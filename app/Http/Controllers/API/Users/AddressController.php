<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Api\ApiResponseTrait;
use App\Models\Address;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AddressController extends Controller
{
    public function index()
    {
        return Address::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'governorate' => 'required',
            'city' => 'required',
            'street' => 'required|string',
            'comments' => 'nullable|string',
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate(); // get user from JWT token
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $address = new Address([
            'governorate' => $request->governorate,
            'city' => $request->city,
            'street' => $request->street,
            'comments' => $request->comments,
        ]);

        $address->user()->associate($user); // associate user_id
        $address->save();

        return response()->json($address, 201);
    }

    public function show()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $addresses = $user->addresses()->get();

        return response()->json($addresses);
    }

    public function update(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'governorate' => 'required|string',
            'city' => 'required|string',
            'street' => 'required|string',
            'comments' => 'nullable|string',
        ]);

        $address = Address::where('user_id', $user->id)->find($id);

        if (!$address) {
            return response()->json(['error' => 'Address not found'], 404);
        }

        $address->update([
            'governorate' => $request->governorate,
            'city' => $request->city,
            'street' => $request->street,
            'comments' => $request->comments,
        ]);

        return response()->json(['message' => 'Address updated successfully', 'address' => $address]);

    }

    public function destroy($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $address = Address::where('user_id', $user->id)->find($id);

        if (!$address) {
            return response()->json(['error' => 'Address not found'], 404);
        }

        $address->delete();

        return response()->json(['message' => 'Address deleted successfully']);
    }
}

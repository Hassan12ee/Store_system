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


    public function store(Request $request)
    {
        $request->validate([
            'governorate' => 'required|string',
            'city' => 'required|string',
            'street' => 'required|string',
            'comments' => 'nullable|string',
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $address = new Address([
            'governorate' => $request->governorate,
            'city' => $request->city,
            'street' => $request->street,
            'comments' => $request->comments,
        ]);

        $address->user()->associate($user);
        $address->save();

        return response()->json([
            'status' => true,
            'message' => 'Address added successfully.',
            'data' => $address
        ], 201);
    }



    public function show()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $addresses = $user->addresses()->get();

        return response()->json([
            'status' => true,
            'data' => $addresses
        ]);
    }



    public function update(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'governorate' => 'required|string',
            'city' => 'required|string',
            'street' => 'required|string',
            'comments' => 'nullable|string',
        ]);

        $address = Address::where('user_id', $user->id)->find($id);

        if (!$address) {
            return response()->json(['status' => false, 'message' => 'Address not found'], 404);
        }

        $address->update([
            'governorate' => $request->governorate,
            'city' => $request->city,
            'street' => $request->street,
            'comments' => $request->comments,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Address updated successfully.',
            'data' => $address
        ]);
    }



    public function destroy($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $address = Address::where('user_id', $user->id)->find($id);

        if (!$address) {
            return response()->json(['status' => false, 'message' => 'Address not found'], 404);
        }

        $address->delete();

        return response()->json([
            'status' => true,
            'message' => 'Address deleted successfully.'
        ]);
    }



}

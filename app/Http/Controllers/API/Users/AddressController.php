<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Api\ApiResponseTrait;
use App\Models\Address;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

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

        $user = Auth::user(); // get the logged-in user

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

    public function show(Address $address)
    {
        return $address;
    }

    public function update(Request $request, Address $address)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'employee_id' => 'nullable|exists:employees,id',
            'governorate' => 'required',
            'city' => 'required',
            'street' => 'required|string',
            'comments' => 'nullable|string',
        ]);

        $address->update($request->all());

        return response()->json($address);
    }

    public function destroy(Address $address)
    {
        $address->delete();

        return response()->json(null, 204);
    }
}

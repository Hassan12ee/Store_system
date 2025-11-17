<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Api\ApiResponseTrait;
use App\Models\Address;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Citie;
use App\Models\Governorate;

class AddressController extends Controller
{


    public function store(Request $request)
    {
        $request->validate([
            'governorate' => 'required|numeric|min:1|max:27|exists:governorates,id',
            'city' => [
                        'required',
                        'min:1',
                        'max:396',
                        'exists:cities,id',
                        function ($attribute, $value, $fail) use ($request) {
                            $city = Citie::find($value);
                            if (!$city || $city->governorate_id != $request->governorate) {
                                $fail('المدينة لا تنتمي إلى المحافظة المحددة.');
                            }
                        },
                    ],
            'street' => 'required|string',
            'comments' => 'nullable|string',
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $address = new Address([
            'governorate_id' => $request->governorate,
            'city_id' => $request->city,
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

        $addresses = $user->addresses()->with([
            'governorate', // القيم والـ attributes المرتبطة
            'city'
        ])->get();

        return response()->json([
                            'status' => true,
                            'data' => $addresses->map(function ($address) {
                                return [
                                    "id" => $address->id,
                                    "user_id" => $address->user_id,
                                    "employee_id" => $address->employee_id,
                                    "governorate_name_ar" => $address->governorate?->governorate_name_ar ?? null,
                                    "governorate_name_en" => $address->governorate?->governorate_name_en ?? null,
                                    "city_name_ar" => $address->city?->city_name_ar ?? null,
                                    "city_name_en" => $address->city?->city_name_en ?? null,
                                    "street" => $address->street,
                                    "comments" => $address->comments,
                                ];

                            })
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
            'governorate' => 'numeric|min:1|max:27|exists:governorates,id',
            'city' => [

                        'min:1',
                        'max:396',
                        'exists:cities,id',
                        function ($attribute, $value, $fail) use ($request) {
                            $city = Citie::find($value);
                            if (!$city || $city->governorate_id != $request->governorate) {
                                $fail('المدينة لا تنتمي إلى المحافظة المحددة.');
                            }
                        },
                    ],
            'street' => 'nullable|string',
            'comments' => 'nullable|string',
        ]);

        $address = Address::where('user_id', $user->id)->find($id);

        if (!$address) {
            return response()->json(['status' => false, 'message' => 'Address not found'], 404);
        }

        $address->update([
            'governorate_id' => $request->governorate,
            'city_id' => $request->city,
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

<?php

namespace App\Http\Controllers\API\employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Address;
use App\Models\Citie;
use App\Models\Governorate;
use App\Models\User;
use App\Models\order;


class adAddressController extends Controller
{
    public function __construct()
    {
        // يمكنك إضافة Middleware هنا إذا لزم الأمر
    }
    public function getGovernorates()
    {
        $governorates = Governorate::all();
        return response()->json([
            'status' => true,
            'data' => $governorates,
        ]);
    }
    public function getCitiesByGovernorate($governorateId)
    {
        $cities = Citie::where('governorate_id', $governorateId)->get();
        return response()->json([
            'status' => true,
            'data' => $cities,
        ]);
    }
    // الحصول على عناوين المستخدم
    // هذا الجزء يستخدم في عرض عناوين المستخدمين عند الطلبات
    public function getUserAddresses($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $addresses = $user->addresses()->with([
            'governorate', // القيم والـ attributes المرتبطة
            'city'
        ])->get();
        return response()->json([
            'status' => true,
            'data' =>
                $addresses->map(function ($address) {
                    return
                        [
                        "id"=> $address->id,
                        "user_id"=> $address->user_id,
                        "employee_id"=> $address->employee_id,
                        "governorate_name_ar"=> $address->governorate->governorate_name_ar,
                        "governorate_name_en"=>    $address->governorate->governorate_name_en,
                        "city_name_ar"=> $address->city->city_name_ar,
                        "city_name_en"=> $address->city->city_name_en,
                        "street" => $address->street,
                         "comments" => $address->comments,
                        ];
                })
        ]);
    }

    // إنشاء عنوان جديد للمستخدم
    // هذا الجزء يستخدم في إنشاء عنوان جديد للمستخدمين عند الطلبات
    public function makeNewAddresse(Request $request , $user_id)
    {

        $user = User::find($user_id);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
            ], 404);
        }

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

        $address = Address::create([
            'user_id' => $user->id,
            'governorate_id' => $request->governorate,
            'city_id' => $request->city,
            'street' => $request->street,
            'comments' => $request->comments,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Address created successfully.',
            'data' => $address,
        ], 201);
    }

    // تحديث عنوان
    // هذا الجزء يستخدم في تحديث عنوان موجود في قاعدة البيانات
    // يمكن استخدامه في تعديل تفاصيل العنوان مثل المحافظة والمدينة والشارع والتعليقات
    public function updateAddress(Request $request, $id)
    {
        $request->validate([
            'governorate' => 'nullable|numeric|min:1|max:27|exists:governorates,id',
            'city' => [
                        'nullable',
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

        $address = Address::findOrFail($id);
        $address->update($request->only(['governorate_id', 'city_id', 'street', 'comments']));

        return response()->json([
            'status' => true,
            'message' => 'Address updated successfully.',
            'data' => $address,
        ]);
    }

    // حذف عنوان
    // هذا الجزء يستخدم في حذف عنوان من قاعدة البيانات
    public function addressdel(Request $request)
    {
        $request->validate([
            'address_id' => 'required|exists:addresses,id',
        ]);

        $address = Address::findOrFail($request->address_id);
        $address->delete();

        return response()->json([
            'status' => true,
            'message' => 'Address deleted successfully.',
        ]);
    }

}

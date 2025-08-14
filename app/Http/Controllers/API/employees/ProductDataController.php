<?php

namespace App\Http\Controllers\Api\employees;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductDataController extends Controller
{
    /**
     * إضافة خاصية جديدة (Attribute)
     */
    public function storeAttribute(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:attributes,name'
        ]);

        $attribute = Attribute::create($validated);

        return response()->json([
            'status'  => true,
            'message' => 'Attribute created successfully',
            'data'    => $attribute
        ]);
    }

    /**
     * إضافة قيمة جديدة للخاصية (Attribute Value)
     */
    public function storeAttributeValue(Request $request)
    {
        $validated = $request->validate([
            'attribute_id' => 'required|exists:attributes,id',
            'value'        => 'required|string|max:100'
        ]);

        $value = AttributeValue::create($validated);

        return response()->json([
            'status'  => true,
            'message' => 'Attribute value created successfully',
            'data'    => $value
        ]);
    }

    /**
     * إضافة متغير جديد لمنتج موجود
     */
    public function storeVariant(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku'        => 'nullable|string|max:100',
            'price'      => 'required|numeric|min:0',
            'quantity'   => 'required|integer|min:0',
            'photo'      => 'nullable|string',
            'values'     => 'required|array',
            'values.*'   => 'exists:attribute_values,id'
        ]);

        DB::beginTransaction();
        try {
            // 1️⃣ إنشاء المتغير
            $variant = ProductVariant::create([
                'product_id' => $validated['product_id'],
                'sku'        => $validated['sku'] ?? null,
                'price'      => $validated['price'],
                'quantity'   => $validated['quantity'],
                'photo'      => $validated['photo'] ?? null,
            ]);

        // 6️⃣ ربط القيم (Attributes) بالمتغير باستخدام attach
$attachData = [];
foreach ($request->attribute_values as $valueId) {
    $attributeValue = AttributeValue::find($valueId);
    $attachData[$valueId] = ['attribute_id' => $attributeValue->attribute_id];
}

$variant->attributeValues()->attach($attachData);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Variant created successfully',
                'data'    => $variant->load('values.attributeValue')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Error creating variant',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function getAllAttributesWithValues()
{
    $attributes = Attribute::with('values:id,attribute_id,value') // جلب القيم المرتبطة
        ->get();

    $result = [];

    foreach ($attributes as $attribute) {
        $attrName = strtolower($attribute->name); // زي: size, color
        $result[$attrName] = $attribute->values->map(function ($val) {
            return [$val->value, [$val->id]];
        })->toArray();
    }

    return response()->json($result, 200, [], JSON_PRETTY_PRINT);
}

    // /**
    //  * عرض جميع الخصائص والقيم المرتبطة بها
    //  */
    // public function getAllAttributes()
    // {
    //     $attributes = Attribute::with('values')->get();

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Attributes retrieved successfully',
    //         'data'    => $attributes
    //     ]);
    // }
}

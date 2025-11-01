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
use Illuminate\Support\Str;
use Milon\Barcode\DNS1D;

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
    protected function generateUniqueBarcode()
    {
        do {
            $barcode = 'P-' . strtoupper(Str::random(8)); // مثل: P-9X8Y2Z1Q
        } while (Product::where('barcode', $barcode)->exists());

        return $barcode;
    }
    /**
     * إضافة متغير جديد لمنتج موجود
     */
    public function storeVariant(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku_En'        => 'nullable|string|max:100',
            'sku_Ar'        => 'nullable|string|max:100',
            'weight'        => 'nullable|string|max:100',
            'price'      => 'required|numeric|min:0',
            'quantity'   => 'required|integer|min:0',
            'warehouse_quantity'   => 'required|integer|min:0',
            'dimensions'   => 'required|string|min:0',
            'photo'      => 'nullable|file|image|max:200048',
            'values'     => 'required|array',
            'values.*'   => 'exists:attribute_values,id'
        ]);

        DB::beginTransaction();
        try {
                        // 2️⃣ توليد باركود فريد
            $barcode = $this->generateUniqueBarcode();

                        // 4️⃣ رفع صورة المتغير
            $variantPhoto = null;
            if ($request->hasFile('variant_photo')) {
                $variantPhoto = 'storage/' . $request->file('variant_photo')->store('variants', 'public');
            }
            // 1️⃣ إنشاء المتغير

            $variant = ProductVariant::create([
                'product_id' => $validated['product_id'],
                'sku_En'        => $validated['sku_En'] ?? null,
                'sku_Ar'        => $validated['sku_Ar'] ?? null,
                'price'      => $validated['price'],
                'quantity'   => $validated['quantity'],
                'warehouse_quantity'   => $validated['warehouse_quantity'],
                'weight'     => $validated['weight'] ?? null,
                'dimensions' => $validated['dimensions'],
                'photo'      => $variantPhoto ?? null,
                'barcode'    => $barcode
            ]);

        // 6️⃣ ربط القيم (Attributes) بالمتغير باستخدام attach
        $attachData = [];
        foreach ($request->values as $valueId) {
            $attributeValue = AttributeValue::find($valueId);
            $attachData[$valueId] = ['attribute_id' => $attributeValue->attribute_id];
        }

        $variant->attributeValues()->attach($attachData);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Variant created successfully',
                'data'    => $variant->load('values')
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

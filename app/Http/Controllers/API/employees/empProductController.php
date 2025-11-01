<?php

namespace App\Http\Controllers\Api\employees;

use App\Http\Controllers\Api\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\AttributeValue;
use App\Models\ProductVariantValue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Milon\Barcode\DNS1D;

class empProductController extends Controller
{
    //
    use ApiResponseTrait;


    // اضافة منتج جديد
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_Ar' => 'required|string',
            'name_En' => 'required|string',
            'Photos' => 'nullable|array',
            'Photos.*' => 'nullable|file|image|max:200048',
            'specifications' => 'nullable|string',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'category_id' => 'nullable|integer|exists:categories,id',

            'sku' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'dimensions' => 'nullable|string',
            'weight' => 'nullable|string',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'warehouse_quantity'=> 'nullable|integer|min:0',
            'variant_photo' => 'nullable|file|image|max:200048',
            'attribute_values' => 'nullable|array',
            'attribute_values.*' => 'integer|exists:attribute_values,id',
        ]);

        if ($validator->fails()) {
            return $this->apiResponse($validator->errors(), message: 'please validate the data', status: 422);
        }

        DB::beginTransaction();
        try {
            // 1️⃣ رفع صور المنتج
            $photoPaths = [];
            if ($request->hasFile('Photos')) {
                foreach ($request->file('Photos') as $photo) {
                    $photoPaths[] = 'storage/' . $photo->store('products', 'public');
                }
            }

            // 2️⃣ توليد باركود فريد
            $barcode = $this->generateUniqueBarcode();

            // 3️⃣ إنشاء المنتج
            $product = Product::create([
                'name_Ar' => $request->name_Ar,
                'name_En' => $request->name_En,
                'Photos' => $photoPaths,
                'specifications' => $request->specifications,
                'brand_id' => $request->brand_id,
                'category_id' => $request->category_id,
            ]);

            // 4️⃣ رفع صورة المتغير
            $variantPhoto = null;
            if ($request->hasFile('variant_photo')) {
                $variantPhoto = 'storage/' . $request->file('variant_photo')->store('variants', 'public');
            }

            // 5️⃣ إنشاء المتغير
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku'        => $request->sku ?? null,
                'price'      => $request->price,
                'quantity'   => $request->quantity,
                'weight' => $request->weight,
                'warehouse_id' => $request->warehouse_id,
                'warehouse_quantity'=> $request->warehouse_quantity,
                'dimensions' => $request->dimensions,
                'photo'      => $variantPhoto,
                'barcode' => $barcode,
            ]);

            // 6️⃣ ربط القيم (Attributes) بالمتغير باستخدام attach
            $attachData = [];
            foreach ($request->attribute_values as $valueId) {
                $attributeValue = AttributeValue::find($valueId);
                $attachData[$valueId] = ['attribute_id' => $attributeValue->attribute_id];
            }

            $variant->attributeValues()->attach($attachData);


            DB::commit();

            // 7️⃣ إرجاع المنتج مع المتغيرات والقيم
            return $this->apiResponse(
                ['product' =>$product->load('variants.attributeValues.attribute')],
                message: 'Product and variant created successfully',
                status: 201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(
                $e->getMessage(),
                message: 'Error creating product and variant',
                status: 500
            );
        }
    }

    // توليد باركود فريد
    // هذا الدالة تولد باركود فريد لكل منتج
    protected function generateUniqueBarcode()
    {
        do {
            $barcode = 'P-' . strtoupper(Str::random(8)); // مثل: P-9X8Y2Z1Q
        } while (Product::where('barcode', $barcode)->exists());

        return $barcode;
    }


    public function showBarcode($barcode)
    {
        $generator = new DNS1D();
        $generator->setStorPath(storage_path('framework/barcodes'));

        // توليد صورة باركود بصيغة Base64
        $barcodeImage = $generator->getBarcodePNG($barcode, 'C128', 2, 60);

        $data = [
            'barcode' => $barcode,
            'image_base64' => 'data:image/png;base64,' . $barcodeImage,
        ];

        return $this->apiResponse($data, 'Barcode generated successfully.', 200);
    }

     // تحديث منتج
    // هذا الدالة تسمح بتحديث معلومات منتج موجود
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['status' => false, 'message' => 'Product not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name_Ar' => 'required|string',
            'name_En' => 'required|string',
            'Photos' => 'nullable|array',
            'Photos.*' => 'nullable|file|image|max:200048',
            'specifications' => 'nullable|string',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'category_id' => 'nullable|integer|exists:categories,id',

            'sku' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'dimensions' => 'nullable|string',
            'weight' => 'nullable|string',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'warehouse_quantity'=> 'nullable|integer|min:0',
            'variant_photo' => 'nullable|file|image|max:200048',
            'attribute_values' => 'nullable|array',
            'attribute_values.*' => 'integer|exists:attribute_values,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1️⃣ رفع صور المنتج
            $photoPaths = [];
            if ($request->hasFile('Photos')) {
                foreach ($request->file('Photos') as $photo) {
                    $photoPaths[] = 'storage/' . $photo->store('products', 'public');
                }
                $product->Photos = array_merge($product->Photos ?? [], $photoPaths);
            }

            // 2️⃣ تحديث معلومات المنتج
            $product->update([
                'name_Ar' => $request->name_Ar,
                'name_En' => $request->name_En,
                'specifications' => $request->specifications,
                'brand_id' => $request->brand_id,
                'category_id' => $request->category_id,
                'Photos' => $product->Photos,
                // لا نقوم بتحديث الباركود لأنه فريد ولا يتغير
            ]);

            // 3️⃣ رفع صورة المتغير
            $variantPhoto = null;
            if ($request->hasFile('variant_photo')) {
                $variantPhoto = 'storage/' . $request->file('variant_photo')->store('variants', 'public');
            }
            $variant = Variants::create([
                'product_id' => $product->id,
                'sku'        => $request->sku ?? null,
                'price'      => $request->price,
                'quantity'   => $request->quantity,
                'photo'      => $variantPhoto,
            ]);
            $variant->variants()->save($variant);

            // 4️⃣ ربط القيم (Attributes) بالمتغير باستخدام attach
            $attachData = [];
            if ($request->attribute_values) {
                foreach ($request->attribute_values as $valueId) {
                    $attributeValue = AttributeValue::find($valueId);
                    if ($attributeValue) {
                        $attachData[$valueId] = ['attribute_id' => $attributeValue->attribute_id];
                    }
                }
                $variant->attributeValues()->sync($attachData);
            }
            DB::commit();
            // 5️⃣ إرجاع المنتج مع المتغيرات والقيم
            return response()->json([
                'status' => true,
                'message' => 'Product updated successfully',
                'data' => $product->load('variants.attributeValues.attribute'),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Error updating product: ' . $e->getMessage(),
            ], 500);
        }
    }


    //اضافة صور للمنتج
    public function addPhotos(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return $this->apiResponse(null, 'Product not found', 404);
        }

        $request->validate([
            'Photos' => 'required|array',
            'Photos.*' => 'file|image|max:200048',
        ]);

        $newPhotos = [];
        foreach ($request->file('Photos') as $photo) {
            $path = $photo->store('products', 'public');
            $newPhotos[] = 'storage/' . $path;
        }

        $product->Photos = array_merge($product->Photos ?? [], $newPhotos);
        $product->save();

        return $this->apiResponse($product, 'Photos added successfully', 200);
    }

    // حذف صورة من المنتج
    public function removePhoto(Request $request, $id)
    {
        $request->validate([
            'photo' => 'required|string',
        ]);

        $product = Product::find($id);
        if (!$product) {
            return $this->apiResponse(null, 'Product not found', 404);
        }

        $photos = $product->Photos ?? [];

        $filtered = array_filter($photos, fn($item) => $item !== $request->photo);

        if (count($photos) === count($filtered)) {
            return $this->apiResponse(null, 'Photo not found in product', 404);
        }

        $storagePath = str_replace('storage/', '', $request->photo);
        Storage::disk('public')->delete($storagePath);

        $product->Photos = array_values($filtered);
        $product->save();

        return $this->apiResponse($product, 'Photo removed successfully', 200);
    }

    //اختيار الصورة الرئيسية للمنتج
    public function setMainPhoto(Request $request, $id)
    {
        $request->validate([
            'photo' => 'required|string'
        ]);

        $product = Product::find($id);
        if (!$product) {
            return $this->apiResponse(null, 'Product not found', 404);
        }

        if (!in_array($request->photo, $product->Photos ?? [])) {
            return $this->apiResponse(null, 'Photo not found in this product', 400);
        }

        $product->main_photo = $request->photo;
        $product->save();

        return $this->apiResponse([
            'main_photo' => $product->main_photo,
            'product' => $product,
        ], 'Main photo updated successfully', 200);
    }

    //عرض تفاصيل منتج
    // هذا الدالة تعرض تفاصيل منتج معين بما في ذلك الخصائص والقيم
    public function show($id)
    {
        $product = Product::with([
            'brand',
            'category',
            'variants.values',  // جلب الخصائص وقيمها
        ])->find($id);

        if (!$product) {
            return $this->apiResponse(null, 'Product not found', 404);
        }


        return $this->apiResponse([
            'product'       =>
                [
                     'id'             => $product->id,
                    'name_Ar'           => $product->name_Ar,
                    'name_En'           => $product->name_En,
                    'Photos' => collect($product->Photos)->map(fn($photo) => asset($photo)),
                    'main_photo'     => $product->main_photo ? asset($product->main_photo) : null,
                    'specifications' => $product->specifications,
                    'created_at'     => $product->created_at,
                    'updated_at'     => $product->updated_at,
                    'variants'       => $product->variants->map(function ($variant) {
                        return [
                            'id'       => $variant->id,
                            'sku'      => $variant->sku,
                            'price'    => $variant->price,
                            'quantity' => $variant->quantity,
                            'warehouse_qty'  => $variant->warehouse_quantity,
                            'photo'    => $variant->photo ? asset($variant->photo) : null,
                            'dimensions'     => $variant->dimensions,
                            'warehouse_id'   => $variant->warehouse_id,
                            'barcode'        => $this->generateBarcodeBase64($variant->barcode) ?? null,
                            'values_with_attributes' => $variant->values->map(function ($value) {
                                return [
                                    'value_id' => $value->id,
                                    'attribute_id' => $value->attribute->id,
                                    'attribute_name' => $value->attribute->name,
                                    'value' => $value->value,
                                ];
                            }),
                        ];

                    }),
                    'brand' => $product->brand ? [
                        'id' => $product->brand->id,
                        'name' => $product->brand->name,
                        'logo' => $product->brand->logo ? asset($product->brand->logo) : null,
                    ] : null,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'image' => $product->category->image ? asset($product->category->image) : null,
                    ] : null,
        ],


        ], 'Product details retrieved successfully', 200);
    }

    // توليد باركود بصيغة Base64
    private function generateBarcodeBase64($barcode)
    {
        if (empty($barcode)) {
            return null; // أو 'No barcode'
        }

        $generator = new DNS1D();
        $generator->setStorPath(storage_path('framework/barcodes'));
        $barcodeImage = $generator->getBarcodePNG($barcode, 'C128', 2, 60);
        return 'data:image/png;base64,' . $barcodeImage;
    }

    // عرض جميع المنتجات
    // هذا الدالة تعرض قائمة بجميع المنتجات مع إمكانية البحث والتصفية
    // يمكنك إضافة المزيد من المعايير حسب الحاجة
    // مثل: السعر، الكمية، التصنيف، إلخ.
    public function index(Request $request)
    {
        $perPage       = $request->query('per_page', 10);
        $search        = $request->query('search');
        $sortBy        = $request->query('sort_by', 'created_at');
        $sortDirection = $request->query('sort_direction', 'desc');
        $minPrice      = $request->query('min_price');
        $maxPrice      = $request->query('max_price');
        $minQuantity   = $request->query('min_quantity');
        $maxQuantity   = $request->query('max_quantity');

        $query = Product::with([
            'variants.values', // القيم والـ attributes المرتبطة
            'brand',
            'category'
        ]);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($minPrice !== null) {
            $query->whereHas('variants', function ($q) use ($minPrice) {
                $q->where('price', '>=', $minPrice);
            });
        }

        if ($maxPrice !== null) {
            $query->whereHas('variants', function ($q) use ($maxPrice) {
                $q->where('price', '<=', $maxPrice);
            });
        }

        if ($minQuantity !== null) {
            $query->whereHas('variants', function ($q) use ($minQuantity) {
                $q->where('quantity', '>=', $minQuantity);
            });
        }

        if ($maxQuantity !== null) {
            $query->whereHas('variants', function ($q) use ($maxQuantity) {
                $q->where('quantity', '<=', $maxQuantity);
            });
        }

        if (in_array($sortBy, ['name', 'created_at']) && in_array($sortDirection, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $products = $query->paginate($perPage);

        $data = [
            'current_page'   => $products->currentPage(),
            'per_page'       => $products->perPage(),
            'total'          => $products->total(),
            'last_page'      => $products->lastPage(),
            'next_page_url'  => $products->nextPageUrl(),
            'prev_page_url'  => $products->previousPageUrl(),
            'products'       => $products->map(function ($product) {
                return [
                    'id'             => $product->id,
                    'name_Ar'           => $product->name_Ar,
                    'name_En'           => $product->name_En,
                    'Photos' => collect($product->Photos)->map(fn($photo) => asset($photo)),
                    'main_photo'     => $product->main_photo ? asset($product->main_photo) : null,
                    'specifications' => $product->specifications,
                    'created_at'     => $product->created_at,
                    'updated_at'     => $product->updated_at,
                    'variants'       => $product->variants->map(function ($variant) {
                        return [
                            'id'       => $variant->id,
                            'sku'      => $variant->sku,
                            'price'    => $variant->price,
                            'quantity' => $variant->quantity,
                            'warehouse_qty'  => $variant->warehouse_quantity,
                            'photo'    => $variant->photo ? asset($variant->photo) : null,
                            'dimensions'     => $variant->dimensions,
                            'warehouse_id'   => $variant->warehouse_id,
                            'barcode'        => $this->generateBarcodeBase64($variant->barcode) ?? null,
                            'values_with_attributes' => $variant->values->map(function ($value) {
                                return [
                                    'value_id' => $value->id,
                                    'attribute_id' => $value->attribute->id,
                                    'attribute_name' => $value->attribute->name,
                                    'value' => $value->value,
                                ];
                            }),
                        ];

                    }),
                    'brand' => $product->brand ? [
                        'id' => $product->brand->id,
                        'name' => $product->brand->name,
                        'logo' => $product->brand->logo ? asset($product->brand->logo) : null,
                    ] : null,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'image' => $product->category->image ? asset($product->category->image) : null,
                    ] : null,

                ];
            }),
        ];

        return $this->apiResponse($data, 'Product list retrieved successfully', 200);
    }

}

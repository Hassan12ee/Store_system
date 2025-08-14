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

// Auth::guard('web')->user()->hasRole('admin');
// Auth::guard('employee')->user()->can('view_orders');
// $user = User::find(1);
// $user->assignRole('admin');

// $employee = Employee::find(5);
// $employee->givePermissionTo('view_orders');

public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string',
        'Photos' => 'nullable|array',
        'Photos.*' => 'nullable|file|image|max:2048',
        'quantity' => 'required|integer|min:0',
        'warehouse_quantity'=> 'nullable|integer|min:0',
        'specifications' => 'nullable|string',
        'price' => 'required|numeric|min:0',
        'weight' => 'nullable|string',
        'dimensions' => 'nullable|string',
        'warehouse_id' => 'required|integer|exists:warehouses,id',

        'sku' => 'nullable|string|max:100',
        'variant_photo' => 'nullable|file|image|max:2048',
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
            'name' => $request->name,
            'barcode' => $barcode,
            'Photos' => $photoPaths,
            'warehouse_quantity'=> $request->warehouse_quantity,
            'specifications' => $request->specifications,
            'weight' => $request->weight,
            'dimensions' => $request->dimensions,
            'warehouse_id' => $request->warehouse_id,
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
            'photo'      => $variantPhoto,
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
            $product->load('variants.attributeValues.attribute'),
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


    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return $this->apiResponse(null, message: 'Product not found', status: 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'quantity' => 'sometimes|integer|min:0',
            'warehouse_quantity'=> 'nullable|integer|min:0',
            'specifications' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'size' => 'nullable|string',
            'dimensions' => 'nullable|string',
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
        ]);

        if ($validator->fails()) {
            return $this->apiResponse($validator->errors(), message: 'please validate the data', status: 422);
        }

        $product->update($request->all());

        return $this->apiResponse( $product, message: 'Product updated successfully', status: 200);
    }


    public function addPhotos(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return $this->apiResponse(null, 'Product not found', 404);
        }

        $request->validate([
            'Photos' => 'required|array',
            'Photos.*' => 'file|image|max:2048',
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


public function show($id)
{
    $product = Product::with([
        'brand',
        'category',
        'attributes.values' // جلب الخصائص وقيمها
    ])->find($id);

    if (!$product) {
        return $this->apiResponse(null, 'Product not found', 404);
    }

    return $this->apiResponse([
        'id' => $product->id,
        'name' => $product->name,
        'quantity' => $product->quantity,
        'specifications' => $product->specifications,
        'price' => $product->price,
        'size' => $product->size,
        'barcode' => $product->barcode ? $this->showBarcode($product->barcode) : null,
        'dimensions' => $product->dimensions,
        'warehouse_id' => $product->warehouse_id,

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

        'attributes' => $product->attributes->map(function ($attribute) {
            return [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'values' => $attribute->values->map(function ($value) {
                    return [
                        'id' => $value->id,
                        'value' => $value->value
                    ];
                })
            ];
        }),

        'Photos' => collect($product->Photos)->map(fn($photo) => asset($photo)),
        'main_photo' => $product->main_photo ? asset($product->main_photo) : null,
        'created_at' => $product->created_at,
        'updated_at' => $product->updated_at,
    ], 'Product details retrieved successfully', 200);
}

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
        'variants.values.attribute', // القيم والـ attributes المرتبطة
        'variants.values.attributeValue',
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
                'name'           => $product->name,
                'main_photo'     => $product->main_photo ? asset($product->main_photo) : null,
                'warehouse_qty'  => $product->warehouse_quantity,
                'specifications' => $product->specifications,
                'barcode'        => $this->generateBarcodeBase64($product->barcode) ?? null,
                'dimensions'     => $product->dimensions,
                'warehouse_id'   => $product->warehouse_id,
                'created_at'     => $product->created_at,
                'updated_at'     => $product->updated_at,
                'variants'       => $product->variants->map(function ($variant) {
                    return [
                        'id'       => $variant->id,
                        'sku'      => $variant->sku,
                        'price'    => $variant->price,
                        'quantity' => $variant->quantity,
                        'photo'    => $variant->photo ? asset($variant->photo) : null,
                        'values'   => $variant->values->map(function ($val) {
                            return [
                                'attribute' => $val->attributeValue->attribute->name ?? null,
                                'value'     => $val->attributeValue->value ?? null,
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

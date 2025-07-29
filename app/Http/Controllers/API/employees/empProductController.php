<?php

namespace App\Http\Controllers\Api\employees;

use App\Http\Controllers\Api\ApiResponseTrait;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
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
            'Photos.*' => 'file|image|max:2048',
            'quantity' => 'required|integer|min:0',
            'specifications' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'size' => 'nullable|string',
            'dimensions' => 'nullable|string',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
        ]);

        if ($validator->fails()) {
            return $this->apiResponse($validator->errors(), message: 'please validate the data', status: 422);
        }

        // رفع الصور
        $photoPaths = [];
        if ($request->hasFile('Photos')) {
            foreach ($request->file('Photos') as $photo) {
                $path = $photo->store('products', 'public');
                $photoPaths[] = 'storage/' . $path;
            }
        }

        // توليد باركود فريد
        $barcode = $this->generateUniqueBarcode();

        $product = Product::create([
            'name' => $request->name,
            'barcode' => $barcode,
            'Photos' => $photoPaths,
            'quantity' => $request->quantity,
            'specifications' => $request->specifications,
            'price' => $request->price,
            'size' => $request->size,
            'dimensions' => $request->dimensions,
            'warehouse_id' => $request->warehouse_id,
        ]);

        return $this->apiResponse($product, message: 'Product created successfully', status: 201);
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
        // توليد صورة باركود بصيغة Base64
        $barcodeImage = DNS1D::getBarcodePNG($barcode, 'C128', 2, 60); // النوع C128 هو Code128

        return response()->json([
            'barcode' => $barcode,
            'image_base64' => 'data:image/png;base64,' . $barcodeImage,
        ]);
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
            return response()->json(['message' => 'Product not found'], 404);
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

        return response()->json([
            'message' => 'Photos added successfully',
            'product' => $product
        ]);
    }


    public function removePhoto(Request $request, $id)
    {
        $request->validate([
            'photo' => 'required|string',
        ]);

        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $photos = $product->Photos ?? [];

        // حاول تحذف الصورة
        $filtered = array_filter($photos, function ($item) use ($request) {
            return $item !== $request->photo;
        });

        if (count($photos) === count($filtered)) {
            return response()->json(['message' => 'Photo not found in product'], 404);
        }

        // حذف من الستوريج
        $storagePath = str_replace('storage/', '', $request->photo);
        Storage::disk('public')->delete($storagePath);

        // تحديث قاعدة البيانات
        $product->Photos = array_values($filtered); // إعادة فهرسة
        $product->save();

        return response()->json([
            'message' => 'Photo removed successfully',
            'product' => $product
        ]);
    }


    public function setMainPhoto(Request $request, $id)
    {
        $request->validate([
            'photo' => 'required|string'
        ]);

        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if (!in_array($request->photo, $product->Photos ?? [])) {
            return response()->json(['message' => 'Photo not found in this product'], 400);
        }

        $product->main_photo = $request->photo;
        $product->save();

        return response()->json([
            'message' => 'Main photo updated successfully',
            'main_photo' => $product->main_photo,
            'product' => $product
        ]);
    }


    public function show($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
        'id' => $product->id,
        'name' => $product->name,
        'quantity' => $product->quantity,
        'specifications' => $product->specifications,
        'price' => $product->price,
        'size' => $product->size,
        'dimensions' => $product->dimensions,
        'warehouse_id' => $product->warehouse_id,
        'Photos' => collect($product->Photos)->map(fn($photo) => asset($photo)),
        'main_photo' => asset($product->main_photo),
        'created_at' => $product->created_at,
        'updated_at' => $product->updated_at,
        ]);

    }


    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');
        $sortBy = $request->query('sort_by', 'created_at');
        $sortDirection = $request->query('sort_direction', 'desc');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $minQuantity = $request->query('min_quantity');
        $maxQuantity = $request->query('max_quantity');

        $query = Product::query();

        // 🔍 بحث بالاسم
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        // 💰 فلترة بالسعر
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        // 📦 فلترة بالكمية
        if ($minQuantity !== null) {
            $query->where('quantity', '>=', $minQuantity);
        }

        if ($maxQuantity !== null) {
            $query->where('quantity', '<=', $maxQuantity);
        }

        // ✅ الترتيب
        if (in_array($sortBy, ['name', 'price', 'quantity', 'created_at']) && in_array($sortDirection, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $products = $query->paginate($perPage);

        return response()->json([
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'last_page' => $products->lastPage(),
            'next_page_url' => $products->nextPageUrl(),
            'prev_page_url' => $products->previousPageUrl(),
            'products' => collect($products->items())->map(function ($product) {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'Photos' => collect($product->Photos)->map(fn($photo) => asset($photo)),
            'main_photo' => $product->main_photo ? asset($product->main_photo) : null,
            'quantity' => $product->quantity,
            'specifications' => $product->specifications,
            'price' => $product->price,
            'size' => $product->size,
            'dimensions' => $product->dimensions,
            'warehouse_id' => $product->warehouse_id,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
        }),

        ]);
    }



}

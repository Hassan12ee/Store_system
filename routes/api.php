<?php

use App\Http\Controllers\Api\employees\empAuthController;
use App\Http\Controllers\API\employees\EmpOrderController;
use App\Http\Controllers\Api\employees\empProductController;
use App\Http\Controllers\Api\employees\WarehouseReceiptController;
use App\http\Controllers\Api\Users\AddressController;
use App\Http\Controllers\Api\Users\AuthController;
use App\http\Controllers\Api\Users\OrderController;
use App\Http\Controllers\Api\Users\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;





Route::controller(ProductController::class)->prefix('Products')->group(function () {
    Route::get('/{id}', 'show');                       // عرض منتج مفرد
    Route::get('/', 'index');                          // عرض قائمة المنتجات
});

Route::middleware(['auth:users', 'verified'])->group(function () {

    Route::controller(ProductController::class)->group(function ()   {
        Route::prefix('cart')->group(function () {
            Route::post('add', 'addToCart');
            Route::get('/', 'getCart');
            Route::delete('{id}', 'removeFromCart');
            Route::put('/','updateCart');
        });
        Route::post('/favorites/add','addToFavorites');
        Route::get('/favorites','getFavorites');
        Route::delete('/favorites/{productId}','removeFromFavorites');
        Route::get('/wishlist', 'getWishlist');
        Route::post('/wishlist', 'addToWishlist');
        Route::delete('/wishlist/{id}','removeFromWishlist');
    });
    Route::get('/secure', function () {
        return response()->json(['message' => 'You are verified ✅']);
    });
        Route::post('addresses', [AddressController::class, 'store']);
        Route::get('/addresses', [AddressController::class, 'show']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
        Route::post('orders/from-cart', [OrderController::class, 'createOrderFromCart']);
        Route::get('/orders', [OrderController::class, 'getUserOrders']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);



});

Route::prefix('auth')->controller(AuthController::class)->group(function () {

    // 🔐 استعادة كلمة المرور باستخدام OTP
    Route::post('password/send-otp',     'sendResetOtp');         // إرسال OTP
    Route::post('password/verify-otp',   'verifyResetOtp');       // التحقق من OTP
    Route::post('password/reset',        'resetPasswordWithOtp'); // إعادة تعيين كلمة المرور

    // ✅ التحقق من البريد بعد التسجيل
    Route::post('verify',      'verify');       // تأكيد OTP
    Route::post('otp/resend',  'resendOtp');    // إعادة إرسال OTP
     Route::post('/send-phone-code', [UserController::class, 'sendPhoneVerificationCode']);
    Route::post('/verify-phone', [UserController::class, 'verifyPhoneCode']);

    // 📝 تسجيل وحساب جديد
    Route::post('register', 'register');

    // 🔑 تسجيل دخول
    Route::post('login', 'login');

    // 🛡️ مسارات محمية بالتوكن
    Route::middleware('auth:users')->group(function () {
        Route::get('me', 'me');           // جلب بيانات المستخدم
        Route::post('logout', 'logout'); // تسجيل الخروج
        Route::post('refresh', 'refresh'); // تجديد التوكن
    });

});

// ====================================================================
//
//                              employee
//                     role:support ,sales, store, admin
// ====================================================================

Route::prefix('employee')->group(function () {


    // ✅ فقط المسؤولين (admin)
    Route::middleware(['auth:employee'])->group(function () {


        Route::prefix('products')->controller(empProductController::class)->group(function () {

            Route::post('/', 'store');                         // إضافة منتج
            Route::put('/{id}', 'update');                     // تعديل منتج
            Route::delete('/{id}', 'destroy');                 // حذف منتج

            Route::post('/{id}/add-photos', 'addPhotos');      // رفع صور
            Route::put('/{id}/main-photo', 'setMainPhoto');    // تحديد صورة رئيسية
            Route::delete('/{id}/remove-photo', 'removePhoto');// حذف صورة

            Route::get('/{id}', 'show');                       // عرض منتج مفرد
            Route::get('/', 'index');                          // عرض قائمة المنتجات
            Route::get('/showBarcode/{id}', 'showBarcode');


        });
        Route::get('/warehouse-receipts', [WarehouseReceiptController::class, 'filter']);
        Route::post('/warehouse-receipts', [WarehouseReceiptController::class, 'store']);
        Route::get('/warehouse-receipts/getProductHistory/{id}', [WarehouseReceiptController::class, 'getProductHistory']);
        Route::prefix('orders')->controller(EmpOrderController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/{id}', 'show');
    Route::get('/filter', 'filter');
    Route::put('/{id}/status', 'updateStatus');
Route::prefix('damaged-products')->group(function () {
    Route::get('/', [DamagedProductController::class, 'index']); // فلترة مع pagination
    Route::post('/', [DamagedProductController::class, 'store']); // تسجيل تالف جديد
});

});
    });

});

Route::prefix('auth/emp')->controller(empAuthController::class)->group(function () {


    // 🔐 عمليات استعادة كلمة المرور (OTP عبر البريد)
    Route::post('password/send-otp',    'sendResetOtp');      // إرسال OTP
    Route::post('password/verify-otp',  'verifyResetOtp');    // تأكيد OTP
    Route::post('password/reset',       'resetPassword');     // تعيين كلمة مرور جديدة

    // ✅ تأكيد البريد بعد التسجيل
    Route::post('verify',      'verify');         // التحقق من OTP بعد التسجيل
    Route::post('otp/resend',  'resendOtp');      // إعادة إرسال OTP

    // 🔑 تسجيل الدخول
    Route::post('login', 'login');

    // 🛡️ عمليات محمية بـ JWT
    Route::middleware('auth:employee')->group(function () {
            Route::prefix('attendance')->group(function () {

                Route::post('check-in', 'checkIn');
                Route::post('check-out', 'checkOut');
            });

        // ✅ فقط الأدمن يقدر يسجل موظفين جدد
        Route::middleware('emp.role:admin')->group(function () {
            Route::post('register', 'register'); // تسجيل موظف جديد
            Route::get('attendance','index');
            Route::get('attendance/monthly-report','monthlyReport');
        });

        // 👤 المستخدم الحالي
        Route::get('me', 'me');

        // 🔁 تجديد التوكن أو الخروج
        Route::post('refresh', 'refresh');
        Route::post('logout', 'logout');
    });
});








// use Spatie\Permission\Models\Role;
// use Spatie\Permission\Models\Permission;

// Route::get('/setup-roles-permissions', function () {
//     // إنشاء الصلاحيات
//     Permission::create(['name' => 'view_dashboard',
//     'guard_name' => 'employee' // أو 'web' للمستخدمين
//     ]);
//     Permission::create(['name' => 'manage_users']);
//     Permission::create(['name' => 'manage_orders']);

//     // إنشاء الأدوار
//     $admin = Role::create(['name' => 'admin'
// ,
//     'guard_name' => 'employee' // أو 'web' للمستخدمين
// ]);
//     $employee = Role::create(['name' => 'employee']);

//     // ربط صلاحيات بالرول
//     $admin->givePermissionTo(['view_dashboard', 'manage_users', 'manage_orders']);
//     $employee->givePermissionTo(['view_dashboard']);

//     return 'Roles and permissions created';
// });
// use Spatie\Permission\Models\Role;
// use Spatie\Permission\Models\Permission;

// // إنشاء صلاحية
// $permission = Permission::create([
//     'name' => 'view_reports',
//     'guard_name' => 'employee' // أو 'web' للمستخدمين
// ]);

// // إنشاء رول
// $role = Role::create([
//     'name' => 'manager',
//     'guard_name' => 'employee' // أو 'web' للمستخدمين
// ]);

// // ربط صلاحية بالرول
// $role->givePermissionTo('view_reports');

// // أو ربط رول بصلاحية
// $permission->assignRole('manager');

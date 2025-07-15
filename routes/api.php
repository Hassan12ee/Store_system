<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Users\AuthController;
use App\Http\Controllers\Api\employees\empAuthController;
use App\Http\Controllers\Api\employees\empProductController;
use App\Http\Controllers\Api\Users\ProductController;
use app\http\Controllers\Api\Users\AddressController;
use app\http\Controllers\Api\Users\OrderController;
Route::controller(ProductController::class)->prefix('Products')->group(function () {

            Route::get('/{id}', 'show');                       // عرض منتج مفرد
            Route::get('/', 'index');                          // عرض قائمة المنتجات
});

Route::middleware(['auth:users', 'verified'])->group(function () {
    // routes/api.php




Route::controller(ProductController::class)->group(function ()   {
Route::prefix('cart')->group(function () {
    Route::post('add', 'addToCart');
    Route::get('/', 'getCart');
    Route::delete('{id}', 'removeFromCart');
    Route::put('{id}','updateCart');
});
    Route::post('/favorites/add','addToFavorites');
    Route::get('/favorites','getFavorites');
    Route::delete('/favorites/{productId}','removeFromFavorites');
    Route::get('/wishlist', 'getWishlist');
    Route::post('/wishlist', 'addToWishlist');
    Route::delete('/wishlist','removeFromWishlist');
    // Route::apiResource('addresses', AddressController::class);





});
    Route::get('/secure', function () {
        return response()->json(['message' => 'You are verified ✅']);
    });
    Route::controller(OrderController::class)->group(function ()   {
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
        Route::delete('orders/{id}', [OrderController::class, 'destroy']);
        });

});




Route::prefix('auth')->controller(AuthController::class)->group(function () {

    // 🔐 استعادة كلمة المرور باستخدام OTP
    Route::post('password/send-otp',     'sendResetOtp');         // إرسال OTP
    Route::post('password/verify-otp',   'verifyResetOtp');       // التحقق من OTP
    Route::post('password/reset',        'resetPasswordWithOtp'); // إعادة تعيين كلمة المرور

    // ✅ التحقق من البريد بعد التسجيل
    Route::post('verify',      'verify');       // تأكيد OTP
    Route::post('otp/resend',  'resendOtp');    // إعادة إرسال OTP

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
// emp.role:support ,sales, store, admin
// ====================================================================
Route::prefix('employee')->group(function () {


    // ✅ فقط المسؤولين (admin)
    Route::middleware(['auth:employee', 'emp.role:admin'])->group(function () {


        Route::prefix('products')->controller(empProductController::class)->group(function () {

            Route::post('/', 'store');                         // إضافة منتج
            Route::put('/{id}', 'update');                     // تعديل منتج
            Route::delete('/{id}', 'destroy');                 // حذف منتج

            Route::post('/{id}/add-photos', 'addPhotos');      // رفع صور
            Route::put('/{id}/main-photo', 'setMainPhoto');    // تحديد صورة رئيسية
            Route::delete('/{id}/remove-photo', 'removePhoto');// حذف صورة

            Route::get('/{id}', 'show');                       // عرض منتج مفرد
            Route::get('/', 'index');                          // عرض قائمة المنتجات
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









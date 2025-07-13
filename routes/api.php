<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\empAuthController;

Route::middleware(['auth:users', 'verified'])->group(function () {

// Route::controller(DiabtesRecord::class)->group(function ()   {
// Route::get('/records/{id}','showhistory');
// });
    Route::get('/secure', function () {
        return response()->json(['message' => 'You are verified ✅']);
    });
});


Route::prefix('auth')->group(function () {
    Route::controller(AuthController::class)->group(function ()   {
    Route::post('password/send-otp','sendResetOtp');
    Route::post('password/verify-otp','verifyResetOtp');
    Route::post('password/reset','resetPasswordWithOtp');
    Route::post('verify','verify');
    Route::post('otp/resend','resendOtp');
    Route::post('register','register');
    Route::post('login','login');
    Route::middleware('auth:users')->group(function () {
        Route::get('me','me');
        Route::post('logout','logout');
        Route::post('refresh','refresh');
    });
});
});
// ====================================================================
//
//                              employee
//Route::middleware(['auth:employee', 'emp.role:admin'])->group(function () {
//     Route::post('/employees/create', [AdminController::class, 'createEmployee']);
// });

// Route::middleware(['auth:employee', 'emp.role:store'])->group(function () {
//     Route::get('/store/orders', [StoreController::class, 'viewOrders']);
// });

// Route::middleware(['auth:employee', 'emp.role:sales'])->group(function () {
//     Route::post('/sales/order', [SalesController::class, 'createOrder']);
// });

// Route::middleware(['auth:employee', 'emp.role:support'])->group(function () {
//     Route::get('/support/tickets', [SupportController::class, 'listTickets']);
// });

// ====================================================================
Route::prefix('employee')->group(function () {
Route::middleware(['auth:employee'])->group(function () {
// Route::controller(DiabtesRecord::class)->group(function ()   {
// Route::get('/records/{id}','showhistory');
// });

});
});

Route::prefix('auth/emp')->group(function () {
    Route::controller(empAuthController::class)->group(function ()   {
    Route::post('password/send-otp','sendResetOtp');
    Route::post('password/verify-otp','verifyResetOtp');
    Route::post('password/reset','resetPassword');
    Route::post('verify','verify');
    Route::post('otp/resend','resendOtp');
    Route::post('login','login');
    Route::middleware('auth:employee')->group(function () {
        Route::middleware('emp.role:admin')->group(function () {
            Route::post('register','register');
    });
        Route::get('me','me');
        Route::post('logout','logout');
        Route::post('refresh','refresh');
    });
    });
});

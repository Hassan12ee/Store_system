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
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Api\employees\ProductDataController;

Route::controller(ProductController::class)->prefix('Products')->group(function () {
    Route::get('/{id}', 'show');                       // عرض منتج مفرد
    Route::get('/', 'index');                          // عرض قائمة المنتجات
});
// 'verified'
Route::middleware(['auth:web'])->group(function () {

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
    Route::middleware('auth:web')->group(function () {
        Route::get('me', 'me');           // جلب بيانات المستخدم
        Route::post('logout', 'logout'); // تسجيل الخروج
        Route::post('refresh', 'refresh'); // تجديد التوكن
    });

});

// ====================================================================
//
//                              employee
//   roles:supporter,Super Admin ,Moderator, warehouse worker, admin
// ====================================================================

Route::prefix('employee')->middleware(['auth:web'])->group(function () {
    Route::prefix('products')->group(function () {
    Route::controller(ProductDataController::class)->group(function () {
        Route::middleware(['permission:add_products'])->group(function () {
            Route::post('/attributes','storeAttribute');
            Route::post('/attribute-values','storeAttributeValue');
            Route::post('/variants','storeVariant');
             Route::get('/getAllAttributes','getAllAttributeswithValues');
        });
    });

    Route::controller(empProductController::class)->group(function () {
        Route::middleware(['permission:add_products'])->group(function () {
            Route::post('/', 'store');                                                           // إضافة منتج
            Route::post('/{id}/add-photos', 'addPhotos');                                        // رفع صور
            Route::put('/{id}/main-photo', 'setMainPhoto');                                      // تحديد صورة رئيسية
        });
        Route::middleware(['permission:view_products'])->group(function () {
            Route::get('/{id}', 'show');                                                        // عرض منتج مفرد
            Route::get('/', 'index');                                                           // عرض قائمة المنتجات
            Route::get('/showBarcode/{id}', 'showBarcode');                                     // عرض باركود منتج
            Route::get('/AllAttributes', 'getAllAttributesWithValues');
        });
        Route::middleware(['permission:edit_products'])->group(function () {
            Route::put('/{id}', 'update');                                                     // تعديل منتج
        });
        Route::middleware(['permission:remove_products'])->group(function () {
            Route::delete('/{id}', 'destroy');                                                 // حذف منتج
            Route::delete('/{id}/remove-photo', 'removePhoto');                                //  حذف صورة المنتج
        });
        });
    });
    Route::prefix('warehouse')->controller(WarehouseReceiptController::class)->group(function () {
        Route::middleware(['permission:add_storage'])->group(function () {
            Route::post('/receipts', 'store');                                               //  إضافة إيصال استلام منتج
        });
        Route::middleware(['permission:view_storage'])->group(function () {
            Route::get('/receipts','filter');                                                //  فلترة ايصالات استلام المنتجات
            Route::get('/receipts/{id}', 'show');                                            //  عرض إيصال استلام المنتج
            Route::get('/receipts/getProductHistory/{id}','getProductHistory');              //  جلب تاريخ استلام منتج
        });
        // Route::middleware(['permission:edit_storage'])->group(function () {
        //     Route::put('/receipts/{id}', 'update');                                      // تعديل إيصال استلام المنتج
        // });
        // Route::middleware(['permission:remove_storage'])->group(function () {
        //     Route::delete('/receipts/{id}', 'destroy');                                  // حذف إيصال استلام المنتج
        // });
    });
    Route::prefix('warehouse')->controller(WarehouseReceiptController::class)->group(function () {
        Route::prefix('damagedProducts')->group(function () {
            Route::middleware(['permission:add_storage'])->group(function () {
                Route::post('/','store');                                                   // تسجيل تالف جديد
            });
            Route::middleware(['permission:view_storage'])->group(function () {
                Route::get('/','index');                                                    // فلترة مع pagination
            });
            // Route::middleware(['permission:edit_storage'])->group(function () {
            //     Route::put('/{id}','update');                                            // تعديل تالف
            // });
            // Route::middleware(['permission:remove_storage'])->group(function () {
            //     Route::delete('/{id}','destroy');                                        // حذف تالف
            // });
        });
    });
    Route::prefix('orders')->controller(EmpOrderController::class)->group(function () {
        Route::middleware(['permission:add_orders'])->group(function () {
            Route::post('/guest-order','createOrderForGuest');                              // إنشاء طلب للزائر
            Route::post('/existing-user-order','createOrderForExistingUser');               // إنشاء طلب لمستخدم مسجل
            Route::post('users/{userId}/addresses','makeNewAddresse');                      // إضافة عنوان جديد لمستخدم
        });
        Route::middleware(['permission:view_orders'])->group(function () {
            Route::get('/', 'index');                                                       // عرض الطلبات
            Route::get('/{id}', 'show');                                                    // عرض طلب مفرد
            Route::get('/filter', 'filter');                                                // فلترة الطلبات
            Route::post('/check-phone','checkPhoneNumber');                                 // التحقق من رقم الهاتف
            Route::get('users/{userId}/addresses','getUserAddresses');                      // جلب عناوين مستخدم
        });
        Route::middleware(['permission:edit_orders'])->group(function () {
            Route::put('/{id}/status', 'updateStatus');                                     // تحديث حالة الطلب
            Route::put('/{id}/address', 'updateAddress');                                   // تحديث عنوان الطلب
            Route::put('/{id}/update', 'updateOrder');                                           // تحديث تفاصيل الطلب
        });
        Route::middleware(['permission:remove_orders'])->group(function () {
            Route::delete('/{id}/address', 'addressdel');                                   // حذف عنوان الطلب
        });
    });

});



Route::prefix('auth/emp')->controller(empAuthController::class)->group(function () {

    Route::post('login', 'login');      // 🔑 تسجيل الدخول
    Route::middleware('auth:web')->group(function () {
        // ✅ فقط الأدمن يقدر يسجل موظفين جدد
        Route::middleware('permission:add_employee')->group(function () {
            Route::post('register', 'register');        // تسجيل موظف جديد
        });

        // 👤 المستخدم الحالي
        Route::get('me', 'me');

        // 🔁 تجديد التوكن أو الخروج
        Route::post('refresh', 'refresh');
        Route::post('logout', 'logout');
    });
});

// Route::get('/setup-roles-permissions', function () {
// $role = Role::create(['name' => 'Super Admin']);
//  $role->givePermissionTo(Permission::all());
// User::find(2)->assignRole('Super Admin');
// });





// Route::get('/setup-roles-permissions', function () {
//     // إنشاء الصلاحيات

//     // إنشاء الأدوار
//     $admin = Role::create(['name' => 'admin']);
//     $warehouse_worker = Role::create(['name' => 'warehouse worker']);
//     $Moderator = Role::create(['name' => 'Moderator']);
//     $supporter = Role::create(['name' => 'supporter']);
//     // ربط صلاحيات بالرول
//     $admin->givePermissionTo(['view_orders','view_users','edit_users', 'add_orders', 'remove_orders', 'edit_orders', 'view_products', 'add_products', 'remove_products', 'edit_products', 'view_storage', 'add_storage', 'remove_storage', 'edit_storage', 'view_employee', 'add_employee',  'edit_employee','givePermissionToRole']);
//     $warehouse_worker->givePermissionTo(['view_orders','edit_orders', 'view_storage', 'add_storage', 'remove_storage', 'edit_storage']);
//     $Moderator->givePermissionTo(['view_orders', 'add_orders','edit_orders', 'view_products', 'view_storage']);
//     $supporter->givePermissionTo(['view_orders', 'add_orders',  'edit_orders','view_users','edit_users', 'view_products', 'view_storage']);
//     return 'Roles and permissions created';
// });


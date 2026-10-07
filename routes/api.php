<?php
use App\Models\User;
use App\Http\Controllers\Api\Users\AuthController;
use App\Http\Controllers\Api\Users\ProductController;
use App\http\Controllers\Api\Users\AddressController;
use App\http\Controllers\Api\Users\OrderController;
use App\Http\Controllers\Api\employees\empAuthController;
use App\Http\Controllers\API\employees\EmpOrderController;
use App\Http\Controllers\API\employees\EmpConfirmOrderController;
use App\Http\Controllers\Api\employees\empProductController;
use App\Http\Controllers\Api\employees\WarehouseReceiptController;
use App\Http\Controllers\Api\employees\ProductDataController;
use App\Http\Controllers\Api\employees\adAddressController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;

Route::controller(ProductController::class)->group(function ()   {
    Route::group(['prefix' => 'Products'], function () {
        Route::get('/{id}', 'show');                       // عرض منتج مفرد
        Route::get('/', 'index');                          // عرض قائمة المنتجات
    });
    //Cart Routes
    Route::group(['prefix' => 'cart'], function () {
        Route::delete('/clear','clearCart'); // Clear cart
        Route::delete('/remove/{id}','removeFromCart'); // Remove one
        Route::post('/add','addToCart'); // Add to cart
        Route::get('/','getCart'); // Get cart items
        Route::put('/','updateCart');
    });
        //  Wishlist Routes
    Route::group(['prefix' => 'wishlist'], function () {
        Route::delete('/clear','clearWishlist'); // Clear wishlist
        Route::delete('/remove/{id}','removeFromWishlist'); // Remove one
        Route::post('/add','addToWishlist'); // Add to wishlist
        Route::get('/','getWishlist'); // Get wishlist items
    });
});
Route::controller(AddressController::class)->group(function ()   {
    Route::post('addresses','store');
    Route::get('/addresses','show');
    Route::put('/addresses/{id}','update');
    Route::delete('/addresses/{id}','destroy');
});
Route::controller(OrderController::class)->group(function ()   {
    Route::post('orders/from-cart','createOrderFromCart');
    Route::get('/orders','getUserOrders');
    Route::get('/orders/{id}','show');
});
Route::prefix('auth')->controller(AuthController::class)->group(function () {
    //  استعادة كلمة المرور باستخدام OTP
    Route::post('password/send-otp',     'sendResetOtp');         // إرسال OTP
    Route::post('password/verify-otp',   'verifyResetOtp');       // التحقق من OTP
    Route::post('password/reset',        'resetPasswordWithOtp'); // إعادة تعيين كلمة المرور
    //  التحقق من البريد بعد التسجيل
    Route::post('verify',      'verify');       // تأكيد OTP
    Route::post('otp/resend',  'resendOtp');    // إعادة إرسال OTP
    Route::post('/send-phone-code', [UserController::class, 'sendPhoneVerificationCode']);
    Route::post('/verify-phone', [UserController::class, 'verifyPhoneCode']);
    // تسجيل وحساب جديد
    Route::post('register', 'register');
    //تسجيل دخول
    Route::post('login', 'login');
    //مسارات محمية بالتوكن
    Route::middleware('auth:web')->group(function () {
        Route::get('me', 'me');           // جلب بيانات المستخدم
        Route::post('logout', 'logout'); // تسجيل الخروج
        Route::post('refresh', 'refresh'); // تجديد التوكن
    });

});
// route to merge guest data into user on login/order creation:
Route::post('/guest/merge-to-user', [GuestController::class, 'mergeToUser'])->middleware('auth:sanctum');
// ====================================================================
//                              employee
//   roles:Super Admin,Admin,Moderator,Supporter,Warehouse worker
// ====================================================================
Route::prefix('employee')->middleware(['auth:web'])->group(function () {
    Route::prefix('products')->group(function () {
        Route::controller(ProductDataController::class)->group(function () {
            Route::middleware(['permission:add_products'])->group(function () {
                Route::post('/attributes','storeAttribute');
                Route::post('/attribute-values','storeAttributeValue');
                Route::post('/variants','storeVariant');// إضافة منتج
            });
        });
        Route::controller(empProductController::class)->group(function () {
            Route::get('/showAttributes', 'getAllAttributes');
            Route::middleware(['permission:add_products'])->group(function () {
                Route::post('/', 'store');                                                           // إضافة منتج
                Route::post('/{id}/add-photos', 'addPhotos');                                        // رفع صور
                Route::put('/{id}/main-photo', 'setMainPhoto');                                      // تحديد صورة رئيسية
            });
            Route::middleware(['permission:view_products'])->group(function () {
                Route::get('/', 'index');
                Route::get('/p', 'indexp');                                                           // عرض قائمة المنتجات
                Route::get('/{id}', 'show');                                                        // عرض منتج مفرد
                Route::get('/showBarcode/{id}', 'showBarcode');
                // عرض باركود منتج
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
    // Route::middleware(['permission:view_orders','permission:edit_orders'])->group(function () {
    //     Route::controller(EmpConfirmOrderController::class)->group(function () {
    //         // GET الموظف يسحب Order
    //         Route::get('/confirm-order', 'workorganization');
    //         // POST تسجيل محاولة (واتساب / كول)
    //         Route::post('/logattempt', 'logAttempt');
    //         // POST إنتهاء تأكيد الطلب
    //         Route::post('/end-confirmation', 'endConfirmation');
    //     });
    // });
    Route::prefix('orders')->controller(EmpOrderController::class)->group(function () {
        Route::middleware(['permission:view_orders','permission:edit_orders'])->group(function () {
            Route::controller(EmpConfirmOrderController::class)->group(function () {
                // GET الموظف يسحب Order
                Route::get('/Confirmation', 'workorganization');
                // POST تسجيل محاولة (واتساب / كول)
                Route::post('/logattempt', 'logAttempt');
                // POST إنتهاء تأكيد الطلب
                Route::post('/end-confirmation', 'endConfirmation');
            });
        });
        Route::middleware(['permission:add_orders'])->group(function () {
            Route::post('/guest-order','createOrderForGuest');                              // إنشاء طلب للزائر
            Route::post('/existing-user-order','createOrderForExistingUser');               // إنشاء طلب لمستخدم مسجل
        });
        Route::middleware(['permission:view_orders'])->group(function () {
            Route::get('/', 'index');                                                       // عرض الطلبات
            Route::get('/{id}', 'show');                                                    // عرض طلب مفرد
            Route::get('/filter', 'filter');                                                // فلترة الطلبات
            Route::post('/check-phone','checkPhoneNumber');                                 // التحقق من رقم الهاتف
        });
        Route::middleware(['permission:edit_orders'])->group(function () {
            Route::put('/{id}/status', 'updateStatus');                                     // تحديث حالة الطلب
            Route::put('/{id}/update', 'updateOrder');                                           // تحديث تفاصيل الطلب
        });
        Route::middleware(['permission:remove_orders'])->group(function () {
            Route::delete('/{d_id}', 'deleteProductFromOrder');                                              // حذف طلب
        });
    });
    Route::controller(adAddressController::class)->group(function () {
        Route::middleware(['permission:add_orders'])->group(function () {
            Route::post('users/{userId}/addresses','makeNewAddresse');                      // إضافة عنوان جديد لمستخدم
        });
        Route::middleware(['permission:view_orders'])->group(function () {
            Route::get('users/{userId}/addresses','getUserAddresses');                      // جلب عناوين مستخدم
            Route::get('governorates','getGovernorates');                                   // جلب المحافظات
            Route::get('cities/{governorateId}','getCitiesByGovernorate');                  // جلب مدن محافظة معينة
        });
        Route::middleware(['permission:edit_orders'])->group(function () {
            Route::put('/{id}/address', 'updateAddress');                                   // تحديث عنوان الطلب
        });
        Route::middleware(['permission:remove_orders'])->group(function () {
            Route::delete('/{id}/address', 'addressdel');                                   // حذف عنوان الطلب
        });
    });
});
Route::prefix('auth/emp')->controller(empAuthController::class)->group(function () {
    Route::post('login', 'login');      //  تسجيل الدخول
    Route::middleware('auth:web')->group(function () {
        //فقط الأدمن يقدر يسجل موظفين جدد
        Route::middleware('permission:add_employee')->group(function () {
            Route::post('register', 'register');        // تسجيل موظف جديد
        });
        //المستخدم الحالي
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
// // إنشاء الصلاحيات
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


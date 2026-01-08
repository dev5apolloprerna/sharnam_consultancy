<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UsersApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\BranchMasterController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RazorpayController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('login', [AuthController::class, 'login']);    
Route::get('logout', [AuthController::class, 'logout']);
Route::post('refresh', [AuthController::class, 'refresh']);
Route::post('change_password', [AuthController::class, 'change_password']);
Route::get('me', [AuthController::class, 'me']);
//Banner 
Route::post('banner', [BannerController::class, 'index']);

//Customer 
Route::post('customer_list', [CustomerController::class, 'index']);
Route::post('create_customer', [CustomerController::class, 'create_customer']);
Route::post('edit_customer', [CustomerController::class, 'edit_customer']);
Route::post('delete_customer', [CustomerController::class, 'delete_customer']);
Route::post('inactive_active_user', [CustomerController::class, 'inactive_active_user']);
Route::post('customer_changepassword', [CustomerController::class, 'customer_changepassword']);
Route::post('forgotpasswordmail', [CustomerController::class, 'forgotpasswordmail']);
Route::post('verifyOTP', [CustomerController::class, 'verifyOTP']);
Route::post('setPassword', [CustomerController::class, 'setPassword']);
Route::post('userProfileImg', [CustomerController::class, 'userProfileImg']);
Route::post('viewImage', [CustomerController::class, 'viewImage']);

//Product 
Route::post('product_list', [ProductController::class, 'index']);
Route::post('tranding_new_products', [ProductController::class, 'tranding_new_products']);
Route::post('product_detail', [ProductController::class, 'product_detail']);
Route::post('create_product', [ProductController::class, 'create_product']);
Route::post('edit_product', [ProductController::class, 'edit_product']);
Route::post('delete_product', [ProductController::class, 'delete_product']);
Route::post('wishlist', [ProductController::class, 'wishlist']);
Route::post('addto_wishlist', [ProductController::class, 'addtowishlist']);
Route::post('removeFrom_wishlist', [ProductController::class, 'removeFromwishlist']);
Route::post('active_inactive_product', [ProductController::class, 'active_inactive_product']);
Route::post('related_products', [ProductController::class, 'related_products']);

//Category 
Route::post('category_list', [CategoryController::class, 'index']);
Route::post('subcategory_list', [CategoryController::class, 'subcategory_list']);
Route::post('create_category', [CategoryController::class, 'create_category']);
Route::post('edit_category', [CategoryController::class, 'edit_category']);
Route::post('delete_category', [CategoryController::class, 'delete_category']);

//order
Route::post('cart', [OrderController::class, 'cart']);
Route::post('order_history', [OrderController::class, 'order_history']);
Route::post('addtoCart', [OrderController::class, 'addtoCart']);
Route::post('editCart', [OrderController::class, 'editCart']);
Route::post('deleteProductFromCart', [OrderController::class, 'deleteProductFromCart']);
Route::post('checkout', [OrderController::class, 'checkout']);
Route::post('submitOrder', [OrderController::class, 'submitOrder']);
Route::post('couponapply', [OrderController::class, 'couponapply']);
Route::post('processing_order', [OrderController::class, 'processing_order']);
Route::post('delivered_order', [OrderController::class, 'delivered_order']);
Route::post('cancel_order', [OrderController::class, 'cancel_order']);
Route::post('order_details', [OrderController::class, 'order_details']);
Route::post('cart_total', [OrderController::class, 'cart_total']);
Route::post('state_list', [OrderController::class, 'state_list']);
Route::post('shipping_charges', [OrderController::class, 'shipping_charges']);

//Review
Route::post('addReview', [ReviewController::class, 'addReview']);
Route::post('viewReview', [ReviewController::class, 'viewReview']);

//Order for admin
Route::post('new_order_for_admin', [OrderController::class, 'new_order_for_admin']);
Route::post('ongoing_order_for_admin', [OrderController::class, 'ongoing_order_for_admin']);
Route::post('past_order_for_admin', [OrderController::class, 'past_order_for_admin']);

Route::post('successPayment', [RazorpayController::class, 'successPayment']);
Route::post('FailPayment', [RazorpayController::class, 'FailPayment']);

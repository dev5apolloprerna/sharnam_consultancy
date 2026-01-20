<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UsersApiController;
use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\EmployeeLocationController;
use App\Http\Controllers\Api\EmployeeAttendanceController;


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
Route::post('employee/login', [EmployeeAuthController::class, 'login']);
Route::middleware('auth:api')->group(function () {
    Route::post('customer-profile', [CustomerAuthController::class, 'profile']);
    Route::post('customer-profile/update', [CustomerAuthController::class, 'updateProfile']);
    Route::post('customer/forgot-password', [CustomerPasswordController::class, 'forgot']);
    Route::post('customer/change-password', [CustomerPasswordController::class, 'changePassword']);

});

Route::post('employee/attendance/start', [EmployeeAttendanceController::class, 'startDay']);
Route::post('employee/attendance/end', [EmployeeAttendanceController::class, 'endDay']);

Route::post('employee/location/track', [EmployeeLocationController::class, 'trackLocation']);

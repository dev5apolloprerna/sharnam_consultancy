<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UsersApiController;
use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\EmployeeLocationController;
use App\Http\Controllers\Api\EmployeeAttendanceController;
use App\Http\Controllers\Api\EmployeePasswordController;


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
    Route::post('employee/forgot-password', [EmployeePasswordController::class, 'forgot']);

Route::middleware('auth:api')->group(function () {
    Route::post('employee-profile', [EmployeeAuthController::class, 'profile']);
    Route::post('employee-profile/update', [EmployeeAuthController::class, 'updateProfile']);
    Route::post('employee/change-password', [EmployeePasswordController::class, 'changePassword']);

});

Route::post('employee/attendance/start', [EmployeeAttendanceController::class, 'startDay']);
Route::post('employee/attendance/end', [EmployeeAttendanceController::class, 'endDay']);

Route::post('employee/location/track', [EmployeeLocationController::class, 'trackLocation']);

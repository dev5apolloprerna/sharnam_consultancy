<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UsersApiController;
use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\EmployeeLocationController;
use App\Http\Controllers\Api\EmployeeAttendanceController;
use App\Http\Controllers\Api\EmployeePasswordController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeLeaveController;
use App\Http\Controllers\Api\EmployeeCreditCollectionController;
use App\Http\Controllers\Api\EmployeeLedgerApiController;
use App\Http\Controllers\Api\EmployeeLeaveManagerController;
use App\Http\Controllers\Api\EmployeeSalaryApiController;
use App\Http\Controllers\Api\HolidayController;
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
    Route::post('employee/dashboard', [DashboardController::class, 'dashboard']);
    Route::match(['get', 'post'], 'employee/holidays', [HolidayController::class, 'index']);
    Route::match(['get', 'post'], 'manager/holidays', [HolidayController::class, 'index']);
});

Route::post('employee/attendance/start', [EmployeeAttendanceController::class, 'startDay']);
Route::post('employee/attendance/end', [EmployeeAttendanceController::class, 'endDay']);

Route::post('employee/location/track', [EmployeeLocationController::class, 'trackLocation']);

Route::prefix('employee')->group(function () {
    Route::get('leaves', [EmployeeLeaveController::class, 'index']);
    Route::post('leaves', [EmployeeLeaveController::class, 'store']);
    Route::post('leaves/show', [EmployeeLeaveController::class, 'show']);
    Route::post('/leaves/list', [EmployeeLeaveController::class, 'leaveList']);
    Route::post('leaves/update', [EmployeeLeaveController::class, 'update']);
    Route::post('leaves/delete ', [EmployeeLeaveController::class, 'destroy']);
});


Route::prefix('employee')
    ->middleware(['auth:api'])   // change to auth:sanctum if you use Sanctum
    ->group(function () {
        Route::post('employee-ledger/list', [EmployeeLedgerApiController::class, 'ledgerList']);
        Route::post('employee-ledger/debit', [EmployeeLedgerApiController::class, 'debitExpense']);
        Route::post('/employee-ledger/update', [EmployeeLedgerApiController::class, 'updateLedger']);
        Route::post('/employee-ledger/delete', [EmployeeLedgerApiController::class, 'deleteLedger']);
        Route::post('salary/list', [EmployeeSalaryApiController::class, 'salaryListing']);
    });


Route::prefix('employee')->group(function () {
    Route::post('/credit-list', [EmployeeCreditCollectionController::class, 'index']);
    Route::post('/credit-store', [EmployeeCreditCollectionController::class, 'store']);
    Route::post('/credit-show', [EmployeeCreditCollectionController::class, 'show']);
    Route::post('credit-update', [EmployeeCreditCollectionController::class, 'update']);
    Route::post('credit-delete', [EmployeeCreditCollectionController::class, 'destroy']);

    Route::patch('{credit_id}/active', [EmployeeCreditCollectionController::class, 'toggleActive']);
});


Route::middleware('auth:api')->group(function () {
    Route::post('/manager/employee-leave-list', [EmployeeLeaveManagerController::class, 'managerEmployeeLeaveList']);
    Route::post('/manager/employee-leave-action', [EmployeeLeaveManagerController::class, 'managerEmployeeLeaveAction']);
});

Route::middleware(['auth:api'])->get('employee/salary-slip/pdf', [EmployeeSalaryApiController::class, 'salarySlipPdf'])->name('api.employee.salary-slip.pdf');

/*Route::post('employee/salary/list', [EmployeeSalaryApiController::class, 'salaryList']);
Route::post('employee/salary-slip', [EmployeeSalaryApiController::class, 'salaryPdfDownload']);
*/

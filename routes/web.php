<?php
use App\Http\Controllers\Front\FrontController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\LoginController;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\ConstructionSiteController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\EmployeeCreditController;
use App\Http\Controllers\Admin\EmployeeLeaveController;
use App\Http\Controllers\Admin\SalaryProcessController;
use App\Http\Controllers\Admin\EmployeeLocationHistoryController;
use App\Http\Controllers\Admin\HolidayMasterController;
use App\Http\Controllers\Admin\EmployeeLeaveLedgerController;

Route::fallback(function () {
     return view('errors.404');
});

Route::get('/login', function () {
    return redirect()->route('login');
});


Auth::routes(['register' => false]);

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Profile Routes
Route::prefix('profile')->name('profile.')->middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'getProfile'])->name('detail');
    Route::get('/edit', [HomeController::class, 'EditProfile'])->name('EditProfile');
    Route::post('/update', [HomeController::class, 'updateProfile'])->name('update');
    Route::post('/change-password', [HomeController::class, 'changePassword'])->name('change-password');
});

Route::get('logout', [LoginController::class, 'logout'])->name('logout');

// Roles
Route::resource('roles', App\Http\Controllers\RolesController::class);

// Permissions
Route::resource('permissions', App\Http\Controllers\PermissionsController::class);

// Users
Route::middleware('auth')->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/store', [UserController::class, 'store'])->name('store');
    Route::get('/edit/{id?}', [UserController::class, 'edit'])->name('edit');
    Route::post('/update/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/delete/{user}', [UserController::class, 'delete'])->name('destroy');
    Route::get('/update/status/{user_id}/{status}', [UserController::class, 'updateStatus'])->name('status');
    Route::post('/password-update/{Id?}', [UserController::class, 'passwordupdate'])->name('passwordupdate');
    Route::get('/import-users', [UserController::class, 'importUsers'])->name('import');
    Route::post('/upload-users', [UserController::class, 'uploadUsers'])->name('upload');
    Route::get('export/', [UserController::class, 'export'])->name('export');
});


Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('construction-site/search', [ConstructionSiteController::class, 'search'])->name('construction-site.search');
    Route::resource('construction-site', ConstructionSiteController::class);
    Route::post('construction-site/bulk-delete', [ConstructionSiteController::class, 'bulkDelete'])->name('construction-site.bulk-delete');
    Route::get('construction-site/{site_id}/employees', [ConstructionSiteController::class, 'employees']);
    Route::post('construction-site/assign-employees', [ConstructionSiteController::class, 'assignEmployees']);

Route::get('construction-site/{site}/employee-accessories', [ConstructionSiteController::class, 'employeeAccessoriesPage']);
Route::get('construction-site/{site}/employee-vehicle', [ConstructionSiteController::class, 'employeeVehiclePage']);
Route::post('construction-site/employee-vehicle/save', [ConstructionSiteController::class, 'saveAssignment']);
Route::delete('construction-site/employee-vehicle/delete/{id}', [ConstructionSiteController::class, 'deleteAssignment'])->name('construction-site.assignment.delete');
Route::post('construction-site/change-status', [ConstructionSiteController::class, 'changeStatus'])->name('construction-site.change-status');


});


Route::prefix('admin')->name('admin.')->group(function () {
    
    Route::post('employee/search', [EmployeeController::class, 'search'])->name('employee.search');

    Route::resource('employee', EmployeeController::class);
    Route::post('employee/bulk-delete', [EmployeeController::class, 'bulkDelete'])->name('employee.bulk-delete');
    Route::post('employee/changepassword', [EmployeeController::class, 'empchangePassword']);

    Route::get('employee/{id}/vehicle', [EmployeeController::class, 'getVehicle']);
    Route::post('employee/vehicle/save', [EmployeeController::class, 'saveVehicle']);
    Route::post('employee/resign', [EmployeeController::class, 'resign'])->name('employee.resign');


});


Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('vehicle', VehicleController::class);
    Route::post('vehicle/bulk-delete', [VehicleController::class, 'bulkDelete'])->name('vehicle.bulk-delete');  
});



// routes/web.php

Route::prefix('admin')->middleware(['auth'])->group(function () {

    Route::get('accessories', [App\Http\Controllers\Admin\AccessoriesController::class, 'index'])->name('accessories.index');
    Route::post('accessories/store', [App\Http\Controllers\Admin\AccessoriesController::class, 'store'])->name('accessories.store');
    Route::get('accessories/edit/{id}', [App\Http\Controllers\Admin\AccessoriesController::class, 'edit'])->name('accessories.edit');
    Route::post('accessories/update/{id}', [App\Http\Controllers\Admin\AccessoriesController::class, 'update'])->name('accessories.update');
    Route::delete('accessories/delete/{id}', [App\Http\Controllers\Admin\AccessoriesController::class, 'destroy'])->name('accessories.delete');

    Route::post('accessories/bulk-delete', [App\Http\Controllers\Admin\AccessoriesController::class, 'bulkDelete'])
        ->name('accessories.bulkDelete');
});


Route::prefix('admin')->group(function () {

    Route::post(
        'construction-site/accessories/save',
        [App\Http\Controllers\Admin\ProjectAccessoriesController::class, 'store']
    )->name('admin.construction-site.accessories.save');

    Route::delete(
        'construction-site/accessories/delete/{id}',
        [App\Http\Controllers\Admin\ProjectAccessoriesController::class, 'destroy']
    )->name('admin.construction-site.accessories.delete');

});




Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('employee-credit', [EmployeeCreditController::class, 'index'])->name('employee-credit.index'); // optional list
    Route::get('employee-credit/create', [EmployeeCreditController::class, 'create'])->name('employee-credit.create');
    Route::post('employee-credit', [EmployeeCreditController::class, 'store'])->name('employee-credit.store');
});

Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/employee-leave', [EmployeeLeaveController::class, 'index'])->name('admin.employee_leave.index');
    Route::post('/employee-leave/status', [EmployeeLeaveController::class, 'updateStatus'])->name('admin.employee_leave.status');
});

Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('employee-leave-ledger', [EmployeeLeaveLedgerController::class, 'index'])->name('employee-leave-ledger.index');
    Route::post('employee-leave-ledger/manual-adjustment', [EmployeeLeaveLedgerController::class, 'manualAdjustment'])->name('employee-leave-ledger.manual-adjustment');
    Route::post('employee-leave-ledger/monthly-credit', [EmployeeLeaveLedgerController::class, 'monthlyCredit'])->name('employee-leave-ledger.monthly-credit');
});


Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('salary-process', [SalaryProcessController::class, 'index'])->name('salary-process.index');
    Route::post('salary-process', [SalaryProcessController::class, 'store'])->name('salary-process.store');
    Route::get('salary-process/{salaryId}/slip', [SalaryProcessController::class, 'downloadSlip'])->name('salary-process.slip');
});


Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('holiday-master', [HolidayMasterController::class, 'index'])->name('holiday-master.index');
    Route::post('holiday-master', [HolidayMasterController::class, 'store'])->name('holiday-master.store');
    Route::put('holiday-master/{holidayId}', [HolidayMasterController::class, 'update'])->name('holiday-master.update');
    Route::delete('holiday-master/{holidayId}', [HolidayMasterController::class, 'destroy'])->name('holiday-master.delete');
});

Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('employee-location-history', [EmployeeLocationHistoryController::class, 'index'])
        ->name('employee-location-history.index');
});
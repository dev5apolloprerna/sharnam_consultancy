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


Route::get('construction-site/{site}/employee-vehicle', [ConstructionSiteController::class, 'employeeVehiclePage']);
Route::post('construction-site/employee-vehicle/save', [ConstructionSiteController::class, 'saveAssignment']);
Route::delete('construction-site/employee-vehicle/delete/{id}', [ConstructionSiteController::class, 'deleteAssignment'])->name('construction-site.assignment.delete');


});


Route::prefix('admin')->name('admin.')->group(function () {
    
    Route::post('employee/search', [EmployeeController::class, 'search'])->name('employee.search');

    Route::resource('employee', EmployeeController::class);
    Route::post('employee/bulk-delete', [EmployeeController::class, 'bulkDelete'])->name('employee.bulk-delete');
    Route::post('employee/changepassword', [EmployeeController::class, 'empchangePassword']);

    Route::get('employee/{id}/vehicle', [EmployeeController::class, 'getVehicle']);
    Route::post('employee/vehicle/save', [EmployeeController::class, 'saveVehicle']);


});


Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('vehicle', VehicleController::class);
    Route::post('vehicle/bulk-delete', [VehicleController::class, 'bulkDelete'])->name('vehicle.bulk-delete');  
});




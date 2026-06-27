<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class EmployeeMaster extends Authenticatable implements JWTSubject
{
    protected $table = 'employee_master';
    protected $primaryKey = 'employee_id';

    public $timestamps = true;

    protected $fillable = [
        'member_id',
        'employee_name',
        'employee_phone',
        'employee_email',
        'employee_address',
        'basic_salary',
        'profile_image',
        'joining_date',
        'designation',
        'password',
        'must_reset_password',
        'temp_password_set_at',
        'device_token',
        'iStatus',
        'isDelete',
        'resign_date',
        'last_working_date',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'resign_date' => 'date',
        'last_working_date' => 'date',
        'temp_password_set_at' => 'datetime',
        'iStatus' => 'integer',
        'isDelete' => 'integer',
        'must_reset_password' => 'integer',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function leaves()
    {
        return $this->hasMany(EmployeeLeaveMaster::class, 'employee_id', 'employee_id');
    }

    public function expenses()
    {
        return $this->hasMany(EmployeeCreditDebitHistory::class, 'employee_id', 'employee_id');
    }

    public function siteAssignments()
    {
        return $this->hasMany(SiteAssignEmployee::class, 'site_emp_id', 'employee_id');
    }

    public function managedSites()
    {
        return $this->hasMany(SiteAssignEmployee::class, 'site_emp_id', 'employee_id')
            ->where('is_site_manager', 1)
            ->where('iStatus', 1)
            ->where('isDelete', 0);
    }

    public function notifications()
    {
        return $this->hasMany(EmployeeNotification::class, 'employee_id', 'employee_id');
    }
}
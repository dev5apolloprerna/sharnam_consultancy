<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Notifiable;


class EmployeeMaster extends Authenticatable implements JWTSubject
{
    use Notifiable, CanResetPasswordTrait;

    protected $table = 'employee_master';
    protected $primaryKey = 'employee_id';
    public $timestamps = true;

    protected $hidden = ['password'];


    protected $fillable = [
        'employee_name',
        'employee_phone',
        'employee_email',
        'employee_address',
        'basic_salary',
        'password',
        'vehicle_id',
        'designation',
        'iStatus',
        'isDelete',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
     public function getEmailForPasswordReset()
    {
        return $this->employee_email; // ✅ change this to your column name
    }

    public function vehicle()
    {
        return $this->belongsTo(VehicleMaster::class, 'vehicle_id');
    }
}

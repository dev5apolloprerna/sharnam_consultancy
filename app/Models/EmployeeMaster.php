<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeMaster extends Model
{
    use HasFactory;

    protected $table = 'employee_master';
    protected $primaryKey = 'employee_id';
    public $timestamps = true;

    protected $fillable = [
        'employee_name',
        'employee_phone',
        'employee_email',
        'employee_address',
        'basic_salary',
        'vehicle_id',
        'designation',
        'iStatus',
        'isDelete',
    ];

    public function vehicle()
    {
        return $this->belongsTo(VehicleMaster::class, 'vehicle_id');
    }
}

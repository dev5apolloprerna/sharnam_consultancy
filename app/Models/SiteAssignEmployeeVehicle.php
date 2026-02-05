<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteAssignEmployeeVehicle extends Model
{
    use HasFactory;

    protected $table = 'construction_employee_vehicle';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id', 'construction_id', 'employee_id', 'vehicle_id','iStatus','isDelete',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id','employee_id')->where('isDelete', 0);
    }

}

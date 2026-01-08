<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleMaster extends Model
{
    use HasFactory;

    protected $table = 'vehicle_master';
    protected $primaryKey = 'vehicle_id';
    public $timestamps = false;

    protected $fillable = [
        'vehicle_name',
        'vehicle_no',
        'employee_id',
        'iStatus',
        'isDelete',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id');
    }
}

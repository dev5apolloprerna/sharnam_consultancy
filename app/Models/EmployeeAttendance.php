<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAttendance extends Model
{
    protected $table = 'employee_attendance';
    protected $primaryKey = 'attendence_id';

    public $timestamps = true;

    protected $fillable = [
        'employee_id',
        'site_id',
        'status',
        'start_date_time',
        'end_date_time',
        'start_location',
        'end_location',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'comments',
        'iStatus',
        'isDelete',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'site_id' => 'integer',
        'start_date_time' => 'datetime',
        'end_date_time' => 'datetime',
        'iStatus' => 'integer',
        'isDelete' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id', 'employee_id');
    }

    public function site()
    {
        return $this->belongsTo(ConstructionSiteMaster::class, 'site_id', 'site_id');
    }
}
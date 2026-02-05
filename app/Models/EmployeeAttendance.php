<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAttendance extends Model
{
    protected $table = 'employee_attendance';
    protected $primaryKey = 'attendence_id';
    public $timestamps = true;

    protected $fillable = [
        'attendence_id', 'employee_id', 'status', 'start_date_time', 'end_date_time', 'start_location', 'end_location', 'start_latitude', 'start_longitude', 'end_latitude', 'end_longitude', 'comments'
    ];
}

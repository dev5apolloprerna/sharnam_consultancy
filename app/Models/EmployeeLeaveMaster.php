<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveMaster extends Model
{
    protected $table = 'employee_leave_master';
    protected $primaryKey = 'emp_leave_id';
    public $timestamps = false; // your table has created_at/updated_at but not default behavior

    protected $fillable = [
        'employee_id',
        'leave_date',
        'leave_type',   // F / H
        'comment',
        'iStatus',
        'isDelete',
        'created_at',
        'updated_at',
    ];
}
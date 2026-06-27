<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveMaster extends Model
{
    protected $table = 'employee_leave_master';
    protected $primaryKey = 'emp_leave_id';

    public $timestamps = true;

    protected $fillable = [
        'employee_id',
        'site_id',
        'leave_date',
        'leave_type',
        'comment',
        'status',
        'reason',
        'approved_by',
        'approved_at',
        'iStatus',
        'isDelete',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'site_id' => 'integer',
        'leave_date' => 'date:Y-m-d',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
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

    public function approvedBy()
    {
        return $this->belongsTo(EmployeeMaster::class, 'approved_by', 'employee_id');
    }
}
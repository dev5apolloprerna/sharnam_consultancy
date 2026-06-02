<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveMaster extends Model
{
    protected $table = 'employee_leave_master';
    protected $primaryKey = 'emp_leave_id';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'leave_date',
        'leave_type',
        'comment',
        'status',
        'reason',
        'approved_by',
        'iStatus',
        'isDelete',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id', 'employee_id');
    }
      public function ledgerEntries()
    {
        return $this->hasMany(EmployeeLeaveLedger::class, 'emp_leave_id', 'emp_leave_id');
    }
}
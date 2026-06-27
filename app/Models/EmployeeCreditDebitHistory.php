<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCreditDebitHistory extends Model
{
    protected $table = 'employee_credit_debit_history';
    protected $primaryKey = 'ledger_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'site_id',
        'site_name',
        'credit_balance',
        'debit_balance',
        'comment',
        'date',
        'enter_by',
        'status',
        'reason',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'site_id' => 'integer',
        'credit_balance' => 'decimal:2',
        'debit_balance' => 'decimal:2',
        'date' => 'date:Y-m-d',
        'enter_by' => 'integer',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id', 'employee_id');
    }

    public function site()
    {
        return $this->belongsTo(ConstructionSiteMaster::class, 'site_id', 'site_id');
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'enter_by', 'id');
    }

    public function enteredByEmployee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'enter_by', 'employee_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(EmployeeMaster::class, 'approved_by', 'employee_id');
    }
}
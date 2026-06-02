<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveLedger extends Model
{
    protected $table = 'employee_leave_ledgers';
    protected $primaryKey = 'leave_ledger_id';

    public const TYPE_MONTHLY_CREDIT = 'monthly_credit';
    public const TYPE_LEAVE_DEBIT = 'leave_debit';
    public const TYPE_MANUAL_CREDIT = 'manual_credit';
    public const TYPE_MANUAL_DEBIT = 'manual_debit';
    public const DEFAULT_MONTHLY_CREDIT = 2.0;

    protected $fillable = [
        'employee_id',
        'emp_leave_id',
        'entry_type',
        'leave_month',
        'leave_year',
        'transaction_date',
        'opening_balance',
        'credit_units',
        'debit_units',
        'closing_balance',
        'reference',
        'description',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'opening_balance' => 'float',
        'credit_units' => 'float',
        'debit_units' => 'float',
        'closing_balance' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id', 'employee_id');
    }

    public function leave()
    {
        return $this->belongsTo(EmployeeLeaveMaster::class, 'emp_leave_id', 'emp_leave_id');
    }
}

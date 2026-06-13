<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
class EmployeeCreditDebitHistory extends Model
{
    protected $table = 'employee_credit_debit_history';
    protected $primaryKey = 'ledger_id';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'site_id',
        'credit_balance',
        'debit_balance',
        'comment',
        'date',
        'enter_by',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id', 'employee_id');
    }
     public function enteredBy()
    {
        return $this->belongsTo(User::class, 'enter_by', 'id');
    }

    public function enteredByEmployee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'enter_by', 'employee_id');
    }
}
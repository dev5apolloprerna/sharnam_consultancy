<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryPayment extends Model
{
    protected $table = 'employee_salary_payments';

    protected $fillable = [
        'employee_id',
        'salary_month',
        'salary_year',
        'amount',
        'deduct_amount',
        'leave_deduct_amount',
        'paid_amount',
        'paid_date',
    ];

    protected $casts = [
        'paid_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id', 'employee_id');
    }
}

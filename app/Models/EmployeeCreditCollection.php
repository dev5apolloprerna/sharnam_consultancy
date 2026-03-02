<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCreditCollection extends Model
{
    protected $table = 'employee_credit_collection';
    protected $primaryKey = 'credit_id';
    public $timestamps = false; // set true if you add timestamps

    protected $fillable = [
        'employee_id',
        'given_by',
        'credit_amount',
        'date',
        'isActive',
    ];

    protected $casts = [
        'credit_amount' => 'decimal:2',
        'date' => 'date:Y-m-d',
        'isActive' => 'integer',
        'employee_id' => 'integer',
        'given_by' => 'integer',
    ];
}
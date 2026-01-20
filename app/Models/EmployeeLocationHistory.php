<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLocationHistory extends Model
{
    use HasFactory;

    protected $table = 'employee_location_history';
    protected $primaryKey = 'location_id';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'latitude',
        'longitude',
        'comments',
        'iStatus',
        'isDelete',
        'created_at'
    ];
}

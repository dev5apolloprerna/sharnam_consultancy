<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HolidayMaster extends Model
{
    use HasFactory;

    protected $table = 'holiday_master';
    protected $primaryKey = 'holiday_id';

    protected $fillable = [
        'holiday_name',
        'holiday_date',
        'description',
        'iStatus',
        'isDelete',
    ];
}

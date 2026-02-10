<?php
// app/Models/Accessories.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accessories extends Model
{
    use HasFactory;

    protected $table = 'accessories_master';
    protected $primaryKey = 'accessories_id';

    // ❌ Disable Laravel timestamps
    public $timestamps = false;

    protected $fillable = [
        'accessories_name',
    ];
}

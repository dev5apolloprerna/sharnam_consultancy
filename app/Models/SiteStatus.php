<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteStatus extends Model
{
    use HasFactory;

    protected $table = 'site_status';
    protected $primaryKey = 'site_status_id';
    public $timestamps = true;

    protected $fillable = [
        'site_status_id',
        'site_status',
        'iStatus',
        'isDelete',
    ];
}

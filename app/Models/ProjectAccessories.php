<?php
// app/Models/ProjectAccessories.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAccessories extends Model
{
    protected $table = 'project_accessories';

    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'accessories_id',
        'qty',
        'date',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConstructionSiteMaster extends Model
{
    use HasFactory;

    protected $table = 'construction_site_master';
    protected $primaryKey = 'site_id';
    public $timestamps = true;

    protected $fillable = [
        'site_name',
        'site_address',
        'site_pincode',
        'site_radious_distance',
        'site_status_id',
        'iStatus',
        'isDelete',
    ];

    public function assignedEmployees()
    {
        return $this->hasMany(SiteAssignEmployee::class, 'site_id')->where('isDelete', 0);
    }

}

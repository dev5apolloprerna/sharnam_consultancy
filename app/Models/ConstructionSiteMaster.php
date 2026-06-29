<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConstructionSiteMaster extends Model
{
    protected $table = 'construction_site_master';
    protected $primaryKey = 'site_id';

    public $timestamps = true;

    protected $fillable = [
        'site_name',
        'site_address',
        'site_pincode',
        'site_radious_distance',
        'latitude',
        'longitude',
        'site_status_id',
        'iStatus',
        'isDelete',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'site_pincode' => 'integer',
        'site_status_id' => 'integer',
        'iStatus' => 'integer',
        'isDelete' => 'integer',
    ];

public function siteStatus()
    {
        return $this->belongsTo(SiteStatus::class, 'site_status_id', 'site_status_id');
    }


    public function assignedEmployees()
    {
        return $this->hasMany(SiteAssignEmployee::class, 'site_id', 'site_id');
    }

    public function managers()
    {
        return $this->hasMany(SiteAssignEmployee::class, 'site_id', 'site_id')
            ->where('is_site_manager', 1)
            ->where('iStatus', 1)
            ->where('isDelete', 0);
    }

    public function leaves()
    {
        return $this->hasMany(EmployeeLeaveMaster::class, 'site_id', 'site_id');
    }

    public function expenses()
    {
        return $this->hasMany(EmployeeCreditDebitHistory::class, 'site_id', 'site_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAssignEmployee extends Model
{
    protected $table = 'site_assign_employees';
    protected $primaryKey = 'assign_id';

    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'site_emp_id',
        'is_site_manager',
        'iStatus',
        'isDelete',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'site_emp_id' => 'integer',
        'is_site_manager' => 'integer',
        'iStatus' => 'integer',
        'isDelete' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'site_emp_id', 'employee_id');
    }

    public function site()
    {
        return $this->belongsTo(ConstructionSiteMaster::class, 'site_id', 'site_id');
    }

    public function scopeActive($query)
    {
        return $query->where('iStatus', 1)->where('isDelete', 0);
    }

    public function scopeManagers($query)
    {
        return $query->active()->where('is_site_manager', 1);
    }
}
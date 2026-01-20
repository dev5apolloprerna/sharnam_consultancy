<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteAssignEmployee extends Model
{
    use HasFactory;

    protected $table = 'site_assign_employees';
    protected $primaryKey = 'assign_id';
    public $timestamps = false;

    protected $fillable = [
        'assign_id',
        'site_id',
        'site_emp_id',
        'iStatus',
        'isDelete',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'site_emp_id','employee_id')->where('isDelete', 0);
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeNotification extends Model
{
    protected $table = 'employee_notifications';
    protected $primaryKey = 'notification_id';

    public $timestamps = true;

    protected $fillable = [
        'employee_id',
        'type',
        'title',
        'message',
        'reference_table',
        'reference_id',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'reference_id' => 'integer',
        'is_read' => 'integer',
        'read_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id', 'employee_id');
    }
}
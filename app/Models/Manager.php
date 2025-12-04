<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Manager extends Model
{
    protected $fillable = [
        'full_name', 
        'email', 
        'employee_id', 
        'department_id'
    ];

    // Un manager appartient à un employé (via employee_id)
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // Un manager appartient à un département (via department_id)
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends Model
{
    protected $fillable = [
        'first_name', 
        'last_name', 
        'email', 
        'phone', 
        'contract_type', 
        'hire_date', 
        'salary_base', 
        'department_id'
    ];

    
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

   
    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'employee_role');
    }

    
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }
}
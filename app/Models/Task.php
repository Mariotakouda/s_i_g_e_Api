<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'title', 
        'description', 
        'status', 
        'due_date', 
        'employee_id'
    ];

    
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
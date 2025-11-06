<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = [
        'type', 
        'start_date', 
        'end_date', 
        'status', 
        'message', 
        'employee_id'
    ];

    
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
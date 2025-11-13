<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    protected $fillable = [
        'date',
        'check_in', 
        'check_out', 
        'total_hours', 
        'employee_id'
    ];

    
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
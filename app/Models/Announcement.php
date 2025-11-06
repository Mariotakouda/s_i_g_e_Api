<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'title', 
        'message', 
        'employee_id'
    ];

    
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
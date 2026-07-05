<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['hostel_id', 'name', 'code', 'location', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

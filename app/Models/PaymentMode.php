<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentMode extends Model
{
    use BelongsToHostel;

    protected $fillable = [
        'hostel_id',
        'name',
        'code',
        'requires_reference',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_reference' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Active modes for the current tenant as code => [name, requires_reference].
     */
    public static function options(): \Illuminate\Support\Collection
    {
        return static::active()->ordered()->get();
    }

    public static function makeCode(string $name): string
    {
        return Str::slug($name, '_') ?: 'mode_'.Str::random(4);
    }
}

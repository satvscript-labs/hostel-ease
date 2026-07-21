<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model an opaque, non-enumerable public identifier for URLs, WITHOUT
 * touching its primary key.
 *
 *  - Adds nothing to the schema by itself — pair it with a migration that adds a
 *    unique `public_id char(26)` column and backfills existing rows (see
 *    database/migrations/*_add_public_id_to_*). PKs and every foreign key stay
 *    integer; only the *route key* changes.
 *  - `getRouteKeyName()` ⇒ implicit route-model binding (`Student $student`)
 *    resolves by `public_id`, and `route('...', $model)` GENERATES a public_id
 *    URL. So call-sites must pass the MODEL, never `$model->id`.
 *  - A fresh ULID is generated on create only when unset, so factories, seeders
 *    and importers get one for free, and the migration backfill (which sets the
 *    value explicitly) is respected.
 *
 * Why ULID and not Laravel's HasUlids: HasUlids makes the ULID the PRIMARY key,
 * which would cascade into every FK. This trait is purely additive — a secondary
 * unique id. To eliminate the (negligible) creation-time leak a ULID carries,
 * swap Str::ulid() for Str::uuid() here; nothing else changes.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}

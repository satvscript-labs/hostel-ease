<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Central audit-log writer used by middleware, controllers and services.
 */
class ActivityLogger
{
    public function log(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        array $properties = [],
    ): ActivityLog {
        $user = Auth::user();

        return ActivityLog::create([
            // Log against the active branch when one is bound, else the user's primary.
            'hostel_id' => \App\Support\Tenant::id() ?? $user?->hostel_id,
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}

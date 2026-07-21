<?php

namespace App\Enums\Presence;

/**
 * Where a person currently is — and, on a punch, the direction the punch
 * expresses. A punch with direction In produces state In; the values are the
 * same domain, so one enum serves both (punch.direction and profile.state).
 *
 * Unknown is a first-class, honest state: never punched since enrolling,
 * post-debounce ambiguity, or after an admin reset (01 §4).
 */
enum PresenceState: string
{
    case In = 'in';
    case Out = 'out';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Inside',
            self::Out => 'Out',
            self::Unknown => 'Unknown',
        };
    }

    /** The other side — drives toggle-mode direction (in ⇄ out). */
    public function opposite(): self
    {
        return match ($this) {
            self::In => self::Out,
            self::Out => self::In,
            self::Unknown => self::Unknown,
        };
    }

    public function isKnown(): bool
    {
        return $this !== self::Unknown;
    }

    /** Semantic colour token (matches the design-system palette). */
    public function color(): string
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'warning',
            self::Unknown => 'secondary',
        };
    }
}

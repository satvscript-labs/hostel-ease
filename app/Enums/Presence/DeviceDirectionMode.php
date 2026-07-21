<?php

namespace App\Enums\Presence;

/**
 * How a device decides a punch's direction (owner Q2 = support BOTH, 01 §4):
 *
 *  - Entry / Exit: a fixed-direction unit (two-unit gate, or a unit whose
 *    InOutMode encodes direction). Every punch on it means the same thing.
 *  - Toggle: one unit serving both ways; each punch flips the person's state.
 */
enum DeviceDirectionMode: string
{
    case Entry = 'entry';
    case Exit = 'exit';
    case Toggle = 'toggle';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entry only',
            self::Exit => 'Exit only',
            self::Toggle => 'Toggle (both ways)',
        };
    }

    /** The fixed direction a punch on this device means, or null for toggle. */
    public function fixedDirection(): ?PresenceState
    {
        return match ($this) {
            self::Entry => PresenceState::In,
            self::Exit => PresenceState::Out,
            self::Toggle => null,
        };
    }
}

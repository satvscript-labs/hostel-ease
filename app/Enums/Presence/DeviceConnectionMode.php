<?php

namespace App\Enums\Presence;

/**
 * How the Connector and a device establish their session (01 §5, 10 §4).
 *
 *  - Tcp: we dial the device at its IP (Dahua `LoginWithHighLevelSecurity`,
 *    EM_LOGIN_SPAC_CAP_TYPE.TCP). Needs a reachable address — fine on a LAN or
 *    over a VPN.
 *  - AutoRegister: the DEVICE dials the Connector (`CLIENT_ListenServer` +
 *    login with SERVER_CONN). Because the connection is outbound from the
 *    hostel, no static IP and no port-forwarding are needed there — this is the
 *    production answer for multi-branch.
 */
enum DeviceConnectionMode: string
{
    case Tcp = 'tcp';
    case AutoRegister = 'auto_register';

    public function label(): string
    {
        return match ($this) {
            self::Tcp => 'We connect to the device',
            self::AutoRegister => 'Device connects to us',
        };
    }

    /** One-line explanation for the device form (02 §6.1a). */
    public function hint(): string
    {
        return match ($this) {
            self::Tcp => 'The device has a fixed address on this network.',
            self::AutoRegister => 'Best for a remote branch — no static IP needed there.',
        };
    }

    /** Auto-registering devices tell US their address; we don't dial them. */
    public function needsAddress(): bool
    {
        return $this === self::Tcp;
    }
}

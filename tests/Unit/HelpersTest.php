<?php

namespace Tests\Unit;

use Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_phone_normalises_to_plus91(): void
    {
        $this->assertSame('+919876543210', hsms_phone('9876543210'));
        $this->assertSame('+919876543210', hsms_phone('+91 98765 43210'));
        $this->assertSame('+919876543210', hsms_phone('098765-43210'));
        $this->assertNull(hsms_phone(null));
    }

    public function test_money_formats_indian_rupees(): void
    {
        $this->assertSame('₹5,000.00', hsms_money(5000));
        $this->assertSame('₹0.00', hsms_money(null));
    }

    public function test_whatsapp_link_builds_wa_me_url(): void
    {
        $this->assertSame('https://wa.me/919876543210', hsms_whatsapp_link('9876543210'));
        $this->assertStringContainsString('text=Hi', hsms_whatsapp_link('9876543210', 'Hi'));
        $this->assertNull(hsms_whatsapp_link(null));
    }
}

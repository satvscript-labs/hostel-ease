<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleTest extends TestCase
{
    public function test_switching_locale_stores_it_in_session(): void
    {
        $this->get(route('locale.switch', 'hi'))->assertRedirect();
        $this->assertSame('hi', session('locale'));
    }

    public function test_unknown_locale_is_ignored(): void
    {
        $this->get(route('locale.switch', 'xx'));
        $this->assertNull(session('locale'));
    }

    public function test_login_page_renders_in_hindi_when_selected(): void
    {
        // Pick Hindi, then load the login page in the same session.
        $this->get(route('locale.switch', 'hi'));
        $this->get(route('login'))->assertOk()->assertSee('मोबाइल नंबर');
    }
}

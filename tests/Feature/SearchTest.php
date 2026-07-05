<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_hostel_admin_finds_students_by_name_and_mobile_within_tenant(): void
    {
        $hostel = Hostel::factory()->create();
        $other = Hostel::factory()->create();
        $admin = User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($hostel->id);

        Student::create(['hostel_id' => $hostel->id, 'name' => 'Amit Shah', 'mobile' => '9876543210',
            'occupation_type' => 'student', 'status' => 'active']);
        Student::create(['hostel_id' => $other->id, 'name' => 'Amit Other', 'mobile' => '9000000000',
            'occupation_type' => 'student', 'status' => 'active']);

        $byName = $this->actingAs($admin)->getJson(route('search', ['q' => 'Amit']))->json('results');
        $this->assertCount(1, $byName);
        $this->assertSame('Amit Shah', $byName[0]['label']);

        $byMobile = $this->actingAs($admin)->getJson(route('search', ['q' => '98765']))->json('results');
        $this->assertSame('Amit Shah', $byMobile[0]['label']);
    }

    public function test_short_queries_return_no_results(): void
    {
        $hostel = Hostel::factory()->create();
        $admin = User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($hostel->id);

        $this->actingAs($admin)->getJson(route('search', ['q' => 'a']))
            ->assertOk()->assertJson(['results' => []]);
    }

    public function test_super_admin_searches_hostels(): void
    {
        $super = User::factory()->superAdmin()->create();
        Hostel::factory()->create(['name' => 'Sunrise Boys Hostel']);

        $results = $this->actingAs($super)->getJson(route('search', ['q' => 'Sunrise']))->json('results');
        $this->assertSame('Hostels', $results[0]['group']);
    }
}

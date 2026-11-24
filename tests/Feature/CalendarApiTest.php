<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_uses_real_fields_and_owner(): void
    {
        $manager = $this->createPrincipalAdmin(['role' => 'admin', 'api_token' => 'admin-token']);
        $headers = ['Authorization' => 'Bearer admin-token'];
        $response = $this->postJson('/api/v1/calendars', ['name' => 'Reunión', 'due_at' => '2026-10-15 09:00:00', 'description' => 'Equipo'], $headers)
            ->assertCreated()->assertJsonPath('calendar.name', 'Reunión')->assertJsonPath('calendar.user_id', $manager->id);
        $this->getJson('/api/v1/calendars/'.$response->json('calendar.id'), $headers)->assertOk()->assertJsonPath('name', 'Reunión');
        $this->getJson('/api/v1/calendars?date_from=2026-10-16', $headers)->assertOk()->assertExactJson([]);
        $this->patchJson('/api/v1/calendars/'.$response->json('calendar.id'), ['name' => 'Reunión actualizada'], $headers)
            ->assertOk()->assertJsonPath('calendar.name', 'Reunión actualizada');
    }

    public function test_employee_only_sees_assigned_events_and_cannot_create(): void
    {
        $employee = User::factory()->create(['api_token' => 'employee']);
        $own = Calendar::factory()->create(['user_id' => $employee->id]);
        $other = Calendar::factory()->create(['user_id' => User::factory()]);
        $headers = ['Authorization' => 'Bearer employee'];
        $this->getJson('/api/v1/calendars', $headers)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $own->id);
        $this->getJson('/api/v1/calendars/'.$other->id, $headers)->assertForbidden();
        $this->postJson('/api/v1/calendars', ['name' => 'Evento', 'due_at' => now()->toIso8601String()], $headers)->assertForbidden();
    }
}

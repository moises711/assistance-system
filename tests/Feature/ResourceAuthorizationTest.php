<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_routes_deny_unprivileged_writes(): void
    {
        User::factory()->create(['api_token' => 'employee']);
        foreach (['activities', 'attendances', 'calendars', 'departaments', 'hours', 'reports', 'schedules'] as $resource) {
            $this->postJson('/api/v1/'.$resource, [], ['Authorization' => 'Bearer employee'])->assertForbidden();
        }
    }

    public function test_activity_crud_returns_valid_json_and_is_scoped(): void
    {
        $admin = $this->createPrincipalAdmin(['role' => 'admin', 'api_token' => 'admin']);
        $headers = ['Authorization' => 'Bearer admin'];
        $response = $this->postJson('/api/v1/activities', [
            'user_id' => $admin->id, 'type' => 'work', 'name' => 'Inventario', 'occurred_at' => now()->toIso8601String(),
        ], $headers)->assertCreated();
        $id = $response->json('id');
        $this->getJson('/api/v1/activities', $headers)->assertOk()->assertJsonCount(1);
        $this->getJson('/api/v1/activities/'.$id, $headers)->assertOk()->assertJsonPath('name', 'Inventario');
        $this->patchJson('/api/v1/activities/'.$id, ['name' => 'Revisión'], $headers)->assertOk();
        User::factory()->create(['api_token' => 'other']);
        $this->getJson('/api/v1/activities/'.$id, ['Authorization' => 'Bearer other'])->assertForbidden();
        $this->deleteJson('/api/v1/activities/'.$id, [], $headers)->assertOk()->assertJsonPath('message', 'Actividad eliminada correctamente');
        $this->assertDatabaseCount('activities', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Pages\DashboardEmpleado;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Filament\Widgets\FullCalendarWidget;
use App\Models\Attendance;
use App\Models\Calendar;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WebAttendanceCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_widget_creates_event_and_dispatches_refresh(): void
    {
        $user = $this->createPrincipalAdmin(['role' => 'admin']);
        $this->actingAs($user);
        Livewire::test(FullCalendarWidget::class)
            ->callAction('createEvent', data: ['name' => 'Reunión web', 'due_at' => '2026-10-15 09:00:00', 'description' => 'Equipo'])
            ->assertHasNoErrors()->assertDispatched('calendar-updated');
        $this->assertDatabaseHas('calendars', ['name' => 'Reunión web', 'user_id' => $user->id]);
    }

    public function test_widget_events_are_scoped_and_read_only_for_employee(): void
    {
        $user = User::factory()->create();
        Calendar::factory()->create(['user_id' => $user->id]);
        Calendar::factory()->create(['user_id' => User::factory()]);
        $this->actingAs($user);
        $widget = Livewire::test(FullCalendarWidget::class)->instance();
        $this->assertCount(1, $widget->getEvents());
        $this->assertFalse($widget->getEvents()[0]['extendedProps']['canEdit']);
        $this->assertFalse($widget->canCreateEvents());
    }

    public function test_web_activity_is_required_at_exit_and_entry_is_free(): void
    {
        $user = $this->createPrincipalAdmin(['role' => 'admin', 'require_activity_report' => true]);
        $this->actingAs($user);
        $this->assignAttendanceSite($user);
        Livewire::test(DashboardEmpleado::class)->callAction('entrada', data: ['location' => $this->attendancePosition()])->assertHasNoErrors();
        $this->assertDatabaseCount('attendances', 1);
        Livewire::test(DashboardEmpleado::class)
            ->callAction('salida', data: ['location' => $this->attendancePosition(), 'notes' => ''])
            ->assertHasActionErrors(['notes' => 'required']);
        $this->assertNull(Attendance::firstOrFail()->check_out_at);
        Livewire::test(DashboardEmpleado::class)
            ->callAction('salida', data: ['location' => $this->attendancePosition(), 'notes' => 'Trabajo completado'])->assertHasNoErrors();
        $this->assertNotNull(Attendance::firstOrFail()->check_out_at);
    }

    public function test_attendance_export_keeps_table_filters_and_user_scope(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:Attendance', 'web'));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $own = Attendance::factory()->create(['user_id' => $user->id]);
        $other = Attendance::factory()->create();
        $test = Livewire::test(ListAttendances::class);
        $test->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
        $action = $test->instance()->getTable()->getAction('export');
        $export = (fn () => $this->exports->first())->call($action);
        $export->hydrate(livewire: $test->instance());
        $this->assertSame([$own->id], $export->getQuery()->pluck('attendances.id')->all());
        $this->assertArrayHasKey('daily_activity', $export->getColumns());
        $test->filterTable('status', 'ausente');
        $action = $test->instance()->getTable()->getAction('export');
        $filteredExport = (fn () => $this->exports->first())->call($action);
        $filteredExport->hydrate(livewire: $test->instance());
        $this->assertSame([], $filteredExport->getQuery()->pluck('attendances.id')->all());
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceController extends ApiController
{
    public function __construct(private AttendanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'date_from' => 'nullable|date', 'date_to' => 'nullable|date|after_or_equal:date_from',
            'scope' => 'nullable|in:mine,all', 'user_id' => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:departaments,id', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $query = Attendance::query();
        if (($filters['scope'] ?? 'mine') === 'all') {
            abort_unless($request->user()->isManager(), 403);
            Gate::authorize('viewAny', Attendance::class);
            $request->user()->applyTeamScopeTo($query, 'attendances.user_id');
        } else {
            $query->where('user_id', $request->user()->id);
        }
        $query->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->with('user:id,name', 'department:id,name')->orderByDesc('date');

        return response()->json(isset($filters['per_page']) ? $query->paginate($filters['per_page']) : $query->get());
    }

    public function today(Request $request): JsonResponse
    {
        return response()->json($this->service->today($request->user()));
    }

    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate(['notes' => 'nullable|string|max:10000', 'location' => 'required|array']);

        return response()->json(['message' => 'Entrada registrada correctamente',
            'attendance' => $this->service->checkIn($request->user(), notes: $data['notes'] ?? null, location: $data['location'])], 201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $data = $request->validate(['notes' => 'nullable|string|max:10000', 'location' => 'required|array']);

        return response()->json(['message' => 'Salida registrada correctamente', 'attendance' => $this->service->checkOut($request->user(), notes: $data['notes'] ?? null, location: $data['location'])]);
    }

    public function startLunch(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Inicio de almuerzo registrado', 'attendance' => $this->service->startLunch($request->user())]);
    }

    public function endLunch(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Fin de almuerzo registrado', 'attendance' => $this->service->endLunch($request->user())]);
    }

    public function scanQr(Request $request): JsonResponse
    {
        $data = $request->validate(['qr_data' => 'required|string', 'notes' => 'nullable|string|max:10000', 'action' => 'nullable|in:check_in,check_out']);
        if (! hash_equals((string) config('app.attendance_qr_key'), $data['qr_data'])) {
            throw ValidationException::withMessages(['qr_data' => 'El código QR no pertenece a una sede válida.']);
        }
        $result = $this->service->scan($request->user(), $data['notes'] ?? null, $data['action'] ?? null);

        return response()->json(['message' => $result['action'] === 'check_in' ? 'Entrada registrada correctamente' : 'Salida registrada correctamente', ...$result],
            $result['action'] === 'check_in' ? 201 : 200);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Attendance::class);
        $data = $this->validatedRecord($request);
        $this->authorizeOwner($request, (int) $data['user_id']);
        $data['department_id'] = User::query()->whereKey($data['user_id'])->value('department_id');
        $attendance = DB::transaction(function () use ($data): Attendance {
            User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            $record = new Attendance($data);
            $this->calculateRecord($record);
            $record->save();

            return $record;
        });

        return response()->json(['message' => 'Asistencia creada correctamente', 'attendance' => $attendance], 201);
    }

    public function show(Attendance $attendance): JsonResponse
    {
        Gate::authorize('view', $attendance);

        return response()->json($attendance->load('user:id,name', 'department:id,name'));
    }

    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        Gate::authorize('update', $attendance);
        $data = $this->validatedRecord($request, $attendance);
        $ownerId = (int) ($data['user_id'] ?? $attendance->user_id);
        $this->authorizeOwner($request, $ownerId);
        $data['department_id'] = User::query()->whereKey($ownerId)->value('department_id');
        $attendance->fill($data);
        $this->calculateRecord($attendance);
        $attendance->save();

        return response()->json(['message' => 'Asistencia actualizada correctamente', 'attendance' => $attendance]);
    }

    public function destroy(Attendance $attendance): JsonResponse
    {
        Gate::authorize('delete', $attendance);
        $attendance->delete();

        return response()->json(['message' => 'Asistencia eliminada correctamente']);
    }

    private function validatedRecord(Request $request, ?Attendance $attendance = null): array
    {
        $required = $attendance ? 'sometimes' : 'required';
        $owner = (int) $request->input('user_id', $attendance?->user_id);
        $data = $request->validate([
            'user_id' => "$required|exists:users,id", 'department_id' => 'nullable|exists:departaments,id',
            'date' => [$required, 'date', Rule::unique('attendances', 'date')->where('user_id', $owner)->ignore($attendance?->id)],
            'check_in_at' => 'nullable|date', 'check_out_at' => 'nullable|date',
            'lunch_start_at' => 'nullable|date', 'lunch_end_at' => 'nullable|date',
            'status' => "$required|in:presente,tarde,ausente,pendiente", 'daily_activity' => 'nullable|string|max:10000',
        ]);
        foreach (['check_in_at', 'check_out_at', 'lunch_start_at', 'lunch_end_at'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = Carbon::parse($data[$field])->setTimezone(config('app.timezone'));
            }
        }
        $record = new Attendance($attendance ? $attendance->getAttributes() : []);
        $record->fill($data);
        $this->validateChronology($record);
        if ($record->check_out_at && User::find($owner)?->require_activity_report && trim($record->daily_activity ?? '') === '') {
            throw ValidationException::withMessages(['daily_activity' => 'La actividad es obligatoria para este usuario.']);
        }

        return $data;
    }

    private function validateChronology(Attendance $record): void
    {
        $previous = null;
        foreach (['check_in_at', 'lunch_start_at', 'lunch_end_at', 'check_out_at'] as $field) {
            if ($record->$field && $previous && $record->$field->lessThan($previous)) {
                throw ValidationException::withMessages([$field => 'Las marcas deben seguir el orden de la jornada.']);
            }
            $previous = $record->$field ?? $previous;
        }
        if (($record->lunch_start_at || $record->check_out_at) && ! $record->check_in_at) {
            throw ValidationException::withMessages(['check_in_at' => 'Registra primero la entrada.']);
        }
        if ($record->lunch_end_at && ! $record->lunch_start_at) {
            throw ValidationException::withMessages(['lunch_start_at' => 'Registra primero el inicio de almuerzo.']);
        }
    }

    private function calculateRecord(Attendance $record): void
    {
        $record->unsetRelation('user');
        $record->total_minutes = $this->service->calculateWorkedMinutes($record);
        $record->late_minutes = $this->service->calculateLateMinutes($record);
        if ($record->check_in_at) {
            $record->status = $record->late_minutes > 0 ? 'tarde' : 'presente';
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ScheduleController extends ApiController
{
    /**
     * Listar todos los horarios.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->isManager() && Gate::allows('viewAny', Schedule::class) ? Schedule::all() : Schedule::whereKey($request->user()->schedule_id)->get());
    }

    /**
     * Crear un nuevo horario.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Schedule::class);
        $validated = $request->validate($this->rules());

        $schedule = Schedule::create($validated);

        return response()->json([
            'message' => 'Horario creado correctamente',
            'schedule' => $schedule,
        ], 201);
    }

    /**
     * Mostrar un horario específico.
     */
    public function show(Request $request, Schedule $schedule): JsonResponse
    {
        abort_unless(Gate::allows('view', $schedule) || (int) $request->user()->schedule_id === (int) $schedule->id, 403);

        return response()->json($schedule);
    }

    /**
     * Actualizar un horario existente.
     */
    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        Gate::authorize('update', $schedule);
        $validated = $request->validate($this->rules(true));

        $schedule->update($validated);

        return response()->json([
            'message' => 'Horario actualizado correctamente',
            'schedule' => $schedule,
        ]);
    }

    /**
     * Eliminar un horario.
     */
    public function destroy(Schedule $schedule): JsonResponse
    {
        Gate::authorize('delete', $schedule);
        $schedule->delete();

        return response()->json([
            'message' => 'Horario eliminado correctamente',
        ], 200);
    }

    private function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'name' => "$required|string|max:255", 'type' => "$required|in:fijo,flexible,rotativo,nocturno",
            'start_time' => "$required|date_format:H:i", 'end_time' => "$required|date_format:H:i",
            'tolerance_minutes' => 'sometimes|integer|min:0|max:1440',
            'maximum_late_minutes' => 'sometimes|integer|min:0|max:1440',
            'allows_late_entry' => 'sometimes|boolean', 'working_days' => 'sometimes|array',
            'working_days.*' => 'integer|between:1,7|distinct', 'is_active' => 'sometimes|boolean',
        ];
    }
}

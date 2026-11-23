<?php

namespace App\Http\Controllers\Api;

use App\Models\Activity;
use App\Models\Hour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HourController extends ApiController
{
    /**
     * Listar todos los registros de horas.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->visibleQuery($request, Hour::class)->with('user:id,name')->latest()->get());
    }

    /**
     * Crear un nuevo registro de horas.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Hour::class);
        $validated = $request->validate([
            'activity_id' => 'required|exists:activities,id',
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after_or_equal:start_time',
        ]);

        $this->authorizeOwner($request, (int) $validated['user_id']);
        abort_unless((int) Activity::findOrFail($validated['activity_id'])->user_id === (int) $validated['user_id'], 422, 'La actividad debe pertenecer al usuario.');
        $hour = Hour::create($validated);

        return response()->json([
            'message' => 'Registro de horas creado correctamente',
            'hour' => $hour,
        ], 201);
    }

    /**
     * Mostrar un registro de horas específico.
     */
    public function show(Hour $hour): JsonResponse
    {
        Gate::authorize('view', $hour);

        return response()->json($hour);
    }

    /**
     * Actualizar un registro de horas existente.
     */
    public function update(Request $request, Hour $hour): JsonResponse
    {
        Gate::authorize('update', $hour);
        $validated = $request->validate([
            'activity_id' => 'sometimes|exists:activities,id',
            'user_id' => 'sometimes|exists:users,id',
            'date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
        ]);

        $this->authorizeOwner($request, (int) ($validated['user_id'] ?? $hour->user_id));
        abort_unless((int) Activity::findOrFail($validated['activity_id'] ?? $hour->activity_id)->user_id === (int) ($validated['user_id'] ?? $hour->user_id), 422, 'La actividad debe pertenecer al usuario.');
        $start = $validated['start_time'] ?? $hour->start_time->format('H:i');
        $end = $validated['end_time'] ?? $hour->end_time->format('H:i');
        if ($end < $start) {
            throw ValidationException::withMessages(['end_time' => 'La hora de fin debe ser posterior al inicio.']);
        }
        $hour->update($validated);

        return response()->json([
            'message' => 'Registro de horas actualizado correctamente',
            'hour' => $hour,
        ]);
    }

    /**
     * Eliminar un registro de horas.
     */
    public function destroy(Hour $hour): JsonResponse
    {
        Gate::authorize('delete', $hour);
        $hour->delete();

        return response()->json([
            'message' => 'Hora eliminada correctamente',
        ], 200);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Listar todos los horarios.
     */
    public function index()
    {
        return response()->json(Schedule::all());
    }

    /**
     * Crear un nuevo horario.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([   
            'user_id'     => 'required|exists:users,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after_or_equal:start_time',
        ]);

        $schedule = Schedule::create($validated);

        return response()->json([
            'message'  => 'Horario creado correctamente',
            'schedule' => $schedule,
        ], 201);
    }

    /**
     * Mostrar un horario específico.
     */
    public function show(Schedule $schedule)
    {
        return response()->json($schedule);
    }

    /**
     * Actualizar un horario existente.
     */
    public function update(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'user_id'     => 'sometimes|exists:users,id',
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_time'  => 'sometimes|date',
            'end_time'    => 'sometimes|date|after_or_equal:start_time',
        ]);

        $schedule->update($validated);

        return response()->json([
            'message'  => 'Horario actualizado correctamente',
            'schedule' => $schedule,
        ]);
    }

    /**
     * Eliminar un horario.
     */
    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return response()->json([
            'message' => 'Horario eliminado correctamente',
        ], 200);
    }
}
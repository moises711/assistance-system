<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * Listar todos los eventos del calendario.
     */
    public function index()
    {
        return response()->json(Calendar::all());
    }

    /**
     * Crear un nuevo evento en el calendario.
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

        $calendar = Calendar::create($validated);

        return response()->json([
            'message'  => 'Evento creado correctamente',
            'calendar' => $calendar,
        ], 201);
    }

    /**
     * Mostrar un evento específico.
     */
    public function show(Calendar $calendar)
    {
        return response()->json($calendar);
    }

    /**
     * Actualizar un evento existente.
     */
    public function update(Request $request, Calendar $calendar)
    {
        $validated = $request->validate([
            'user_id'     => 'sometimes|exists:users,id',
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_time'  => 'sometimes|date',
            'end_time'    => 'sometimes|date|after_or_equal:start_time',
        ]);

        $calendar->update($validated);

        return response()->json([
            'message'  => 'Evento actualizado correctamente',
            'calendar' => $calendar,
        ]);
    }

    /**
     * Eliminar un evento del calendario.
     */
    public function destroy(Calendar $calendar)
    {
        $calendar->delete();

        return response()->json([
            'message' => 'Evento eliminado correctamente',
        ], 200);
    }
}
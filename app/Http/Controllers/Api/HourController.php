<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hour;
use Illuminate\Http\Request;

class HourController extends Controller
{
    /**
     * Listar todos los registros de horas.
     */
    public function index()
    {
        return response()->json(Hour::all());
    }

    /**
     * Crear un nuevo registro de horas.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'activity_id' => 'required|exists:activities,id',
            'user_id'     => 'required|exists:users,id',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after_or_equal:start_time',
        ]);

        $hour = Hour::create($validated);

        return response()->json([
            'message' => 'Registro de horas creado correctamente',
            'hour'    => $hour,
        ], 201);
    }

    /**
     * Mostrar un registro de horas específico.
     */
    public function show(Hour $hour)
    {
        return response()->json($hour);
    }

    /**
     * Actualizar un registro de horas existente.
     */
    public function update(Request $request, Hour $hour)
    {
        $validated = $request->validate([
            'activity_id' => 'sometimes|exists:activities,id',
            'user_id'     => 'sometimes|exists:users,id',
            'start_time'  => 'sometimes|date',
            'end_time'    => 'sometimes|date|after_or_equal:start_time',
        ]);

        $hour->update($validated);

        return response()->json([
            'message' => 'Registro de horas actualizado correctamente',
            'hour'    => $hour,
        ]);
    }

    /**
     * Eliminar un registro de horas.
     */
    public function destroy(Hour $hour)
    {
        $hour->delete();

        return response()->json([
            'message' => 'Hora eliminada correctamente',
        ], 200);
    }
}
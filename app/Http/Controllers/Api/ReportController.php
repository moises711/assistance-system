<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Listar todos los reportes.
     */
    public function index()
    {
        return response()->json(Report::all());
    }

    /**
     * Crear un nuevo reporte.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'title'   => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        $report = Report::create($validated);

        return response()->json([
            'message' => 'Reporte creado correctamente',
            'report'  => $report,
        ], 201);
    }

    /**
     * Mostrar un reporte específico.
     */
    public function show(Report $report)
    {
        return response()->json($report);
    }

    /**
     * Actualizar un reporte existente.
     */
    public function update(Request $request, Report $report)
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'title'   => 'sometimes|string|max:255',
            'content' => 'nullable|string',
        ]);

        $report->update($validated);

        return response()->json([
            'message' => 'Reporte actualizado correctamente',
            'report'  => $report,
        ]);
    }

    /**
     * Eliminar un reporte.
     */
    public function destroy(Report $report)
    {
        $report->delete();

        return response()->json([
            'message' => 'Reporte eliminado correctamente',
        ], 200);
    }
}
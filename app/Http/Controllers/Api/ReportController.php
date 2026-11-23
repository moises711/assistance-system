<?php

namespace App\Http\Controllers\Api;

use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends ApiController
{
    /**
     * Listar todos los reportes.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->visibleQuery($request, Report::class)->with('user:id,name')->withSum('attendances as total_minutes', 'total_minutes')->withSum('attendances as late_minutes', 'late_minutes')->withCount('attendances')->latest()->get());
    }

    /**
     * Crear un nuevo reporte.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Report::class);
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',

        ]);

        $this->authorizeOwner($request, (int) $validated['user_id']);
        $report = Report::create($validated);

        return response()->json([
            'message' => 'Reporte creado correctamente',
            'report' => $report,
        ], 201);
    }

    /**
     * Mostrar un reporte específico.
     */
    public function show(Report $report): JsonResponse
    {
        Gate::authorize('view', $report);

        return response()->json($report->load('user:id,name')->loadSum('attendances', 'total_minutes')->loadSum('attendances', 'late_minutes')->loadCount('attendances'));
    }

    /**
     * Actualizar un reporte existente.
     */
    public function update(Request $request, Report $report): JsonResponse
    {
        Gate::authorize('update', $report);
        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,id',

        ]);

        $this->authorizeOwner($request, (int) ($validated['user_id'] ?? $report->user_id));
        $report->update($validated);

        return response()->json([
            'message' => 'Reporte actualizado correctamente',
            'report' => $report,
        ]);
    }

    /**
     * Eliminar un reporte.
     */
    public function destroy(Report $report): JsonResponse
    {
        Gate::authorize('delete', $report);
        $report->delete();

        return response()->json([
            'message' => 'Reporte eliminado correctamente',
        ], 200);
    }
}

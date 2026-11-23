<?php

namespace App\Http\Controllers\Api;

use App\Models\Departament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DepartamentController extends ApiController
{
    /**
     * Listar todos los departamentos.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->isManager() && Gate::allows('viewAny', Departament::class) ? Departament::all() : Departament::whereKey($request->user()->department_id)->get());
    }

    /**
     * Crear un nuevo departamento.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Departament::class);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $departament = Departament::create($validated);

        return response()->json([
            'message' => 'Departamento creado correctamente',
            'departament' => $departament,
        ], 201);
    }

    /**
     * Mostrar un departamento específico.
     */
    public function show(Request $request, Departament $departament): JsonResponse
    {
        abort_unless(Gate::allows('view', $departament) || (int) $request->user()->department_id === (int) $departament->id, 403);

        return response()->json($departament);
    }

    /**
     * Actualizar un departamento existente.
     */
    public function update(Request $request, Departament $departament): JsonResponse
    {
        Gate::authorize('update', $departament);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $departament->update($validated);

        return response()->json([
            'message' => 'Departamento actualizado correctamente',
            'departament' => $departament,
        ]);
    }

    /**
     * Eliminar un departamento.
     */
    public function destroy(Departament $departament): JsonResponse
    {
        Gate::authorize('delete', $departament);
        $departament->delete();

        return response()->json([
            'message' => 'Departamento eliminado correctamente',
        ], 200);
    }
}

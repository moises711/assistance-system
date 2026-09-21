<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departament;
use Illuminate\Http\Request;

class DepartamentController extends Controller
{
    /**
     * Listar todos los departamentos.
     */
    public function index()
    {
        return response()->json(Departament::all());
    }

    /**
     * Crear un nuevo departamento.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $departament = Departament::create($validated);

        return response()->json([
            'message'     => 'Departamento creado correctamente',
            'departament' => $departament,
        ], 201);
    }

    /**
     * Mostrar un departamento específico.
     */
    public function show(Departament $departament)
    {
        return response()->json($departament);
    }

    /**
     * Actualizar un departamento existente.
     */
    public function update(Request $request, Departament $departament)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $departament->update($validated);

        return response()->json([
            'message'     => 'Departamento actualizado correctamente',
            'departament' => $departament,
        ]);
    }

    /**
     * Eliminar un departamento.
     */
    public function destroy(Departament $departament)
    {
        $departament->delete();

        return response()->json([
            'message' => 'Departamento eliminado correctamente',
        ], 200);
    }
}
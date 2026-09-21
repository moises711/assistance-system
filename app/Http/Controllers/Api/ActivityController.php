<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Activity::Collection(Activity::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type'   => 'required|string|max:255',
            'name' => 'required|string|max:255',        
            'description' => 'nullable|string',
            'occurred_at' => 'required|date',
            'ip_address' => 'nullable|string|max:45',
            'user_agent' => 'nullable|string|max:1000',
            'session_id' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);
        $activity = Activity::create($validated);
        return response()->json($activity)
            ->response()
            ->setStatusCode(201);
    }
    /**
     * Display the specified resource.
     */
    public function show(Activity $activity)
    {
        return new ActivityResource($activity);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'type'   => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',        
            'description' => 'nullable|string',
            'occurred_at' => 'sometimes|date',
            'ip_address' => 'nullable|string|max:45',
            'user_agent' => 'nullable|string|max:1000',
            'session_id' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);
        $activity->update($validated);
        return response()->json($activity);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity)
    {
        $activity->delete();
        return response()->json(message: 'Actividad eliminada correctamente');
    }
}

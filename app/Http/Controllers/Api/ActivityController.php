<?php

namespace App\Http\Controllers\Api;

use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivityController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => 'nullable|in:work,login', 'source' => 'nullable|in:attendance,manual',
            'date_from' => 'nullable|date', 'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $query = $this->visibleQuery($request, Activity::class)->with('user:id,name')
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $v === 'attendance' ? $q->whereNotNull('attendance_id') : $q->whereNull('attendance_id'))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '<=', $v))
            ->orderByDesc('occurred_at')->orderByDesc('id');

        return response()->json(isset($filters['per_page']) ? $query->paginate($filters['per_page']) : $query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Activity::class);
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:work',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'occurred_at' => 'required|date',
        ]);
        if (isset($validated['occurred_at'])) {
            $validated['occurred_at'] = Carbon::parse($validated['occurred_at'])->setTimezone(config('app.timezone'));
        }
        $this->authorizeOwner($request, (int) $validated['user_id']);
        $validated['ip_address'] = $request->ip();
        $validated['user_agent'] = $request->userAgent();
        $activity = Activity::create($validated);

        return response()->json($activity, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Activity $activity): JsonResponse
    {
        Gate::authorize('view', $activity);

        return response()->json($activity->load('user:id,name'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Activity $activity): JsonResponse
    {
        Gate::authorize('update', $activity);
        abort_if($activity->attendance_id !== null || $activity->isLogin(), 422, 'Este registro se gestiona desde su origen.');
        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'type' => 'sometimes|in:work',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:10000',
            'occurred_at' => 'sometimes|date',
        ]);
        if (isset($validated['occurred_at'])) {
            $validated['occurred_at'] = Carbon::parse($validated['occurred_at'])->setTimezone(config('app.timezone'));
        }
        $this->authorizeOwner($request, (int) ($validated['user_id'] ?? $activity->user_id));
        $activity->update($validated);

        return response()->json($activity);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity): JsonResponse
    {
        Gate::authorize('delete', $activity);
        abort_if($activity->attendance_id !== null || $activity->isLogin(), 422, 'Este registro se gestiona desde su origen.');
        abort_if($activity->hours()->exists(), 422, 'No se puede eliminar una actividad con horas registradas.');
        $activity->delete();

        return response()->json(['message' => 'Actividad eliminada correctamente']);
    }
}

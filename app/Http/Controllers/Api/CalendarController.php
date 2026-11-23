<?php

namespace App\Http\Controllers\Api;

use App\Models\Calendar;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CalendarController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['date_from' => 'nullable|date', 'date_to' => 'nullable|date|after_or_equal:date_from']);
        $query = $this->visibleQuery($request, Calendar::class)
            ->when($data['date_from'] ?? null, fn ($q, $v) => $q->whereDate('due_at', '>=', $v))
            ->when($data['date_to'] ?? null, fn ($q, $v) => $q->whereDate('due_at', '<=', $v));

        return response()->json($query->orderBy('due_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Calendar::class);
        $data = $this->validated($request);
        $data['user_id'] ??= $request->user()->id;
        $this->authorizeOwner($request, (int) $data['user_id']);

        return response()->json(['message' => 'Evento creado correctamente', 'calendar' => Calendar::create($data)], 201);
    }

    public function show(Calendar $calendar): JsonResponse
    {
        Gate::authorize('view', $calendar);

        return response()->json($calendar);
    }

    public function update(Request $request, Calendar $calendar): JsonResponse
    {
        Gate::authorize('update', $calendar);
        $data = $this->validated($request, true);
        $this->authorizeOwner($request, (int) ($data['user_id'] ?? $calendar->user_id));
        $calendar->update($data);

        return response()->json(['message' => 'Evento actualizado correctamente', 'calendar' => $calendar]);
    }

    public function destroy(Calendar $calendar): JsonResponse
    {
        Gate::authorize('delete', $calendar);
        $calendar->delete();

        return response()->json(['message' => 'Evento eliminado correctamente']);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';
        $data = $request->validate(['user_id' => 'sometimes|required|exists:users,id',
            'name' => "$required|string|max:255", 'description' => 'nullable|string|max:255', 'due_at' => "$required|date"]);
        if (isset($data['due_at'])) {
            $data['due_at'] = Carbon::parse($data['due_at'])->setTimezone(config('app.timezone'));
        }

        return $data;
    }
}

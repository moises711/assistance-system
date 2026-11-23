<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkSite;
use App\Services\AttendanceLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkSiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkSite::class);

        return response()->json(WorkSite::orderBy('name')->get());
    }

    public function store(Request $request, AttendanceLocationService $service): JsonResponse
    {
        Gate::authorize('create', WorkSite::class);

        return response()->json(WorkSite::create($service->siteData($request->user(), $request->all())), 201);
    }

    public function update(Request $request, WorkSite $workSite, AttendanceLocationService $service): JsonResponse
    {
        Gate::authorize('update', $workSite);
        $workSite->update($service->siteData($request->user(), $request->all(), $workSite));

        return response()->json($workSite->fresh());
    }
}

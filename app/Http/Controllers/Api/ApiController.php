<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

abstract class ApiController extends Controller
{
    protected function visibleQuery(Request $request, string $model): Builder
    {
        $query = $model::query();
        if ($request->user()->isManager() && Gate::allows('viewAny', $model)) {
            return $query;
        }

        return $query->where('user_id', $request->user()->id);
    }

    protected function authorizeOwner(Request $request, int $ownerId): void
    {
        abort_unless($ownerId === $request->user()->id || $request->user()->isManager(), 403,
            'No puedes asignar registros a otro usuario.');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Calendar;
use App\Models\Departament;
use App\Models\Hour;
use App\Models\Report;
use App\Models\Schedule;
use App\Models\User;
use App\Models\WorkSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Usuario inactivo.'], 403);
        }

        $token = Str::random(60);
        $user->api_token = $token;
        $user->api_token_expires_at = now()->addDays(30);
        $user->save();

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'require_activity_report' => $user->require_activity_report,
            'gps_validation_version' => 1,
            'attendance_location' => ['required_for' => ['check_in', 'check_out'], 'max_accuracy_meters' => 30, 'site' => $user->workSite],
            'can_manage_work_sites' => Gate::allows('viewAny', WorkSite::class),
            'can_update_work_sites' => Gate::allows('update', new WorkSite),
            'can_create_work_sites' => Gate::allows('create', WorkSite::class),
            'capabilities' => $this->capabilities($user),
            'can_choose_owner' => $user->isManager() && Gate::allows('viewAny', User::class),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        User::whereKey($request->user()->id)->where('api_token', $request->bearerToken())
            ->update(['api_token' => null, 'api_token_expires_at' => null]);

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    public function users(Request $request): JsonResponse
    {
        abort_unless($request->user()->isManager(), 403);
        Gate::authorize('viewAny', User::class);

        $users = User::query();
        $request->user()->applyTeamScopeTo($users, 'users.id');

        return response()->json($users->orderBy('name')->get(['id', 'name', 'department_id', 'require_activity_report']));
    }

    private function capabilities(User $user): array
    {
        $capabilities = [];
        foreach ([
            'activities' => Activity::class,
            'attendances' => Attendance::class,
            'calendars' => Calendar::class,
            'departaments' => Departament::class,
            'hours' => Hour::class,
            'reports' => Report::class,
            'schedules' => Schedule::class,
        ] as $resource => $model) {
            $record = new $model(['user_id' => $user->id]);
            $capabilities[$resource] = [
                'view_all' => $user->isManager() && Gate::allows('viewAny', $model),
                'create' => Gate::allows('create', $model),
                'update' => Gate::allows('update', $record),
                'delete' => Gate::allows('delete', $record),
            ];
        }

        return $capabilities;
    }
}

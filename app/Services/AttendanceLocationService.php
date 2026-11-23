<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkSite;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AttendanceLocationService
{
    public function position(?array $location): array
    {
        $data = Validator::make(['location' => $location], [
            'location' => 'required|array', 'location.latitude' => 'required|numeric|between:-90,90',
            'location.longitude' => 'required|numeric|between:-180,180', 'location.accuracy' => 'required|numeric|gt:0|max:30',
            'location.captured_at' => 'required|date', 'location.is_mocked' => 'sometimes|boolean|declined',
        ], ['location.required' => 'Debes permitir y obtener tu ubicación para marcar manualmente.',
            'location.accuracy.max' => 'La ubicación es imprecisa. Activa la ubicación precisa e inténtalo de nuevo.',
            'location.is_mocked.declined' => 'No se permite una ubicación simulada.'])->validate()['location'];
        $captured = Carbon::parse($data['captured_at']);
        if ($captured->lessThan(now()->subSeconds(60)) || $captured->greaterThan(now()->addSeconds(10))) {
            throw ValidationException::withMessages(['location' => 'La ubicación caducó o su fecha es inválida. Obtén una posición nueva.']);
        }

        return ['latitude' => (float) $data['latitude'], 'longitude' => (float) $data['longitude'], 'accuracy' => (float) $data['accuracy'], 'captured_at' => $captured->toIso8601String()];
    }

    public function validate(User $user, ?array $location): array
    {
        $position = $this->position($location);
        $site = WorkSite::whereKey($user->work_site_id)->lockForUpdate()->first();
        if (! $site || ! $site->is_active) {
            throw ValidationException::withMessages(['location' => 'No tienes una sede activa asignada. Solicita al administrador que configure tu área de asistencia.']);
        }
        $distance = $this->distance($site->latitude, $site->longitude, $position['latitude'], $position['longitude']);
        if ($distance + $position['accuracy'] > $site->radius_meters) {
            throw ValidationException::withMessages(['location' => 'Estás fuera del área autorizada o demasiado cerca del límite. Distancia: '.(int) round($distance).' m; radio: '.$site->radius_meters.' m.']);
        }

        return [...$position, 'work_site_id' => $site->id, 'site_name' => $site->name, 'radius_meters' => $site->radius_meters, 'distance_meters' => round($distance, 2), 'validated_at' => now()->toIso8601String()];
    }

    public function siteData(User $actor, array $input, ?WorkSite $site = null): array
    {
        $data = Validator::make($input, ['name' => 'required|string|max:255', 'radius_meters' => 'required|integer|min:20|max:1000', 'is_active' => 'sometimes|boolean', 'location' => 'nullable|array'])->validate();
        $location = $data['location'] ?? null;
        unset($data['location']);
        if ($location !== null || $site === null) {
            $position = $this->position($location);
            $data = [...$data, 'latitude' => $position['latitude'], 'longitude' => $position['longitude'], 'capture_accuracy' => $position['accuracy'], 'captured_at' => now(), 'captured_by' => $actor->id];
        }

        return $data;
    }

    public function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;

        return 6371000 * 2 * asin(sqrt(min(1, $a)));
    }
}

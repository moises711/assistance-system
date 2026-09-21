<?php 

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Listar las asistencias del usuario autenticado
     */
    public function index(Request $request)
    {
        $attendances = Attendance::where('user_id', $request->user()->id)->latest()->get();
        
        return response()->json($attendances);
    }

    /**
     * Obtener el estado de la asistencia del día actual
     */
    public function today(Request $request)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return response()->json($attendance);
    }

    /**
     * Marcar entrada (Check-in)
     */
    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $attendance = Attendance::create([
            'user_id'        => $request->user()->id,
            'date'           => now()->toDateString(),
            'check_in_at'    => now(),
            'status'         => 'presente',
            'daily_activity' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message'    => 'Entrada registrada correctamente',
            'attendance' => $attendance,
        ], 201);
    }

    /**
     * Marcar salida (Check-out)
     */
    public function checkOut(Request $request)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        if (! $attendance) {
            return response()->json(['message' => 'No hay registro de entrada para el día de hoy.'], 404);
        }

        $attendance->update([
            'check_out_at' => now(),
        ]);

        return response()->json([
            'message'    => 'Salida registrada correctamente',
            'attendance' => $attendance,
        ]);
    }

    /**
     * Iniciar almuerzo
     */
    public function startLunch(Request $request)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        if (! $attendance) {
            return response()->json(['message' => 'No hay registro de entrada para el día de hoy.'], 404);
        }

        $attendance->update([
            'lunch_start_at' => now(),
        ]);

        return response()->json([
            'message'    => 'Inicio de almuerzo registrado',
            'attendance' => $attendance,
        ]);
    }

    /**
     * Finalizar almuerzo
     */
    public function endLunch(Request $request)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        if (! $attendance || ! $attendance->lunch_start_at) {
            return response()->json(['message' => 'No se ha iniciado el periodo de almuerzo hoy.'], 400);
        }

        $attendance->update([
            'lunch_end_at' => now(),
        ]);

        return response()->json([
            'message'    => 'Fin de almuerzo registrado',
            'attendance' => $attendance,
        ]);
    }

    /**
     * Guardar una asistencia completa
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'department_id'  => 'nullable|exists:departaments,id',
            'date'           => 'required|date',
            'check_in_at'    => 'nullable|date',
            'check_out_at'   => 'nullable|date|after_or_equal:check_in_at',
            'lunch_start_at' => 'nullable|date',
            'lunch_end_at'   => 'nullable|date|after_or_equal:lunch_start_at',
            'status'         => 'required|string|max:255',
            'daily_activity' => 'nullable|string',
        ]);

        $attendance = Attendance::create($validated);

        return response()->json([
            'message'    => 'Asistencia creada con éxito',
            'attendance' => $attendance,
        ], 201);
    }  

    /**
     * Mostrar un registro específico de asistencia
     */
    public function show(Attendance $attendance)
    {
        return response()->json($attendance);
    }

    /**
     * Actualizar un registro de asistencia
     */
    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'user_id'        => 'sometimes|exists:users,id',
            'department_id'  => 'nullable|exists:departaments,id',
            'date'           => 'sometimes|date',
            'check_in_at'    => 'nullable|date',
            'check_out_at'   => 'nullable|date|after_or_equal:check_in_at',
            'lunch_start_at' => 'nullable|date',
            'lunch_end_at'   => 'nullable|date|after_or_equal:lunch_start_at',
            'status'         => 'sometimes|string|max:255',
            'daily_activity' => 'nullable|string',
        ]);

        $attendance->update($validated);

        return response()->json([
            'message'    => 'Asistencia actualizada correctamente',
            'attendance' => $attendance,
        ]);   
    }
    /**
     * Escanear código QR y registrar asistencia
     */
    /**
 * Registrar asistencia mediante lectura de QR con la cámara de la App
 */
public function scanQr(Request $request)
{
    $validated = $request->validate([
        'qr_data' => 'required|string',
    ]);

    // Clave estática o token de la sede impreso en el QR
    $qrValido = config('app.attendance_qr_key', 'SEDE_CENTRAL_2026');

    if ($validated['qr_data'] !== $qrValido) {
        return response()->json([
            'message' => 'El código QR escaneado no pertenece a una sede válida.'
        ], 422);
    }

    $user = $request->user();
    $today = now()->toDateString();

    // Consultar si el usuario ya registró marcación el día de hoy
    $attendance = Attendance::where('user_id', $user->id)
        ->whereDate('date', $today)
        ->first();

    // CASO 1: No existe registro previo hoy -> Marca Entrada
    if (! $attendance) {
        $attendance = Attendance::create([
            'user_id'         => $user->id,
            'department_id'   => $user->department_id ?? null,
            'date'            => $today,
            'check_in_at'     => now(),
            'check_in_method' => 'APP_CAMARA_QR',
            'status'          => 'presente',
        ]);

        return response()->json([
            'message'    => 'Entrada registrada con éxito',
            'action'     => 'check_in',
            'attendance' => $attendance,
        ], 201);
    }

    // CASO 2: Existe registro de Entrada pero falta Salida -> Marca Salida
    if (! $attendance->check_out_at) {
        $attendance->update([
            'check_out_at'     => now(),
            'check_out_method' => 'APP_CAMARA_QR',
        ]);

        return response()->json([
            'message'    => 'Salida registrada con éxito',
            'action'     => 'check_out',
            'attendance' => $attendance,
        ]);
    }

    // CASO 3: Ya completó la jornada hoy
    return response()->json([
        'message' => 'Ya has registrado tu entrada y salida del día de hoy.'
    ], 400);
}

    /**
     * Eliminar un registro de asistencia
     */
    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return response()->json(['message' => 'Asistencia eliminada correctamente'], 200);
    }
}
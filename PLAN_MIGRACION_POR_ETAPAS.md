# Plan de migracion por etapas

## Objetivo

Convertir Laravel en el backend del sistema de asistencias y dejar Filament como panel administrativo web. La aplicacion movil consumira una API REST versionada.

La migracion se hara por etapas pequenas. No se comenzara la app movil ni toda la API hasta validar primero las reglas principales del dominio en Laravel.

## Arquitectura objetivo

```text
Panel Filament web
        |
        |-- gestion administrativa
        |-- RRHH
        |-- supervisores
        |-- reportes
        v
Laravel
        |
        |-- modelos y migraciones
        |-- servicios de negocio
        |-- policies y roles
        |-- API REST /api/v1
        v
Base de datos
        ^
        |
Aplicacion movil
        |
        |-- login
        |-- dashboard personal
        |-- marcaciones
        |-- GPS y QR
        |-- permisos
```

## Regla de trabajo

Cada etapa debe cumplir tres condiciones antes de continuar:

1. Migraciones ejecutables desde una base limpia.
2. Pruebas automatizadas para las reglas implementadas.
3. Flujo verificable desde Filament o mediante una prueba HTTP.

No se debe iniciar la siguiente etapa si la etapa actual tiene reglas sin probar.

# Etapa 0: preparar el entorno

## Objetivo

Asegurar que el proyecto pueda ejecutarse y probarse desde Docker.

## Actividades

- Levantar los servicios Docker.
- Confirmar que `laravel.test` tenga PHP, Composer y `pdo_mysql`.
- Confirmar que MySQL este disponible.
- Confirmar que Filament cargue el panel.
- Confirmar que el idioma del entorno sea español:

```env
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_TIMEZONE=America/Lima
```

- Limpiar la configuracion:

```bash
docker compose exec laravel.test php artisan optimize:clear
```

- Ejecutar las migraciones desde cero:

```bash
docker compose exec laravel.test php artisan migrate:fresh --seed
```

## Pruebas de salida

- `php artisan about` funciona dentro del contenedor.
- `migrate:fresh --seed` termina sin errores.
- `/admin` carga correctamente.
- Se pueden ver usuarios, departamentos, actividades, horas y asistencias.
- Los textos principales aparecen en español.

# Etapa 1: base de usuarios y roles

## Objetivo

Preparar los usuarios que utilizaran el panel web y la futura aplicacion movil.

## Cambios previstos

### Usuarios

Agregar a `users` los campos necesarios:

- `role`: `admin`, `rrhh`, `supervisor`, `empleado`, `pphh`.
- `supervisor_id` nullable.
- `department_id` nullable.
- `schedule_id` nullable.
- `allows_remote` boolean.
- `is_active` boolean.

Conservar:

- Nombre.
- Apellido.
- DNI unico.
- Correo unico.
- Contraseña.
- Codigo QR personal.

### Modelo `User`

Agregar:

- Casts para booleanos.
- Relaciones `supervisor`, `subordinates`, `department` y `schedule`.
- Scopes para usuarios activos y por rol.
- Helpers de autorizacion, sin colocar toda la logica de permisos dentro del modelo.

### Autorizacion

Crear policies y middleware para:

- Administrador.
- RRHH.
- Supervisor.
- Empleado.
- Practicante.

Definir que puede hacer cada rol en usuarios, asistencias, permisos y reportes.

## Pruebas de salida

- Se puede crear un usuario con cada rol.
- Un usuario puede tener supervisor, departamento y horario.
- Un empleado no puede consultar usuarios fuera de su permiso.
- Un supervisor solo puede consultar subordinados.
- RRHH puede gestionar empleados.
- El administrador puede gestionar toda la configuracion.
- Un usuario inactivo no puede iniciar sesion.

# Etapa 2: horarios - completada

## Objetivo

Crear las reglas necesarias para determinar jornada, tardanzas y faltas.

## Modelo previsto: `Schedule`

Campos iniciales:

- `name`.
- `type`: fijo, flexible, rotativo o nocturno.
- `start_time`.
- `end_time`.
- `tolerance_minutes`.
- `maximum_late_minutes`.
- `allows_late_entry`.
- Dias laborables de lunes a domingo.
- `is_active`.

## Reglas previstas

- Calcular hora limite de entrada.
- Saber si una fecha es laborable.
- Calcular duracion de jornada.
- Soportar turnos que cruzan medianoche.
- Validar horarios incompletos o invalidos.

## Pruebas de salida

- Entrada antes de la tolerancia queda a tiempo.
- Entrada despues de la tolerancia queda tardia.
- Un dia no laborable no permite marcar normalmente.
- Un turno nocturno calcula correctamente su duracion.
- Los horarios se pueden administrar desde Filament.

## Implementacion realizada

- Enum `App\Enums\ScheduleType` con horarios fijo, flexible, rotativo y nocturno.
- Modelo `Schedule` con tolerancia, tardanza maxima, dias laborables y estado activo.
- Migracion `0000_01_01_000000_create_schedules_table.php`.
- Relacion `User::schedule()` y campo `users.schedule_id`.
- Factory `ScheduleFactory`.
- CRUD Filament de horarios en `/admin/schedules`.
- Asignacion de horario desde el formulario de usuarios.
- Calculo probado de jornada normal, turno nocturno, dia laborable, limite de entrada y tardanza.

## Validacion realizada

- `migrate:fresh --seed` ejecutado correctamente dentro de Docker.
- Horario creado y persistido mediante factory.
- Horario asignado a un usuario y recuperado mediante relacion Eloquent.
- Jornada 09:00-17:00 calculada como 480 minutos.
- Tolerancia de 10 minutos calculada como limite 09:10.

La siguiente etapa es la **Etapa 3: asistencia minima**, donde se implementara la secuencia de entrada, almuerzo y salida usando un servicio de dominio.

# Etapa 3: asistencia minima

## Objetivo

Implementar el flujo basico de asistencia antes de agregar GPS y QR de oficina.

## Modelo `Attendance`

Revisar y ampliar los campos actuales para incluir:

- `user_id`.
- `department_id`.
- `date`.
- `check_in_at`.
- `lunch_start_at` nullable.
- `lunch_end_at` nullable.
- `check_out_at` nullable.
- `status`.
- `total_minutes`.
- `overtime_minutes`.
- `missing_minutes`.
- `late_minutes`.
- `early_leave_minutes`.
- `daily_activity` nullable.
- `check_in_method`.
- `check_out_method`.
- IP y coordenadas, inicialmente opcionales.

Agregar una restriccion unica para:

```text
user_id + date
```

## Servicio recomendado

Crear `app/Services/AttendanceService.php` para concentrar las reglas:

- `checkIn()`.
- `startLunch()`.
- `endLunch()`.
- `checkOut()`.
- `calculateWorkedMinutes()`.
- `calculateLateMinutes()`.
- `validateActionOrder()`.

Las pantallas Filament y la futura API deben usar este servicio. No duplicar las reglas en formularios y controladores.

## Reglas de la primera version

1. Un usuario solo tiene una asistencia por dia.
2. No se puede iniciar dos veces.
3. No se puede iniciar almuerzo sin entrada.
4. No se puede finalizar almuerzo sin iniciarlo.
5. No se puede salir sin entrada.
6. No se puede salir dos veces.
7. La actividad diaria sera obligatoria para empleados y practicantes al salir.
8. Las horas netas descontaran el almuerzo.
9. La asistencia usara el horario asignado al usuario.

## Pruebas de salida

- Entrada exitosa.
- Entrada duplicada rechazada.
- Inicio de almuerzo sin entrada rechazado.
- Fin de almuerzo sin inicio rechazado.
- Salida sin entrada rechazada.
- Salida duplicada rechazada.
- Calculo correcto con y sin almuerzo.
- Calculo correcto de tardanza.
- Restriccion de una asistencia por usuario y dia.

# Etapa 4: Filament para asistencia

## Objetivo

Administrar desde la web las reglas ya probadas.

## Funciones

- Crear y editar horarios.
- Asignar horario a usuario.
- Consultar asistencias.
- Filtrar por fecha, usuario, departamento y estado.
- Ver detalle de entrada, almuerzo y salida.
- Correccion manual restringida a admin/RRHH.
- Registrar quien hizo una correccion.

## Pruebas de salida

- RRHH puede consultar asistencias.
- Un supervisor solo ve sus subordinados.
- Un empleado no puede editar manualmente su asistencia.
- Los filtros devuelven datos correctos.
- Las modificaciones quedan auditadas.

# Etapa 5: autenticacion para la app

## Objetivo

Crear la primera API consumible por la aplicacion movil.

## Tecnologia recomendada

Usar Laravel Sanctum.

## Endpoints iniciales

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/me
GET  /api/v1/dashboard
```

## Reglas

- Respuestas JSON consistentes.
- Validacion con Form Requests.
- Tokens revocables.
- Usuario inactivo rechazado.
- Rate limiting en login.
- No devolver contrasenas ni datos sensibles.

## Pruebas de salida

- Login correcto devuelve token.
- Credenciales invalidas devuelven error controlado.
- Token invalido devuelve 401.
- Logout revoca el token.
- `/me` devuelve el usuario autenticado.
- La app puede obtener su dashboard.

# Etapa 6: GPS y QR de oficina

## Objetivo

Agregar validacion de ubicacion sin romper las reglas de asistencia.

## Funciones

- Configuracion de oficina activa.
- Latitud, longitud y radio permitido.
- Calculo Haversine.
- Registro de precision GPS.
- Usuarios remotos sin bloqueo GPS.
- QR de oficina firmado.
- Validacion de QR y configuracion activa.

## Pruebas de salida

- Coordenada dentro del radio permite marcar.
- Coordenada fuera del radio rechaza marcar.
- Precision insuficiente rechaza marcar si la politica lo exige.
- QR invalido rechaza marcar.
- QR de otra oficina rechaza marcar.
- Una marcacion QR sigue validando horario, feriado y permiso.

# Etapa 7: permisos, feriados y justificaciones

## Objetivo

Completar la gestion laboral que afecta las asistencias.

## Modulos

- Permisos o ausencias programadas.
- Feriados.
- Justificaciones.
- Recuperaciones.
- Metas de practicantes.

## Pruebas de salida

- Un empleado solicita permiso.
- RRHH aprueba o rechaza.
- Un permiso aprobado bloquea la marcacion.
- Un feriado no genera falta.
- Una falta puede crear recuperacion.
- Una justificacion queda pendiente y luego se procesa.
- El practicante puede consultar avance de su meta.

# Etapa 8: reportes y notificaciones

## Objetivo

Completar la operacion administrativa.

## Funciones

- Dashboard por rol.
- Reporte por fechas.
- Filtros por usuario, departamento y estado.
- Tardanzas y faltas.
- Horas extra.
- Exportacion Excel.
- Notificaciones por correo.
- Notificaciones en cola.

## Pruebas de salida

- Un reporte coincide con las asistencias almacenadas.
- Un supervisor solo ve sus subordinados.
- La exportacion respeta los filtros.
- Las notificaciones no bloquean la marcacion.
- Los errores de correo quedan registrados.

# Etapa 9: aplicacion movil

## Objetivo

Construir la app cuando la API y las reglas del backend esten estables.

## Primera version movil

- Login.
- Dashboard personal.
- Entrada.
- Inicio y fin de almuerzo.
- Salida.
- Historial de asistencias.
- Solicitud de permisos.
- Consulta de estado.
- GPS.
- Escaneo de QR de oficina.

## Criterio para comenzar la app

La app no debe comenzar antes de que esten validados:

- Contratos JSON.
- Autenticacion Sanctum.
- Reglas de marcacion.
- Codigos de error.
- GPS/QR.
- Permisos del usuario.

# Primera ejecucion recomendada

La primera etapa practica debe ser **Etapa 1: usuarios y roles**, seguida inmediatamente por pruebas.

Comandos de referencia dentro del contenedor:

```bash
docker compose exec laravel.test php artisan migrate:fresh --seed
docker compose exec laravel.test php artisan test
docker compose exec laravel.test php artisan route:list
```

## Definition of Done de la primera etapa

- Migracion limpia exitosa.
- Usuarios con roles funcionando.
- Policies probadas.
- Filament muestra y filtra usuarios correctamente.
- Usuario inactivo no puede iniciar sesion.
- No se empieza horarios ni API hasta que estas pruebas pasen.

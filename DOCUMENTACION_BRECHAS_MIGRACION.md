# Brechas de migracion: sistema de asistencias Laravel

## Proposito

Este documento compara la funcionalidad descrita en `DOCUMENTACION_SISTEMA_ACTUAL.md` con la implementacion Laravel actual. Sirve como contexto de trabajo para completar la migracion desde Django.

Estados usados:

- **Implementado**: existe una implementacion funcional identificable.
- **Parcial**: existe la estructura, pero faltan reglas, pantallas o integracion.
- **Faltante**: no existe una implementacion equivalente identificable.
- **Pendiente de validar**: existe codigo relacionado, pero necesita prueba funcional con la base de datos y el panel.

## Resumen ejecutivo

Laravel tiene actualmente una base CRUD de Filament para usuarios, actividades, asistencias, calendarios, departamentos, horas y reportes. Tambien tiene generacion de QR personal, registro de inicios de sesion y relaciones Eloquent basicas.

Las brechas principales son:

1. No existe el modelo de dominio completo de horarios, empleados por rol, permisos, justificaciones, feriados, GPS, recuperaciones ni tokens moviles.
2. La asistencia actual es solo un registro simple con inicio y fin; no existe el flujo entrada -> almuerzo -> salida.
3. No existen las reglas de negocio de horario, tardanzas, faltas, permisos, recuperaciones ni validacion GPS/QR de oficina.
4. No existe la API movil versionada ni la autenticacion por tokens de dispositivo.
5. Reportes, dashboards, exportacion Excel y notificaciones aun no representan la funcionalidad Django.
6. La autorizacion por roles no esta implementada en el modelo ni en policies/middleware equivalentes.

## Estado funcional por modulo

| Modulo | Estado Laravel | Situacion actual | Trabajo faltante |
|---|---|---|---|
| Usuarios | Parcial | CRUD Filament, DNI, email, QR personal y relaciones basicas | Roles, supervisor, horario, departamento/area, remoto, estado activo y permisos |
| Roles y autorizacion | Faltante | No se observa campo de rol ni policies equivalentes | Roles admin, RRHH, supervisor, empleado y practicante; middleware y policies |
| Login web | Parcial | Filament proporciona login y se registra el evento `Login` en `Activity` | Redireccion por rol, registro publico, throttling, cambio y recuperacion de contrasena adaptados |
| Registro de actividad/login | Parcial | `Activity` guarda tipo, usuario, fecha, IP, navegador, sesion y metadata | Logout, consultas de auditoria, filtros y retencion de eventos |
| Asistencia diaria | Parcial | `Attendance` guarda usuario, departamento, inicio, fin, fecha, estado y horas | Unicidad diaria, flujo de marcacion, almuerzo, calculos y validaciones |
| Horarios | Faltante | No existe modelo ni recurso de horario | Turnos fijos/flexibles/rotativos/nocturnos, tolerancias y dias laborables |
| Departamentos/areas | Parcial | Existe `Departament` y CRUD Filament | Confirmar si reemplaza `Area`; agregar unicidad, estado y asignacion a usuarios |
| Horas por actividad | Parcial | `Hour` relaciona usuario y actividad con fecha e intervalo | Validar solapamientos, calcular duracion, asociar a jornada y reportar horas |
| Actividades | Parcial | Catalogo y auditoria comparten `Activity`; existe relacion con horas | Separar claramente actividad laboral de auditoria o documentar ambos tipos; CRUD y filtros |
| GPS | Faltante | No existe configuracion ni calculo de distancia | Configuracion de oficina, radio, Haversine, precision, captura y validacion |
| QR de oficina | Faltante | Solo existe QR personal de usuario | Payload firmado, configuracion activa, validacion de oficina y descarga |
| QR personal | Parcial | UUID persistente y SVG visible en detalle del usuario | Lectura por RRHH, busqueda por codigo y uso para marcar entrada/salida |
| Almuerzo | Faltante | `Attendance` no tiene inicio/fin de almuerzo | Campos, orden de acciones y validacion de duplicados |
| Tardanzas | Faltante | No hay calculo contra horario | Minutos tarde, llegada temprana, tolerancia y estados derivados |
| Faltas | Faltante | No existe tarea equivalente a `verificar_faltas` | Comando programado, feriados, ausencias y generacion diaria |
| Permisos/ausencias | Faltante | No hay modelo ni recurso | Solicitud, aprobacion/rechazo, fechas, motivo, creador y procesador |
| Justificaciones | Faltante | No hay modelo ni flujo | Solicitud por asistencia, revision, aprobacion y trazabilidad |
| Recuperaciones | Faltante | No existe deuda de recuperacion | Generacion por falta, horas pendientes, cierre y estados |
| Feriados | Faltante | No existe modelo ni recurso | CRUD, bloqueo de marcacion y exclusion de faltas |
| Metas de practicante | Faltante | No existe modelo ni pantalla | Meta de horas, periodo, progreso y restricciones para practicantes |
| Reportes | Parcial | Existe `Report` relacionado solo con usuario | Reporte real de asistencias, filtros, agregados y exportacion XLSX |
| Dashboards | Faltante | Solo dashboard base de Filament | Dashboard admin, RRHH, supervisor, empleado y practicante |
| API movil | Faltante | No hay rutas API funcionales equivalentes | `/api/v1`, login por token, dashboard, GPS, QR, marcaciones y permisos |
| API administrativa | Faltante | `routes/web.php` solo tiene la raiz | CRUD API para empleados, horarios, areas, feriados, reportes y solicitudes |
| Notificaciones | Faltante | No hay notificaciones de tardanzas o justificaciones | Mail/notifications, destinatarios, colas y registro de fallos |
| Exportacion Excel | Faltante | No se observa exportador | Exportacion de reportes con filtros y autorizacion |
| Configuracion regional | Parcial | Se esta cambiando el locale a espanol | Confirmar `.env` con `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=es` y timezone America/Lima |

## Funciones faltantes prioritarias

### 1. Modelo de usuario y roles

Agregar a `users` o a tablas relacionadas:

- `role`: `admin`, `rrhh`, `supervisor`, `empleado`, `pphh`.
- `supervisor_id` nullable, relacionado con `users`.
- `department_id` o una relacion con un catalogo `areas`.
- `schedule_id` o relacion con `schedules`.
- `allows_remote`/`permite_remoto`.
- `is_active`.
- Datos de contacto y auditoria necesarios.

Implementar policies y middleware para limitar:

- Administrador: configuracion y acceso total.
- RRHH: usuarios, asistencias, permisos, justificaciones y reportes.
- Supervisor: subordinados, reportes y revisiones.
- Empleado/practicante: sus propios datos, marcaciones y solicitudes.

### 2. Horarios

Crear modelo `Schedule` o `Horario` con:

- Nombre.
- Tipo: fijo, flexible, rotativo y nocturno.
- Hora de entrada y salida.
- Tolerancia.
- Maximo de tardanza.
- Permiso de entrada tardia.
- Dias laborables de lunes a domingo.
- Duracion de jornada, incluyendo turnos nocturnos.

Reglas necesarias:

- `horaEntradaConTolerancia()`.
- `esLaborable($fecha)`.
- `duracionJornada()`.
- Validar que la salida pueda caer al dia siguiente.

### 3. Flujo completo de marcacion

Ampliar `attendances` para soportar:

- `check_in_at` o entrada.
- `lunch_start_at`.
- `lunch_end_at`.
- `check_out_at` o salida.
- Tipo de entrada y salida: GPS, QR, manual o RRHH.
- IP de entrada y salida.
- Latitud, longitud y precision de entrada y salida.
- Horas netas trabajadas.
- Minutos tarde y minutos temprano.
- Actividad diaria.

Reglas:

1. Una asistencia por usuario y fecha.
2. Entrada antes de iniciar almuerzo.
3. Fin de almuerzo solo despues de iniciar almuerzo.
4. Salida solo despues de entrada y una sola vez.
5. Actividad obligatoria para empleados y practicantes al salir.
6. Calculo de horas netas descontando almuerzo.

### 4. GPS y QR de oficina

Crear modelos para:

- `GpsConfiguration`: sede, latitud, longitud, radio y activo.
- `AuthorizedOfficeIp`: sede, IP publica y activo.

Implementar servicios:

- Distancia Haversine.
- Validacion de radio.
- Validacion de precision.
- Configuracion activa.
- Payload QR firmado con `config_id`, coordenadas y radio.
- Descarga y visualizacion del QR de oficina.
- Marcacion QR sin omitir las reglas de horario, feriado o permiso.

### 5. Permisos, justificaciones y recuperaciones

Crear modelos y recursos Filament para:

- `ScheduledAbsence`/`AusenciaProgramada`.
- `Justification`/`Justificacion`.
- `RecoveryDay`/`RecuperacionDia`.
- `Holiday`/`DiaFeriado`.
- `InternHoursGoal`/`MetaHorasPracticante`.

Cada flujo debe incluir:

- Estado.
- Usuario solicitante.
- Usuario que aprueba o procesa.
- Fechas y motivo.
- Restricciones por rol.
- Auditoria de cambios.

### 6. Reportes y dashboards

Implementar:

- Resumen diario de presentes, tardanzas, faltas y permisos.
- Reporte por rango de fechas.
- Filtro por usuario, supervisor, departamento y estado.
- Horas trabajadas y horas extra.
- Progreso de practicantes.
- Panel personal del empleado.
- Panel de supervisor con subordinados.
- Exportacion XLSX.

El modelo `Report` actual solo almacena `user_id`; no representa por si mismo un reporte generado. Conviene decidir si debe ser una entidad de reportes guardados o eliminarlo y generar reportes mediante consultas/servicios.

### 7. API movil

Crear rutas versionadas `/api/v1` con:

- Login por usuario/email y token de dispositivo.
- Logout y revocacion de token.
- Expiracion y limite de dispositivos.
- Dashboard personal.
- Validacion GPS.
- Validacion QR de oficina.
- Marcaciones de entrada, almuerzo y salida.
- Solicitud y consulta de permisos.
- Resumen RRHH.

Recomendacion: usar Sanctum o tokens hashados propios, nunca guardar tokens planos si no es imprescindible.

## Problemas tecnicos actuales detectados

1. `Activity` mezcla catalogo de actividades laborales con auditoria de login mediante el campo `type`; debe mantenerse documentado o separarse en `AuditLog`.
2. `Report` no contiene fecha, filtros, tipo ni resultado; actualmente no alcanza para representar los reportes del sistema Django.
3. `Calendar` representa eventos con `due_at`, pero no tiene `user_id` ni relaciones; debe definirse si es calendario de citas, feriados o turnos.
4. `Attendance` usa `total_hours`, `overtime` y `missingtime` como enteros, pero no esta definido si son horas o minutos. Se recomienda nombres explicitos como `total_minutes`, `overtime_minutes` y `missing_minutes`.
5. `Hour` usa campos `time` y casts `datetime:H:i`; conviene decidir si se guardan horas locales y establecer timezone America/Lima.
6. Existe una migracion separada `2026_09_08_164043_q_r.php` para `qr_code`; si se mantiene la politica de consolidar migraciones, debe integrarse en la migracion original de usuarios.
7. La migracion original de `activities` debe ejecutarse despues de `users` porque ahora contiene `user_id`.
8. No hay una restriccion unica visible para impedir dos asistencias del mismo usuario en la misma fecha.
9. No se observan indices para las consultas habituales de asistencia por usuario, fecha y estado.
10. El registro de login en `AppServiceProvider` debe protegerse para no ejecutarse durante comandos sin request y conviene registrar tambien logout.
11. No hay pruebas de dominio para validar secuencia de marcaciones, tardanzas, permisos, GPS, QR ni autorizacion por roles.
12. Las factories existen, pero deben ampliarse para cubrir escenarios de asistencia, ausencias, tardanzas y recuperaciones.

## Orden recomendado de implementacion

### Fase 1: base del dominio

1. Definir nombres oficiales: `Department`/`Departament`, `Schedule`, `Area` y nombres de campos de asistencia.
2. Ampliar `users` con rol, supervisor, horario, departamento y remoto.
3. Crear policies y middleware por rol.
4. Consolidar migraciones y agregar indices/FK/unicidades.
5. Establecer timezone `America/Lima` y locale `es`.

### Fase 2: asistencia real

1. Crear modelo de horarios.
2. Ampliar asistencia con entrada, almuerzo y salida.
3. Crear un servicio `AttendanceService` para concentrar reglas.
4. Implementar tardanza, llegada temprana y horas netas.
5. Completar formularios, tabla, detalle y acciones Filament.
6. Agregar pruebas de la secuencia de marcacion.

### Fase 3: ubicacion y QR

1. Configurar GPS y sedes.
2. Implementar Haversine y validacion de radio.
3. Generar y validar QR de oficina firmado.
4. Conectar QR personal con busqueda/registro de RRHH.
5. Probar marcaciones GPS, remotas y QR.

### Fase 4: gestion laboral

1. Permisos y ausencias.
2. Feriados.
3. Justificaciones.
4. Recuperaciones.
5. Metas de practicantes.
6. Notificaciones por correo.

### Fase 5: reportes y movilidad

1. Dashboard por rol.
2. Reportes filtrables.
3. Exportacion XLSX.
4. API movil versionada.
5. Tokens, expiracion, revocacion y pruebas de seguridad.

## Criterios de finalizacion de la migracion

- Un usuario puede pertenecer a un rol, departamento, horario y supervisor.
- Una asistencia diaria permite entrada, almuerzo opcional y salida.
- El sistema calcula tardanza, horas netas, faltas y recuperaciones.
- GPS y QR de oficina validan las marcaciones segun las reglas.
- RRHH puede escanear el QR personal y localizar al empleado.
- Permisos, justificaciones y feriados afectan correctamente la marcacion.
- Cada rol solo puede ver y modificar lo que le corresponde.
- Los reportes se pueden filtrar y exportar.
- La API movil reproduce las reglas del navegador.
- Hay pruebas automatizadas para los casos críticos.

## Contexto persistente para futuras sesiones

Al continuar este proyecto, usar este orden de referencia:

1. Leer `DOCUMENTACION_SISTEMA_ACTUAL.md` para conocer el comportamiento original Django.
2. Leer este archivo para conocer las brechas ya identificadas.
3. Revisar las migraciones y modelos Laravel antes de añadir nuevas tablas.
4. Preferir servicios de dominio para reglas de asistencia en vez de colocarlas en recursos Filament.
5. Mantener las migraciones originales consolidadas mientras el sistema siga en desarrollo.
6. Validar cada fase con migraciones limpias, factories y pruebas de feature.

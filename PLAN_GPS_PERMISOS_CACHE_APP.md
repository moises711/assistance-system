# Plan de integración: sedes, GPS, QR, permisos, caché y horas semanales

Fecha: 10/10/2026. Proyectos de trabajo: `/home/sonjin/Empresa/asistenciaweb` y `/home/sonjin/Empresa/app`. Este documento es el plan solicitado; no aplica migraciones ni modifica la lógica actual.

## 1. Situación comprobada

- Laravel 13.30.0, Filament 5.7.8, Shield 4.3.1 y Spatie Permission 8.3.0, según composer.lock.
- AttendanceService concentra entrada, salida, refrigerio, QR, orden de acciones y cálculo de minutos. Debe seguir siendo el punto común de la web y API.
- La marcación directa aún no valida ubicación. La app no tiene geolocalización ni permisos de ubicación.
- El QR es global, basado en attendance_qr_key; OfficeQrPage también usa una cadena fija. Falta asociarlo a sedes.
- /me entrega capacidades, pero los catálogos Flutter y parte del backend mezclan permisos con nombres de roles. UserResource limita la consulta a sí mismo salvo super_admin, incluso si otro usuario tiene permisos de administración.
- El resumen semanal no tiene una API específica. total_minutes contiene jornadas cerradas y descuenta el refrigerio completo.
- Laravel tiene configuración Redis; cache.php usa database como valor predeterminado. La disponibilidad de Redis en Hostinger no está comprobada. El PHP de esta sesión no carga phpredis.
- Dos pantallas de asistencia conservadas en IndexedStack pueden pedir los mismos datos. Redis reduce consultas a la base; la caché Flutter será la que reduzca peticiones HTTP.

## 2. Reglas propuestas

- Botón directo: exige una ubicación actual y validación de la sede en el servidor.
- QR: no exige GPS ni solicita permisos de ubicación. Debe ser válido, pertenecer a una sede activa autorizada y estar permitido para ese usuario.
- Alcance inicial propuesto: GPS en entrada y salida. Radio inicial: 100 metros, ajustable por sede. Estos valores quedan pendientes de la preferencia del usuario.
- La actividad sigue pidiéndose al salir; require_activity_report mantiene su obligatoriedad u opcionalidad tanto por GPS como por QR.
- No se rastrea al empleado continuamente: solo se captura ubicación al intentar marcar.
- Si no hay sede asignada, GPS está apagado, la ubicación es antigua o imprecisa, no se confirma una marcación directa. Se explica el motivo y se ofrece QR si está habilitado.
- allows_remote no se convierte automáticamente en una autorización para saltarse controles. Las excepciones remotas requieren una regla explícita y permiso específico.
- Las correcciones administrativas se distinguen de la marcación propia: requieren autorización, motivo y auditoría.

## 3. Tablas y modelos

### work_sites / WorkSite

Campos: id, name, address opcional, latitude y longitude con precisión decimal; radius_meters; maximum_accuracy_meters; is_active; captured_accuracy_meters; captured_at; captured_by; timestamps y softDeletes. Validar coordenadas y límites de radio. La precisión observada al capturar no equivale a una garantía de exactitud.

### attendance_rules / AttendanceRule

Campos: id, name, is_active, gps_enabled, qr_enabled, gps_check_in, gps_check_out, gps_lunch_start, gps_lunch_end, maximum_position_age_seconds y allow_remote_exception. Es una configuración operativa; no reemplaza las Policies de autorización Laravel ni el permiso de ubicación del teléfono. Evitar llamar a este modelo AttendancePolicy porque ya existe la policy de autorización con ese nombre.

### work_site_user y users

Pivot con user_id y work_site_id únicos. Añadir attendance_rule_id a users. Un empleado puede tener varias sedes autorizadas. La interfaz permitirá asignar sedes en lote a usuarios de un departamento, conservando una única fuente de asignación: el pivot.

### attendance_marks / AttendanceMark

Registro por acción: attendance_id, user_id, work_site_id nullable, action, method (gps, qr, remote_exception, administrative), received_at, latitude/longitude nullable, accuracy_meters nullable, distance_meters nullable, location_captured_at nullable, request_id, actor_id, reason nullable y versión/snapshot de la regla aplicada. request_id se asocia al usuario y es único por usuario. Guardar los datos necesarios de comprobación; ocultar coordenadas a quienes no tengan permiso para consultarlas.

Los timestamps oficiales provienen del servidor. Las columnas actuales de attendances se mantienen como estado de la jornada. Los cambios de sede o radio posteriores no deben alterar retrospectivamente la evidencia de una marcación anterior.

Migraciones adicionales, sin reescribir las antiguas: creación de work_sites; attendance_rules; pivot y FK de users; attendance_marks e índices. Mantener el índice único actual user_id/date.

## 4. Captura de sede y validación GPS

El administrador podrá usar la app o abrir Filament desde su teléfono. El formulario tendrá “Capturar mi ubicación”, indicador de precisión, reintento y confirmación antes de guardar. Tomar varias lecturas durante un intervalo breve, descartar lecturas antiguas y elegir una lectura de buena precisión; mostrar coordenadas, precisión y radio. Un valor inicial de precisión administrativa de 15 m es una propuesta que deberá probarse en el lugar, no una promesa del dispositivo.

En Filament: navigator.geolocation, enableHighAccuracy y permiso explícito del navegador, bajo HTTPS. En Flutter: geolocator, comprobación del servicio y permiso en primer plano. No solicitar ubicación en segundo plano. Fuentes: [Geolocation API](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation_API) y [Geolocator](https://pub.dev/packages/geolocator).

El servidor comprueba autenticación, permiso de acción, regla, sede asignada/activa, coordenadas válidas, edad de lectura y precisión; rechazar timestamps futuros o incoherentes y limitar frecuencia de solicitudes. Propuesta de lectura: antigüedad máxima 60 s y precisión máxima 30 m. Calcular distancia geodésica y exigir distancia + precisión <= radio para una aceptación conservadora. Cerca del límite, solicitar otra lectura; no ampliar el radio automáticamente por mala precisión. La app muestra una estimación; el backend decide.

GPS no prueba por sí solo que una ubicación no fue simulada. Las señales de ubicación simulada que proporcione el dispositivo son indicios complementarios y no sustituyen la validación. No prometer prevención absoluta.

AttendanceService recibirá un contexto de marcación ya validado por un servicio común. Los botones web usarán la misma regla para evitar eludir GPS entrando por DashboardEmpleado o por otra ruta. Validación, actualización y auditoría se coordinan con la transacción existente. Revalidar la configuración de la sede si cambió durante la solicitud.

## 5. QR sin GPS

Unificar generación y comprobación en OfficeQrPage, su vista y AttendanceController. Cada sede tendrá su credencial QR, revocable y guardada mediante hash. Generar el QR localmente con las librerías ya instaladas, sin enviar su contenido a servicios externos.

El cartel imprimible seguirá funcionando sin ubicación; comprobar sede, asignación y regla. Añadir como modalidad opcional un QR temporal firmado para una pantalla de recepción, con caducidad propuesta de 90 s. Una imagen de un QR estático puede compartirse: el QR temporal reduce ese problema, pero no convierte el QR en una prueba GPS.

No reintroducir la validación de GPS después de escanear. La salida por QR conserva la descripción de actividad. Enviar acción explícita para evitar que un reintento de entrada se interprete como salida. Un request_id repetido devuelve el mismo resultado anterior y no ejecuta otra acción. La compatibilidad con el QR global actual se retirará después de preparar las nuevas sedes y carteles.

## 6. Rutas API v1

| Ruta | Función y autorización |
|---|---|
| GET /me | Perfil ampliado: horario, departamento, sedes mínimas, regla, módulos y capacidades efectivas |
| GET /work-sites | Sedes autorizadas al empleado; listado administrativo solo con permiso |
| POST /work-sites | Crear sede con permiso Create:WorkSite |
| GET/PATCH/DELETE /work-sites/{id} | Policy y alcance; baja lógica para conservar historial |
| POST /work-sites/{id}/capture | Guardar/reemplazar centro capturado; permiso CaptureLocation:WorkSite |
| GET /attendance-rules y CRUD con {id} | Gestión de reglas con permisos de cada operación |
| PUT /users/{id}/work-sites | Asignar sedes; permiso AssignWorkSites:User y alcance sobre usuario |
| GET /attendance/context | Acciones posibles, métodos permitidos y requisitos; no declara válido el GPS |
| POST /attendance/check-in | Acción GPS: sede, lat/lon, precisión, captured_at y request_id |
| POST /attendance/check-out | Mismo contexto GPS y notes; actividad obligatoria según usuario |
| POST /attendance/lunch/start y /end | GPS únicamente si la regla configurada lo exige |
| POST /attendance/scan | qr_data, action, request_id y notes cuando corresponde; sin GPS |
| GET /attendance/weekly-summary?week_start=YYYY-MM-DD | Resumen propio; otro usuario solo con autorización y alcance |
| GET /users y CRUD con {id} | Administración autorizada; no exponer credenciales ni asignar roles arbitrarios |
| GET /roles y /permissions | Catálogos de administración bajo autorización |

No permitir que el cliente elija method=manual para omitir controles. Las correcciones usan acciones administrativas separadas y auditadas. Cerrar también el posible atajo en POST/PATCH /attendances: crear o modificar sellos por CRUD requiere Correct:Attendance, motivo y alcance administrativo; la marcación propia debe pasar por GPS o QR. Tener Create:Attendance no autoriza a eludir la ubicación. Respuestas con códigos de error estables: outside_site, inaccurate_location, stale_location, location_required, site_not_assigned, invalid_qr, action_not_allowed. Autenticación 401, autorización 403, validación 422 y conflictos reales 409.

## 7. Filament Shield y alcance de datos

Crear WorkSiteResource y AttendanceRuleResource con Schema/Form, Infolist, Table y páginas List/Create/View/Edit siguiendo las carpetas actuales. WorkSite incluirá captura, radio y usuarios asignados. UserForm incluirá sedes y regla. Añadir filtros por sede, método y usuario en asistencias, y método/distancia en detalle autorizado.

Generar permisos CRUD con Shield y registrar WorkSitePolicy, AttendanceRulePolicy y AttendanceMarkPolicy. Habilitar permisos personalizados en config/filament-shield.php: CaptureLocation:WorkSite, AssignWorkSites:User, MarkGps:Attendance, MarkQr:Attendance, ViewWeeklySummary:Attendance, ViewTeamSummary:Attendance y Correct:Attendance. La facultad de gestionar roles será distinta de la facultad de editar usuarios.

Auditar HasRoleBasedNavigation, UserResource::getEloquentQuery(), User::isManager() y Gate::before. Sustituir decisiones por nombres de roles por Policies + alcance. Solo super_admin conserva bypass global; migrar los accesos legítimos actuales del administrador a permisos explícitos antes de retirar el bypass. Shield administra permisos; las Policies limitan qué usuarios, departamentos y sedes puede consultar cada persona.

/me entrega capacidades y módulos para Flutter. La app oculta acciones no autorizadas; la API las verifica siempre. Invalidar capacidades tras cambios de roles, permisos, estado, sede o regla. No reemplazar ciegamente policies existentes al regenerar Shield.

## 8. Caché y rendimiento

### Laravel / Redis

Verificar primero Redis y el cliente PHP en el hosting real. Configurar CACHE_STORE, conexión y prefijo por aplicación/entorno. No cambiar el cliente ni instalar dependencias sin confirmar compatibilidad. Proporcionar un modo explícito con database si Redis no está disponible; reportar el fallo de caché sin desactivar la validación GPS.

Valores propuestos: catálogos 5 min, resúmenes 60 s, configuración para visualización 60 s. Usar claves por usuario, alcance, filtros, semana y versión de permisos. Invalidar después del commit al marcar/corregir asistencia o cambiar horario, usuario, sede, regla o permisos. Las escrituras y las decisiones críticas de autorización/proximidad no usan una respuesta almacenada. Redis puede almacenar catálogos y agregados; los registros y la idempotencia permanecen en la base de datos.

Usar Cache::remember y bloqueos cortos para evitar recalcular el mismo agregado simultáneamente; índices, consultas agregadas y paginación antes que cargar colecciones completas. Fuente: [Laravel 13 Cache](https://laravel.com/docs/13.x/cache).

### Flutter

Cachear GET por usuario + URL completa + filtros; compartir solicitudes idénticas en curso. TTL propuesto: catálogos 5 min, calendario 2 min, resumen/contexto 30 s. No hacer polling desde el reloj: se actualiza localmente. Invalidar tras POST/PATCH/DELETE exitoso y limpiar caché al cambiar usuario o cerrar sesión. Compartir los datos de las dos vistas de asistencia.

Mostrar “Datos de las 10:35” y acción Actualizar cuando se muestra una copia anterior. No confirmar escrituras offline ni reutilizar un GPS antiguo para marcar. Refrescar al volver a primer plano según caducidad, no en cada rebuild. No persistir GPS ni capacidades como autorización offline. Primero implementar caché en memoria; cualquier persistencia posterior debe estar aislada por cuenta y proteger datos personales.

## 9. Horas semanales

API y servicio WeeklyAttendanceSummaryService: semana de lunes a domingo en America/Lima, navegación entre semanas, total confirmado, minutos de refrigerio, tardanzas, días marcados y detalle diario. Calcular el tiempo neto desde los sellos y excluir filas incompletas del total confirmado.

Separar la jornada abierta como tiempo provisional “en curso”; no sumarla como trabajo ya confirmado ni inventar una hora de salida. Para turnos nocturnos, dividir los intervalos trabajados y refrigerios entre los límites de la semana: una jornada que cruza el domingo no debe duplicarse ni atribuirse completa a ambas semanas. La API devuelve as_of y zona horaria.

Mostrar horas y minutos, gráfico diario accesible, período y día en curso en Flutter y widgets Filament. Los cambios administrativos recalculan e invalidan ambos resúmenes. Horas extra solo se muestran cuando existe una regla de cálculo definida; no asumir que todo excedente es hora extra.

## 10. Archivos previstos

Backend nuevos: Models/{WorkSite,AttendanceRule,AttendanceMark}.php; Policies correspondientes; Services/{AttendanceLocationValidator,WeeklyAttendanceSummaryService,AttendanceQrService}.php; Controllers/Api/{WorkSiteController,AttendanceRuleController,WeeklyAttendanceSummaryController}.php; Request classes de marcación/configuración; Resources de Filament mencionados; widgets semanales; migraciones e índices; pruebas Feature.

Backend existentes: routes/api.php; Services/AttendanceService.php; Controllers/Api/{AuthController,AttendanceController}.php; Models/{User,Attendance}.php; Providers/AppServiceProvider.php; Policies/UserPolicy.php y AttendancePolicy.php; Resources/Users/{UserResource.php,Schemas/UserForm.php}; Resources/Concerns/HasRoleBasedNavigation.php; Pages/{DashboardEmpleado,OfficeQrPage}.php y vistas; config/{cache,database,filament-shield}.php.

Flutter nuevos: core/{location_service,api_cache,attendance_repository}.dart; features/work_sites/work_sites_screen.dart; features/attendance_rules/attendance_rules_screen.dart; features/attendance/weekly_summary_screen.dart; features/users/users_screen.dart. Reutilizar ResourceEditor para CRUD compatible; usar pantallas específicas para captura y marcación.

Flutter existentes: core/app_api.dart; features/attendance/attendance_screen.dart; features/dashboard/home_shell.dart; features/catalog/{catalog_screen,resource_screen}.dart; features/profile/profile_screen.dart; main.dart; pubspec.yaml; android/app/src/main/AndroidManifest.xml; ios/Runner/Info.plist y Podfile. Geolocator será una dependencia nueva; escoger una versión compatible y fijarla en el lock al implementar.

## 11. Orden de implementación y pruebas

1. Unificar autorización y alcance, conservar accesos legítimos y añadir pruebas de denegación.
2. Crear tablas, modelos, factories, policies, recursos Filament y formularios Flutter de sedes/reglas; asignar al menos una sede piloto antes de activar GPS obligatorio.
3. Validación central de GPS y QR, idempotencia, auditoría y nuevas rutas; conectar botones web y app.
4. Resúmenes semanales compartidos y vistas administrativas/completas por capacidades.
5. Caché Flutter, agregados Redis, invalidación, consultas e índices.
6. Validación en un teléfono real, compilación APK y despliegue coordinado de API/app. Migraciones de producción solo tras respaldo y comprobación del entorno; todavía no se aplican.

Casos de aceptación: dentro/fuera/al borde del radio; precisión mala, posición antigua, permiso denegado, GPS apagado, sede desactivada o ajena; QR válido sin ubicación y con GPS apagado, QR revocado o vencido; salida con actividad obligatoria/opcional; reintentos idénticos y simultáneos; acceso por web que no omita GPS; usuario sin permiso que llama API directamente; cambio de rol sin caché vieja; cuentas distintas sin datos compartidos; desconexión sin confirmación falsa; caída de Redis sin eludir reglas; semana vacía, pausas, domingo a lunes y turnos nocturnos; filtros/paginación; accesibilidad y reducir animaciones.

Medir consultas y solicitudes antes/después: cambiar pestañas durante el TTL no debe repetir GET idénticos, el reloj no produce tráfico y cada mutación obtiene una respuesta autoritativa. Ninguna sede ni regla se activa con coordenadas de ejemplo.

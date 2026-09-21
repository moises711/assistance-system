# Sistema de control de asistencia

## 1. Proposito

Este documento describe el comportamiento implementado actualmente en el proyecto Django `control_asistencia`. Es una fotografia del codigo existente: incluye las funciones disponibles, los roles, las vistas web, la API movil, las reglas de marcacion, los datos almacenados y las tareas operativas.

La aplicacion permite:

- Gestionar usuarios, roles, areas y horarios.
- Registrar entrada, inicio y fin de almuerzo y salida.
- Validar la ubicacion mediante GPS, radio de oficina o QR de oficina.
- Escanear el QR personal de un empleado desde RRHH.
- Controlar tardanzas, faltas, permisos, justificaciones y recuperaciones.
- Consultar reportes y exportarlos a Excel.
- Usar la misma logica desde navegador web o aplicacion movil mediante API.

## 2. Arquitectura actual

La solucion es una aplicacion Django monolitica con una app principal llamada `asistencia`.

### Componentes

- `control_asistencia/settings.py`: configuracion, base SQLite, zona horaria y seguridad.
- `control_asistencia/urls.py`: rutas globales, panel Django y service worker.
- `asistencia/models.py`: entidades y reglas basicas del dominio.
- `asistencia/views.py`: vistas HTML, acciones web y API administrativa.
- `asistencia/api_mobile.py`: API versionada para la aplicacion movil.
- `asistencia/api_auth.py`: lectura y validacion de tokens de dispositivo.
- `asistencia/middleware.py`: puente entre token Bearer y `request.user`.
- `asistencia/forms.py`: validacion de formularios web.
- `asistencia/utils.py`: calculo de horas, IP y distancia GPS.
- `asistencia/signals.py`: avisos por correo de tardanzas y justificaciones.
- `asistencia/admin.py`: administracion Django y configuracion GPS.
- `asistencia/management/commands/`: configuracion GPS y generacion de faltas.
- `asistencia/templates/asistencia/`: interfaz web por rol.
- `asistencia/static/asistencia/js/html5-qrcode.min.js`: lector QR del navegador.

Configuracion relevante:

- Usuario personalizado: `asistencia.CustomUser`.
- Idioma: `es-pe`.
- Zona horaria: `America/Lima`.
- Base de datos por defecto: `db.sqlite3`.
- Login web: `/login/`.
- Redireccion raiz: `/login/`.
- API movil: rutas bajo `/api/v1/`.
- API administrativa web: rutas bajo `/api/`.
- Los tokens moviles se envian como `Authorization: Bearer <token>` o `X-Api-Token`.

## 3. Roles y responsabilidades

Los roles se almacenan en `CustomUser.rol`.

| Codigo | Nombre | Capacidades observadas |
|---|---|---|
| `admin` | Administrador | Acceso al dashboard administrativo, configuracion GPS/IP, usuarios, areas, horarios, feriados, reportes y operaciones de RRHH. |
| `rrhh` | RRHH | Gestion de empleados y practicantes, validacion de asistencia, permisos, justificaciones, areas, horarios y reportes. |
| `supervisor` | Supervisor | Panel de control, consulta de reportes y revision de justificaciones de sus subordinados. |
| `empleado` | Empleado | Dashboard personal, marcacion, GPS/QR, actividad diaria, permisos, justificaciones y credenciales. |
| `pphh` | Practicante | Operacion de empleado mas control de actividad diaria y meta de horas de practica. |

### Redireccion despues del login

- `admin` -> `/admin-dashboard/`.
- `rrhh` -> `/dashboard/`.
- `supervisor` -> `/panel-control/`.
- `empleado` y `pphh` -> `/dashboard/`.

### Mecanismos de autorizacion

- `LoginRequiredMixin` y `@login_required`: exigen sesion web.
- `AdminRequiredMixin`: solo `admin`.
- `AdminOrRRHHRequiredMixin`: `admin` o `rrhh`.
- `ReporteAsistenciasView`: `admin`, `rrhh` o `supervisor`.
- `procesar_justificacion`: `admin`, `rrhh` o `supervisor`.
- `_require_admin_rrhh`: usado por las APIs administrativas de mantenimiento.
- El middleware de tokens autentica las rutas `/api/` con un `DispositivoToken`.

Nota: varias APIs administrativas usan `@login_required`, por lo que funcionan con sesion web y tambien pueden recibir el usuario movil cuando el middleware ya coloco `request.user`. La comprobacion de rol se hace dentro de cada funcion cuando corresponde.

## 4. Modelo de datos

### `CustomUser`

Extiende el usuario de Django. Campos propios: `rol`, `dni`, `supervisor`, `horario`, `area`, `permite_remoto` y `codigo_qr`.

- El DNI es unico.
- Un usuario puede tener un supervisor, un horario y un area.
- `permite_remoto=True` evita la validacion GPS para marcar.
- Al guardar sin codigo, genera un QR con UUID corto y los ultimos cuatro digitos del DNI.

### `Horario`

Define nombre, tipo (`fijo`, `flexible`, `rotativo`, `nocturno`), entrada, salida, tolerancia, tardanza maxima, permiso de entrada tardia y dias laborables de lunes a domingo.

Funciones del modelo:

- `hora_entrada_con_tolerancia()`: hora limite sumando la tolerancia.
- `es_laborable(fecha)`: indica si el dia esta habilitado.
- `duracion_jornada()`: calcula la jornada y admite turnos que cruzan medianoche.

### `RegistroAsistencia`

Es el registro diario por empleado. Tiene unicidad por `(empleado, fecha)`.

Almacena entrada, salida, inicio/fin de almuerzo, estado, tipo de marcacion, IP, coordenadas y precision de entrada/salida, horas netas, minutos tarde, minutos temprano y actividad diaria.

Estados: `a_tiempo`, `tardanza`, `falta`, `permiso`, `recuperacion`.

Tipos de marcacion: `gps`, `qr`, `manual`, `rrhh`.

### `ConfiguracionGPS`

Guarda nombre, latitud, longitud, radio permitido, estado activo y fechas de auditoria. `obtener_configuracion_activa()` devuelve la primera configuracion activa.

### `IpOficinaAutorizada`

Registra sedes, IP publica y estado activo. La accion `autorizar_ip_actual` crea o actualiza la IP que ve el servidor.

### `Area`

Catalogo de areas, con nombre unico y descripcion.

### `Justificacion`

Relaciona una explicacion con un `RegistroAsistencia`. Tiene motivo, aprobacion y fecha de creacion. Al crearse puede notificar al supervisor.

### `AusenciaProgramada`

Solicitud de permiso o ausencia con empleado, rango de fechas, motivo, estado (`pendiente`, `aprobada`, `rechazada`), creador y procesador. La propiedad `aprobada` conserva compatibilidad con codigo anterior.

### `MetaHorasPracticante`

Meta total de horas de practica por practicante, fechas de inicio/fin y valor requerido, por defecto 300 horas.

### `RecuperacionDia`

Deuda generada por una falta: empleado, fecha de falta, registro origen, horas a recuperar, fecha y horas recuperadas y estado (`pendiente`, `recuperado`, `excluido`).

### `DiaFeriado`

Fecha unica y descripcion. Los feriados bloquean la marcacion y evitan la generacion automatica de faltas.

### `DispositivoToken`

Token aleatorio de hasta 64 caracteres para la app movil. Guarda usuario, dispositivo, creacion y ultimo uso.

## 5. Flujo principal de asistencia

### Entrada

1. El usuario inicia sesion web o obtiene un token movil.
2. El sistema obtiene o crea el registro del dia.
3. Valida que no sea feriado ni tenga ausencia aprobada.
4. Valida que tenga horario y que el dia sea laborable. Una recuperacion pendiente permite marcar en un dia no laborable.
5. Si el usuario no permite remoto y la marcacion no es QR, exige coordenadas y configuracion GPS activa.
6. Calcula tardanza y llegada temprana.
7. El estado queda `a_tiempo`, `tardanza`, `falta` o `recuperacion` segun horario y recuperacion.
8. Guarda hora, IP, coordenadas, precision y tipo de entrada.

### Almuerzo

- `inicio_almuerzo` exige entrada y no permite duplicarla.
- `fin_almuerzo` exige inicio y no permite duplicarlo.

### Salida

1. Exige que exista entrada y que no exista salida.
2. Empleados y practicantes deben enviar `actividad`.
3. Guarda hora, IP, coordenadas y tipo de salida.
4. Calcula `horas_netas_trabajadas` mediante `calcular_horas_netas`.
5. Si el registro era de recuperacion, cierra la deuda pendiente con fecha y horas recuperadas.

La secuencia disponible es `entrada` -> `inicio_almuerzo` -> `fin_almuerzo` -> `salida`; almuerzo es opcional, pero sus pasos deben respetar el orden.

## 6. GPS, QR y red

### GPS

`validar_ubicacion_gps` usa la formula de Haversine y compara la distancia con el radio permitido de `ConfiguracionGPS`.

- Usuarios remotos: no requieren GPS.
- Usuarios presenciales: requieren coordenadas para entrada no QR.
- La precision se registra, pero no cambia el resultado del radio.
- La configuracion puede capturarse desde `/capturar-gps/`, guardarse desde `/guardar-gps/` o configurarse con el comando `configurar_gps`.

### QR de oficina

`obtener_codigo_qr_oficina` firma un payload con `django.core.signing` y el salt `oficina-qr-v1`. El payload incluye configuracion, coordenadas y radio.

- `/descargar-qr-oficina/` genera la imagen del QR.
- `/validar-qr-oficina/` comprueba firma y que el `config_id` coincida con la oficina activa.
- Una marcacion tipo QR puede evitar el bloqueo GPS de entrada, pero sigue pasando validaciones de feriado y horario.

### QR personal del empleado

El empleado muestra `codigo_qr` desde su dashboard. RRHH usa el lector `html5-qrcode` y `/api/escanear-qr/` para localizar al empleado y registrar entrada o salida manual/QR.

## 7. Vistas web y funciones principales

### Autenticacion y cuenta

- `CustomLoginView`: login con `LoginThrottleForm` y redireccion por rol.
- `LogoutView`: cierre de sesion hacia login.
- `RegisterView`: registro publico mediante `PublicRegistroForm`.
- `CredencialesAccesoView`: actualiza usuario/correo y cambia contrasena sin cerrar la sesion.
- `RoleAwarePasswordChangeView`: cambia contrasena y vuelve al dashboard del rol.
- Vistas Django de recuperacion: `password_reset`, confirmacion y finalizacion.

### Dashboards

- `DashboardView`: dashboard personal, reloj, jornada, horas semanales, acciones de marcacion, QR personal, permisos y credenciales.
- `PanelControlView`: panel para supervisor con subordinados, registros y seguimiento.
- `AdminDashboardView`: metricas administrativas de empleados, presentes, tardanzas, faltas, permisos, justificaciones y horas por periodo.
- `ActividadesEmpleadosView`: consulta de actividades diarias reportadas.

### Marcacion y ubicacion

- `marcar_evento(request, accion)`: controlador web de entrada, almuerzo y salida.
- `validar_gps`: valida coordenadas contra la oficina activa.
- `validar_qr_oficina`: valida el QR firmado de oficina.
- `capturar_gps_admin`: muestra captura y configuracion GPS.
- `guardar_gps_admin`: crea o actualiza coordenadas, radio y nombre.
- `descargar_qr_oficina`: devuelve el QR de oficina, con opcion de descarga.
- `escanear_qr_empleado`: procesa QR personal para operaciones de RRHH.
- `calcular_tardanza_y_temprano`: calcula minutos tarde y minutos temprano.

### Personal y configuracion

- `EmpleadosView`: crea empleados/practicantes y lista usuarios de esos roles.
- `eliminar_empleado`: elimina usuarios bajo restricciones de rol y proteccion del propio usuario/admin.
- `ConfigIpView`: registra una IP autorizada desde formulario.
- `autorizar_ip_actual`: autoriza la IP publica actual para admin.
- `AreasView`: crea/lista areas.
- `HorariosView`: crea/lista horarios.
- `actualizar_empleado_api`: actualiza datos de un empleado por JSON.
- `api_horarios`, `api_horario_detalle`, `actualizar_horario_api`: CRUD parcial de horarios.
- `api_areas`, `api_area_detalle`, `actualizar_area_api`: CRUD parcial de areas.
- `api_feriados`, `api_feriado_detalle`, `crear_feriado`, `eliminar_feriado`: mantenimiento de feriados.

### Permisos, justificaciones y reportes

- `solicitar_permiso_empleado`: crea solicitud de ausencia del usuario autenticado.
- `crear_ausencia_programada`: crea una ausencia desde gestion administrativa.
- `procesar_ausencia`: aprueba o rechaza una ausencia y registra quien la proceso.
- `JustificacionCreateView`: crea justificacion para el propio registro, subordinado o gestion administrativa.
- `JustificacionesPendientesView`: muestra pendientes para admin/RRHH o para el supervisor correspondiente.
- `procesar_justificacion`: aprueba o elimina una justificacion.
- `ReporteAsistenciasView`: filtra por fecha, empleado y area.
- `exportar_reporte_excel`: genera archivo XLSX del reporte.
- `api_reporte_asistencias` y `api_exportar_reporte_asistencias`: equivalentes JSON/archivo para consumo programatico.

### Funciones auxiliares de `views.py`

- Vistas de error: `bad_request_view`, `permission_denied_view`, `page_not_found_view`, `server_error_view`.
- QR y redireccion: `obtener_codigo_qr_oficina`, `obtener_redirect_por_rol`.
- Presentacion: `formatear_duracion`, `mensajes_validation_error`, `service_worker`.
- Reglas previas: `requiere_validacion_gps`, `validar_gps_para_marcacion`, `obtener_recuperacion_pendiente`, `validar_horario_para_marcacion`, `validar_dia_sin_permiso_ni_feriado`.
- Serializacion y conversion: `_parse_json_body`, `_as_time_iso`, `_as_date_iso`, `_as_bool`, `_serialize_horario`, `_serialize_area`, `_serialize_empleado`, `_serialize_feriado`, `_serialize_justificacion`, `_serialize_ausencia`.
- Autorizacion: `_require_admin_rrhh`.

## 8. API movil `/api/v1/`

### Autenticacion

- `POST /api/v1/auth/login/`: autentica por usuario, email o username y crea token.
- `POST /api/v1/auth/logout/`: elimina el token recibido.
- Las respuestas incluyen CORS abierto y aceptan `OPTIONS`.

### Operacion del empleado

- `GET /api/v1/dashboard/`: usuario, horario, registro del dia, proxima accion, GPS, horas semanales, ultimos registros y permisos.
- `POST /api/v1/gps/validar/`: devuelve configuracion o valida distancia/precision.
- `POST /api/v1/qr-oficina/validar/`: valida QR firmado de oficina.
- `POST /api/v1/marcar/<accion>/`: ejecuta `entrada`, `inicio_almuerzo`, `fin_almuerzo` o `salida` usando la misma regla central.
- `POST /api/v1/permisos/`: crea solicitud de permiso.
- `GET /api/v1/permisos/listar/`: lista permisos propios, con filtro opcional por estado.

### Operacion de RRHH en movil

- `GET /api/v1/rrhh/resumen/`: resumen del dia de empleados/practicantes y permisos pendientes.
- `POST /api/v1/rrhh/permisos/<id>/`: aprueba o rechaza una ausencia.

Funciones internas destacadas de `api_mobile.py`:

- `_cors`, `_json`, `_error`, `_leer_json`: respuesta y lectura JSON.
- `_token_desde_request`, `_usuario_por_token`: acceso a autenticacion movil.
- `_fmt_dt`, `_fmt_time`, `_td_format`: formatos de fechas, horas y duraciones.
- `_serializar_usuario`, `_serializar_registro`, `_serializar_permiso`: contratos JSON.
- `_proxima_accion`, `_dashboard_payload`: estado del dashboard.
- `_ejecutar_marcacion`: implementacion movil de entrada, almuerzo y salida.
- `_es_staff_rrhh`: comprueba admin/RRHH.

## 9. API administrativa `/api/`

Rutas disponibles actualmente:

- `/api/empleados/` y `/api/empleados/<id>/`.
- `/api/empleado/<id>/actualizar/`.
- `/api/horarios/` y `/api/horarios/<id>/`.
- `/api/horario/<id>/actualizar/`.
- `/api/areas/` y `/api/areas/<id>/`.
- `/api/area/<id>/actualizar/`.
- `/api/feriados/` y `/api/feriados/<id>/`.
- `/api/reportes/asistencias/` y `/api/reportes/asistencias/exportar/`.
- `/api/justificaciones/` y `/api/justificaciones/<id>/procesar/`.
- `/api/ausencias/` y `/api/ausencias/<id>/procesar/`.

Las funciones de serializacion convierten horarios, areas, empleados, feriados, justificaciones y ausencias a JSON. Los metodos aceptados dependen de cada vista y normalmente son `GET`, `POST`, `PUT`, `PATCH` o `DELETE`.

## 10. Formularios

- `LoginThrottleForm`: autentica y limita intentos repetidos mediante cache.
- `CredencialesAccesoForm`: cambia datos de acceso tras validar contrasena actual.
- `JustificacionForm`: motivo de una justificacion.
- `IpOficinaAutorizadaForm`: sede e IP publica.
- `AusenciaProgramadaForm`: fechas, motivo y validaciones del rango.
- `EmpleadoCreationForm`: alta administrativa de empleados/practicantes.
- `PublicRegistroForm`: alta publica.
- `AreaForm`: alta de area.
- `HorarioForm`: alta de horario.
- `DiaFeriadoForm`: alta de feriado.
- `MetaHorasPracticanteForm`: meta de horas del practicante.

## 11. Notificaciones y tareas operativas

### Signals

- `notificar_tardanza`: al guardar un registro en estado tardanza, envia correo al supervisor si tiene email.
- `notificar_justificacion`: al crear una justificacion, envia correo al supervisor si tiene email.

Los correos usan SMTP de Gmail configurado por `EMAIL_HOST_USER`, `EMAIL_HOST_PASSWORD` y `DEFAULT_FROM_EMAIL`. Los errores de correo se silencian.

### Comandos de gestion

`python manage.py configurar_gps --latitud ... --longitud ... [--nombre ...] [--radio ...]`

- Desactiva configuraciones activas anteriores.
- Crea o actualiza la configuracion indicada.

`python manage.py verificar_faltas`

- Revisa empleados activos con rol `empleado`.
- Omite feriados, dias no laborables y ausencias aprobadas.
- Crea o actualiza falta del dia.
- Crea deuda `RecuperacionDia` usando la duracion del horario.

Este comando debe ser ejecutado por un programador externo, cron o tarea de despliegue si se desea generar faltas automaticamente; no hay evidencia en el codigo revisado de una tarea programada interna.

## 12. Flujo de permisos y justificaciones

### Permiso o ausencia

1. Empleado/practicante envia fechas y motivo.
2. Se crea `AusenciaProgramada` en estado `pendiente`.
3. RRHH o admin la aprueba/rechaza desde web o API movil.
4. Una ausencia aprobada bloquea la marcacion de ese dia y evita que `verificar_faltas` genere falta.

### Justificacion

1. Se crea vinculada a un registro de asistencia.
2. Puede verla admin/RRHH o el supervisor del empleado.
3. El responsable aprueba o rechaza.
4. Aprobar cambia `aprobada`; rechazar elimina el objeto. No se observa un estado de asistencia especifico para justificada.

## 13. Interfaz disponible

Plantillas principales:

- `login.html`, `register.html`: acceso y registro.
- `dashboard.html`: empleado/practicante.
- `dashboard_rrhh.html`: validacion y escaneo QR.
- `panel_control.html`: supervisor.
- `admin_dashboard.html`: administrador.
- `empleados.html`, `areas.html`, `horarios.html`: mantenimiento.
- `reporte.html`: reportes.
- `justificacion_form.html`, `justificaciones_pendientes.html`: justificaciones.
- `actividades_empleados.html`: actividades.
- `capturar_gps_admin.html`: GPS y QR de oficina.
- `credenciales_acceso.html`, `password_change*.html`: credenciales.
- `base.html`, `base_admin.html`: layouts comunes.

El frontend usa JavaScript embebido en las plantillas para reloj, geolocalizacion, fetch, modales y lector QR. La libreria local de lectura es `html5-qrcode`.

## 14. Rutas web principales

### Acceso y dashboards

`/login/`, `/logout/`, `/register/`, `/dashboard/`, `/panel-control/`, `/admin-dashboard/`.

### Asistencia

`/marcar/<accion>/`, `/validar-gps/`, `/validar-qr-oficina/`, `/capturar-gps/`, `/guardar-gps/`, `/qr-oficina/descargar/`, `/api/escanear-qr/`.

### Administracion

`/empleados/`, `/empleados/<id>/eliminar/`, `/config-ip/`, `/autorizar-ip/`, `/areas/`, `/horarios/`, `/feriados/crear/`, `/feriados/<id>/eliminar/`, `/actividades/`.

### Reportes y solicitudes

`/asistencias/reporte/`, `/asistencias/reporte/excel/`, `/justificaciones/pendientes/`, `/justificaciones/procesar/<id>/`, `/asistencias/<id>/justificar/`, `/ausencias/crear/`, `/ausencias/<id>/procesar/`, `/permisos/solicitar/`.

### Credenciales

`/credenciales/`, `/password/change/`, `/password/change/done/`, `/password_reset/`, `/reset/<uidb64>/<token>/`, `/reset/done/`.

## 15. Estado actual y observaciones tecnicas

Estas observaciones se derivan del codigo actual y deben considerarse al mantener el sistema:

1. `calcular_horas_netas` recibe datos de almuerzo, pero actualmente calcula solo `salida - entrada`; no descuenta el intervalo de almuerzo.
2. `verificar_faltas` procesa automaticamente el rol `empleado`, pero no el rol `pphh`.
3. El control de `tardanza_maxima_minutos` y `permite_entrada_tardia` existe en el modelo, pero la validacion principal visible usa la tolerancia del horario.
4. La aprobacion de una justificacion no cambia el estado del registro a un estado nuevo; solo marca `Justificacion.aprobada=True`.
5. La API movil tiene CORS abierto (`*`); debe revisarse antes de un despliegue con datos sensibles.
6. La creacion de tokens movil no muestra expiracion automatica ni limite de dispositivos; cerrar sesion elimina el token actual.
7. La generacion de faltas depende de ejecutar `verificar_faltas`; no se observa un scheduler integrado.
8. El envio de correo falla silenciosamente, por lo que una notificacion no garantiza que el supervisor haya sido informado.
9. El dashboard semanal usa el rango de lunes hasta hoy y controla el rango de referencia de 24 a 30 horas.
10. Las respuestas y mensajes de usuario se mezclan entre textos con y sin tildes; conviene normalizar idioma y nomenclatura en una futura pasada.

## 16. Pruebas existentes

`asistencia/tests.py` cubre, entre otros casos:

- Tolerancia de entrada de horarios.
- Entrada justo en el limite, antes y despues del limite.
- Restriccion de exportacion Excel para empleados.
- Exportacion Excel autorizada para admin.
- Reporte, justificaciones y ausencias por API.
- Listado de empleados por API.

Comando recomendado para validar el estado del proyecto:

```bash
python manage.py check
python manage.py test
```

## 17. Archivos de referencia

- `asistencia/models.py`: dominio y persistencia.
- `asistencia/views.py`: reglas y vistas web/API administrativa.
- `asistencia/api_mobile.py`: API movil.
- `asistencia/api_auth.py` y `asistencia/middleware.py`: tokens y autenticacion movil.
- `asistencia/forms.py`: validaciones de formularios.
- `asistencia/utils.py`: calculos GPS, horas e IP.
- `asistencia/signals.py`: notificaciones.
- `asistencia/urls.py`: mapa completo de rutas.
- `asistencia/admin.py`: panel de administracion.
- `asistencia/management/commands/`: tareas manuales/operativas.
- `asistencia/templates/asistencia/`: pantallas web.

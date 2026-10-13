# Sprint 1 — Acceso y personas

Este documento describe el código actual del Sprint 1, incluido el correctivo aprobado de HU-03. Sus pruebas automatizadas se ejecutaron exclusivamente en `sga_rio_grande_testing`; el 13 de octubre de 2026 se aplicaron sus cinco migraciones a la base principal con autorización específica y respaldo previo. Consulta la [evidencia y trazabilidad del correctivo](sprint-1-hu03-corrective.md) y la [validación inicial](sprint-1-validation.md).

## HU-01 — Autenticación segura

- Correo normalizado y contraseña; solo usuarios y roles activos.
- Cinco intentos fallidos por correo e IP durante 60 segundos. El acceso correcto limpia el contador.
- Regeneración del identificador de sesión tras ingresar.
- Cierre mediante POST con protección CSRF, invalidación de sesión y token renovado.
- Sesiones vinculadas a `users.session_version`, `role_id` y `roles.permissions_version`: los cambios de correo, contraseña, rol, estado o permisos revocan accesos anteriores, incluso tras reactivar cuentas o roles. La siguiente solicitud comprueba valores actuales de la base.
- Panel protegido, sin registro público, recuperación por correo ni API en este Sprint.
- Blade y CSS servido directamente, sin npm ni Vite.

## HU-03 — Roles y permisos

- Un rol obligatorio por usuario; se pueden crear roles adicionales con código estable, nombre, descripción y estado configurables.
- Los tres roles existentes se conservan como configuración inicial; no se reasignan usuarios ni se conceden permisos a roles desconocidos.
- Catálogo de 17 permisos por acción, con códigos únicos, relaciones Eloquent y tabla `permission_role` con claves foráneas y asociación única.
- Pantallas administrativas `/roles` y `/auditoria`. La asignación de permisos modifica enlaces activos; las revocaciones conservan los enlaces históricos.
- Policies y Gates consultan permisos, sin privilegios implícitos por nombres o códigos de rol. No hay excepción de superadministrador.
- No se pueden conceder permisos propios ausentes ni modificar cuentas o roles con capacidades superiores a las del operador.
- Auditoría básica de creación y cambios de cuentas, roles y permisos, con operador, fecha, origen, IP y valores anteriores/posteriores. Nunca incluye contraseñas ni hashes.
- Una transacción serializa cambios administrativos y comprueba nuevamente el acceso del operador. Impide perder la última cuenta activa con los 12 permisos de administración completos, incluso en roles personalizados.
- Los usuarios o roles inactivos carecen de permisos efectivos. Un rol nuevo sin permisos no obtiene acceso por su nombre.

## HU-02 — Gestión de usuarios

- Los permisos `users.*` controlan consulta, creación, edición, estado, asignación de rol y contraseña. Inicialmente los posee el Administrador.
- Correo único y normalizado; contraseña confirmada de al menos 12 caracteres, con mayúscula, minúscula y número, almacenada con hash.
- Contraseña vacía en edición conserva la existente.
- Rol válido y estado booleano obligatorios; campos adicionales de solicitudes ignorados.
- Desactivación reversible, sin borrado. No se puede desactivar ni degradar al último administrador activo.
- Creación y actualización en transacción: bloqueo común del catálogo de permisos, nueva autorización del operador y verificación del último acceso administrativo. Los datos y su auditoría se confirman juntos.
- Listado paginado de 20 registros, búsqueda por nombre/correo y filtro de estado.
- Comando interactivo `sga:create-admin` para la primera cuenta, sin credenciales predeterminadas ni sobrescrituras.

## HU-04 — Gestión de trabajadores

- Código interno, nombres, apellidos, documento opcional, cargo opcional, área opcional y estado.
- Código obligatorio, normalizado a mayúsculas y único. Documento completo opcional, normalizado a mayúsculas y único cuando se informa; admite complemento sin imponer un patrón de CI.
- Sin relación de cuenta, contraseña o correo obligatorio del trabajador.
- Los permisos `workers.*` controlan consulta, creación, edición y estado de forma independiente. Inicialmente Administrador y Encargado pueden escribir; Consulta/Auditor solo consulta.
- Antes de guardar trabajadores también se revalida el acceso dentro de la transacción común; una revocación ocurrida durante la solicitud bloquea la escritura.
- `created_by` y `updated_by` identifican operadores, asignados desde la sesión. No se aceptan desde formularios. Las claves foráneas preservan esas referencias.
- Listado paginado, búsqueda por código, nombres, apellidos, documento, cargo o área, y filtro de estado.
- Activación y desactivación reversibles, sin rutas de eliminación.

## Esquema y validación

Las migraciones añaden `is_active` y `session_version` a `users`, crean `roles`, añaden `users.role_id` y crean `workers`. Los trabajadores no tienen `user_id`; las referencias a usuarios solo describen quién realizó la operación.

El correctivo añade cinco migraciones nuevas: campos de seguridad y autoría en `roles`, tablas `permissions`, `permission_role`, `administrative_audit_logs`, e inicialización del catálogo. Las siete migraciones anteriores permanecen intactas. El detalle físico y los resultados actuales están en [el informe del correctivo](sprint-1-hu03-corrective.md).

La asignación del rol obligatorio exige que `users` esté vacío. Si encuentra cuentas anteriores, la migración se detiene antes de añadir la columna: no concede privilegios automáticamente.

Las pruebas se ejecutan contra MariaDB, verificando permisos mediante solicitudes directas, validaciones, sesiones, CSRF, contraseñas, trazabilidad, unicidad y claves foráneas. Usan una base exclusiva, comprobaciones de aislamiento y transacciones.

Las pantallas y mensajes están en español. Blade escapa los datos mostrados. Las colisiones de unicidad durante guardado también se convierten en mensajes de validación.

## Configuración local

La clave `APP_KEY` inicialmente vacía se generó con autorización específica; se verificó que fuera el único cambio en `.env` y que la pantalla de acceso respondiera HTTP 200. Apache de XAMPP utiliza PHP 8.2.12: para ejecutar Laravel 13, usa PHP 8.3 de Laragon mediante `php artisan serve`. No se requieren actualizaciones de herramientas.

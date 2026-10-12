# Sprint 1 — Acceso y personas

## HU-01 — Autenticación segura

- Correo normalizado y contraseña; solo usuarios activos.
- Cinco intentos fallidos por correo e IP durante 60 segundos. El acceso correcto limpia el contador.
- Regeneración del identificador de sesión tras ingresar.
- Cierre mediante POST con protección CSRF, invalidación de sesión y token renovado.
- Sesiones vinculadas a `users.session_version`: los cambios de correo, contraseña, rol o estado revocan accesos anteriores, incluso tras reactivar cuentas.
- Panel protegido, sin registro público, recuperación por correo ni API en este Sprint.
- Blade y CSS servido directamente, sin npm ni Vite.

## HU-03 — Roles y permisos

- Un rol obligatorio por usuario: `administrador`, `encargado`, `consulta_auditor`.
- Catálogo fijo, códigos únicos y claves foráneas.
- Policies para usuarios y trabajadores; Gate para el panel.
- Autorización en el servidor y opciones visibles según permisos.
- Los usuarios inactivos y roles ajenos al catálogo no reciben permisos.

## HU-02 — Gestión de usuarios

- Solo el Administrador puede listar, buscar, crear, editar o cambiar estados.
- Correo único y normalizado; contraseña confirmada de al menos 12 caracteres, con mayúscula, minúscula y número, almacenada con hash.
- Contraseña vacía en edición conserva la existente.
- Rol válido y estado booleano obligatorios; campos adicionales de solicitudes ignorados.
- Desactivación reversible, sin borrado. No se puede desactivar ni degradar al último administrador activo.
- Actualización en transacción: bloqueo de administradores activos en orden de ID y nueva autorización del operador; se verifica que su acceso no cambió durante la solicitud.
- Listado paginado de 20 registros, búsqueda por nombre/correo y filtro de estado.
- Comando interactivo `sga:create-admin` para la primera cuenta, sin credenciales predeterminadas ni sobrescrituras.

## HU-04 — Gestión de trabajadores

- Código interno, nombres, apellidos, documento opcional, cargo opcional, área opcional y estado.
- Código obligatorio, normalizado a mayúsculas y único. Documento completo opcional, normalizado a mayúsculas y único cuando se informa; admite complemento sin imponer un patrón de CI.
- Sin relación de cuenta, contraseña o correo obligatorio del trabajador.
- Administrador y Encargado pueden escribir; Consulta/Auditor solo lista y ve detalles.
- `created_by` y `updated_by` identifican operadores, asignados desde la sesión. No se aceptan desde formularios. Las claves foráneas preservan esas referencias.
- Listado paginado, búsqueda por código, nombres, apellidos, documento, cargo o área, y filtro de estado.
- Activación y desactivación reversibles, sin rutas de eliminación.

## Esquema y validación

Las migraciones añaden `is_active` y `session_version` a `users`, crean `roles`, añaden `users.role_id` y crean `workers`. Los trabajadores no tienen `user_id`; las referencias a usuarios solo describen quién realizó la operación.

La asignación del rol obligatorio exige que `users` esté vacío. Si encuentra cuentas anteriores, la migración se detiene antes de añadir la columna: no concede privilegios automáticamente.

Las pruebas se ejecutan contra MariaDB, verificando permisos mediante solicitudes directas, validaciones, sesiones, CSRF, contraseñas, trazabilidad, unicidad y claves foráneas. Usan una base exclusiva, comprobaciones de aislamiento y transacciones.

Las pantallas y mensajes están en español. Blade escapa los datos mostrados. Las colisiones de unicidad durante guardado también se convierten en mensajes de validación.

## Configuración local

La clave `APP_KEY` inicialmente vacía se generó con autorización específica; se verificó que fuera el único cambio en `.env` y que la pantalla de acceso respondiera HTTP 200. Apache de XAMPP utiliza PHP 8.2.12: para ejecutar Laravel 13, usa PHP 8.3 de Laragon mediante `php artisan serve`. No se requieren actualizaciones de herramientas.

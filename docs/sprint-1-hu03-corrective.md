# Sprint 1 — Correctivo HU-03: roles y permisos configurables

Implementación y pruebas: 12 de octubre de 2026, America/La_Paz. Migración principal autorizada y ejecutada: 13 de octubre de 2026.

Estado: código implementado bajo aprobación del plan, pruebas aprobadas en `sga_rio_grande_testing` y cinco migraciones nuevas aplicadas a `sga_rio_grande` con autorización específica del usuario. El 13 de octubre de 2026 el usuario también autorizó el commit y push del correctivo y su documentación a `main`. No se desarrolló el Sprint 2.

La [validación inicial](sprint-1-validation.md) describe el estado anterior del commit `3a0045b83060fea8d7b85e72aa077996306d4224`. El correctivo parte de `main`, commit `6eb9815dd05e97e243e4f9af98288ba18c44e7a5`, y conserva autenticación, usuarios y trabajadores.

## Comportamiento implementado

- Un rol obligatorio por usuario. `/roles` permite crear roles, editar nombre/descripción, cambiar estado y configurar permisos por acción.
- Código de rol único y estable; nombre de hasta 80 caracteres y descripción de hasta 2000. Los códigos nuevos usan letras minúsculas, números y guion bajo.
- Catálogo de 17 permisos que corresponden a funcionalidades implementadas. No hay un formulario para inventar permisos sin una funcionalidad asociada.
- Policies y Gates consultan permisos activos. Los nombres de roles no conceden acceso y no existe un bypass de Administrador.
- Los roles nuevos sin permisos carecen de acceso. Se conservan los tres roles iniciales y sus identificadores. No se crean usuarios, no se reasignan cuentas ni se conceden permisos a roles desconocidos.
- Un operador solo puede conceder permisos que posee; no puede modificar cuentas o roles con capacidades superiores a las propias. Este control también protege cambios de contraseña y asignación de rol.
- No se eliminan roles, usuarios, trabajadores ni enlaces revocados desde la aplicación. Desactivar un rol conserva sus asignaciones y bloquea el ingreso de sus usuarios.
- Auditoría administrativa de creación, edición, cambio de estado, asignación de rol, cambio de contraseña y configuración de permisos. `/auditoria` permite consulta paginada y búsqueda por operador, acción, rol o cuenta.

## Catálogo y configuración inicial

| Código | Capacidad | Administrador | Encargado | Consulta/Auditor |
|---|---|---|---|---|
| `panel.view` | Abrir panel | Sí | Sí | Sí |
| `users.view` | Consultar usuarios | Sí | No | No |
| `users.create` | Crear usuarios | Sí | No | No |
| `users.update` | Editar nombre y correo | Sí | No | No |
| `users.change_status` | Activar/desactivar usuarios | Sí | No | No |
| `users.assign_role` | Asignar rol a usuarios | Sí | No | No |
| `users.change_password` | Cambiar contraseñas | Sí | No | No |
| `roles.view` | Consultar roles | Sí | No | No |
| `roles.create` | Crear roles | Sí | No | No |
| `roles.update` | Editar nombre/descripción de roles | Sí | No | No |
| `roles.change_status` | Activar/desactivar roles | Sí | No | No |
| `roles.assign_permissions` | Configurar permisos de roles | Sí | No | No |
| `workers.view` | Consultar trabajadores | Sí | Sí | Sí |
| `workers.create` | Crear trabajadores | Sí | Sí | No |
| `workers.update` | Editar trabajadores | Sí | Sí | No |
| `workers.change_status` | Activar/desactivar trabajadores | Sí | Sí | No |
| `audit.view` | Consultar auditoría administrativa | Sí | No | Sí |

La creación de un usuario requiere `users.create` y `users.assign_role`, porque el rol es obligatorio. El formulario general de edición requiere `users.update`; los cambios de contraseña, rol o estado exigen además su permiso específico. El estado tiene un endpoint independiente. La edición general de roles admite operadores autorizados para datos, permisos o estado, verificando cada campo modificado. Para navegar por listados y sus redirecciones debe concederse el permiso de consulta correspondiente. `panel.view` controla únicamente el panel.

## Esquema aditivo y efectos de la migración principal

| Migración nueva | Cambios |
|---|---|
| `2026_10_12_000001_add_security_fields_to_roles_table` | Añade `description` nullable, `is_active` por defecto true, `permissions_version` por defecto 1, `created_by` y `updated_by` nullable con FK a usuarios y eliminación restringida. |
| `2026_10_12_000002_create_permissions_table` | Crea `permissions`: ID, código único de hasta 80, nombre de hasta 120, descripción nullable y timestamps. |
| `2026_10_12_000003_create_permission_role_table` | Crea asociación con ID, FKs restringidas a rol/permiso, estado activo y timestamps; unicidad `(role_id, permission_id)`. |
| `2026_10_12_000004_create_administrative_audit_logs_table` | Crea eventos con operador y nombre histórico, sujeto usuario o rol, acción, origen, IP, antes/después y fecha; FKs restringidas y CHECK que exige exactamente un sujeto. Índices para orden y acción. |
| `2026_10_12_000005_initialize_existing_role_permissions` | Carga los 17 permisos y 25 asociaciones iniciales en los tres roles conocidos, con tres eventos de auditoría de inicialización. No modifica usuarios ni trabajadores. |

La inicialización omite roles desconocidos y roles que ya tengan enlaces, incluso si fueron revocados. Los seeders aseguran registros del catálogo sin conceder ni restaurar permisos. Los valores antes/después se serializan como JSON en columnas TEXT mediante casts Eloquent, sin depender del comportamiento de un tipo JSON nativo. Tablas InnoDB, índices cortos y FKs compatibles con MariaDB 10.4.32.

Las siete migraciones previamente ejecutadas permanecen intactas. Las tres tablas adicionales llevaron la base principal de 11 a 14 tablas y de siete a doce migraciones registradas. Se conservaron los tres roles, cero usuarios y cero trabajadores observados. Las filas nuevas de permisos y auditoría no contienen datos de credenciales.

## Protección del acceso y revocación

`SecurityMutation` adquiere `lockForUpdate` sobre el permiso estable `roles.assign_permissions` antes de cualquier mutación de usuarios, roles o trabajadores. Después recarga el operador, comprueba actividad, rol y versiones, y exige permisos por acción. La primera cuenta administrativa explícita utiliza el mismo bloqueo.

Al finalizar una mutación administrativa debe existir al menos una cuenta activa con rol activo y los **12 permisos** de `PermissionCatalog::ADMINISTRATIVE_ACCESS`: panel, seis acciones de usuarios y cinco de roles. La comprobación usa capacidades, incluso si el rol tiene otro código. Dos usuarios en el mismo rol no permiten quitar al rol el último acceso completo. Si la comprobación falla, se revierte la modificación y su evento de auditoría. El guard no crea cuentas ni restaura permisos automáticamente.

Cada sesión almacena `auth_version`, `auth_role_id` y `auth_role_version`. El middleware recarga usuario y rol en cada solicitud. El cambio de permisos o estado incrementa `roles.permissions_version`; los cambios de acceso de una cuenta incrementan `users.session_version`. Una sesión anterior se invalida en su siguiente solicitud protegida, incluso si el permiso se concedió, el usuario se reactivó o el rol se reactivó. Un nuevo ingreso obtiene los permisos actuales. Cambiar solo el nombre o descripción del rol no revoca sesiones.

No se utiliza caché persistente de permisos. La acción de trabajadores también vuelve a comprobar el acceso dentro de la transacción para impedir una escritura autorizada antes de una revocación concurrente. Conserva los campos `created_by`/`updated_by` existentes; no exige que haya un administrador para una operación que no modifica acceso administrativo.

## Auditoría y mensajes

`AdministrativeAudit` registra actor, nombre en el momento del evento, cuenta o rol afectado, acción, origen, IP para web, fecha y valores anteriores/posteriores. Los cambios de permisos incluyen concesiones y revocaciones; las contraseñas solo generan una bandera `password_changed`. No se guardan valores de contraseña, hashes, tokens ni claves de aplicación.

La auditoría se escribe en la misma transacción que el cambio administrativo. Los formularios y mensajes están en español; se añadieron mensajes de `in`, `array`, `distinct`, `prohibited` y `regex`. Blade escapa contenido de roles, cuentas y auditoría. No hay rutas de edición/eliminación de eventos y el modelo bloquea esas operaciones. Es auditoría básica de aplicación: un acceso directo privilegiado a MariaDB queda fuera de esta protección.

## Trazabilidad entre historias, requisitos, código y pruebas

| Historia / requisito | Código principal | Evidencia automatizada |
|---|---|---|
| HU-01: ingreso seguro, sesiones y revocación | `LoginRequest`, `AuthenticatedSessionController`, `EnsureUserIsActive` | `AuthenticationTest`; `PermissionAuthorizationTest`: roles inactivos, sesiones antiguas, concesión/revocación y reactivación. |
| HU-03: crear, editar y desactivar roles | `RoleRequest`, `RoleStatusRequest`, `RoleController`, `SaveRole`, vistas `roles` | `RbacManagementTest`: creación, autoría, edición, validación, código estable, estados, búsqueda y ausencia de borrado. |
| HU-03: permisos por acción y ausencia de escalada | `PermissionCatalog`, modelos `Role`/`Permission`/`User`, Policies, Gates, `SecurityMutation` | `PermissionAuthorizationTest`: acciones independientes, petición directa, roles personalizados, campos manipulados, capacidades superiores y revocación durante solicitud. |
| HU-03: último acceso completo | `SecurityMutation`, acciones de roles/usuarios, `CreateAdministrator` | `RbacManagementTest`: eliminación de cada permiso mínimo, desactivación, cuentas en el mismo rol y administrador personalizado; `AdministrativeConcurrencyTest`: dos procesos independientes. |
| HU-03: auditoría básica | `AdministrativeAudit`, `AdministrativeAuditLog`, Policy, controlador y vista de auditoría | `AdministrativeAuditTest`: antes/después, actor, secretos excluidos, escape HTML y lectura autorizada; `RbacManagementTest`: fallo de auditoría revierte el cambio. |
| HU-02: usuarios y roles configurables | `UserRequest`, `UserPolicy`, `CreateUser`, `UpdateUser`, `UserController`, comando inicial y vistas | `UserManagementTest`; `RbacManagementTest`: asignación de rol personalizado e imposibilidad de activar cuentas con rol inactivo. |
| HU-04: trabajadores independientes y permisos | `WorkerRequest`, `WorkerStatusRequest`, `WorkerPolicy`, `SaveWorker`, controlador y vistas | `WorkerManagementTest`; `PermissionAuthorizationTest`: edición separada del estado y rechazo de escritura con acceso revocado. |
| Integridad, compatibilidad y conservación | Cinco migraciones aditivas, seeders, `tests/bootstrap.php`, `tests/TestCase.php` | `RbacSchemaTest`: IDs iniciales, catálogo, datos conservados, revocaciones no restauradas, unicidad, FKs y CHECK real en MariaDB. |

## Ejecución y resultados

La validación del 12 de octubre no instaló paquetes, cambió Node, modificó `.env` ni ejecutó migraciones principales. PHPUnit 12.5.38 ejecutado con PHP 8.3.10 contra MariaDB **10.4.32**. La migración principal se autorizó y ejecutó el 13 de octubre, según la evidencia siguiente.

```powershell
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
php vendor/laravel/pint/builds/pint --test app bootstrap/app.php routes database tests
git diff --check
```

| Verificación | Resultado |
|---|---|
| Suite completa, incluyendo regresión del Sprint 1 | **74 pruebas aprobadas, 475 verificaciones**, 55,634 segundos. |
| Concurrencia real de desactivación de cuentas y revocación de roles | Dos procesos/PDO por caso. En cada caso una operación se confirma y la otra se protege. Se comprueba espera del bloqueo de al menos 100 ms y un solo evento confirmado. |
| Pint | Aprobado. El formateo se limitó al código actual y las migraciones nuevas del correctivo. |
| Diferencias Git | Sin errores de espacios; ninguna migración anterior modificada. |
| `.env` | SHA-256 idéntico antes/después. |
| Base principal | Esquema y contenido de todas sus 11 tablas idénticos antes/después, mediante huellas sin divulgar datos. Siete migraciones, tres roles, cero usuarios y cero trabajadores. |
| Base exclusiva al terminar | Doce migraciones; tres roles, 17 permisos, 25 enlaces y tres eventos iniciales; cero usuarios y trabajadores residuales. |

Las pantallas se verificaron por solicitudes HTTP y renderizado Blade. La aceptación visual en navegador y navegación por teclado queda pendiente con el responsable; no se afirma haber realizado esa aceptación.

`phpunit.xml` fuerza entorno testing, conexión mysql, nombre exclusivo y `DB_URL` vacío. El bootstrap rechaza caché de configuración y destinos diferentes, exige una base de pruebas previamente existente y genera clave efímera en memoria. `TestCase` verifica nombre configurado y `SELECT DATABASE()` antes de migraciones normales. Las pruebas ordinarias usan transacciones; las dos de concurrencia verifican también el aislamiento de cada conexión y proceso, confirman sus propios fixtures y limpian solo sus IDs. La suite se ejecuta en un único proceso coordinador.

No se ejecutaron `migrate:fresh`, `migrate:reset`, `db:wipe` ni equivalentes. No se borraron datos principales, ni se hicieron commits o push.

## Migración principal autorizada — 13 de octubre de 2026

El usuario autorizó obtener un respaldo y aplicar las cinco migraciones listadas a `sga_rio_grande`. La autorización de commit y push se recibió posteriormente, después de presentar los resultados. No se ejecutó la suite automatizada sobre la base principal: las comprobaciones operativas posteriores fueron consultas de solo lectura.

Antes de migrar se verificaron PHP 8.3.10, MariaDB 10.4.32, conexión Laravel `mysql`, nombre configurado y resultado real de `SELECT DATABASE()`: `sga_rio_grande`. Las migraciones pendientes coincidían exactamente con las cinco aprobadas. Se capturaron cantidades y huellas de datos, columnas originales y `.env` para compararlas después.

El respaldo se obtuvo con `C:\xampp\mysql\bin\mysqldump.exe`, transacción consistente, rutinas, eventos, triggers y UTF-8. Se comprobó salida exitosa, finalización del dump y presencia de las definiciones de las 11 tablas. Quedó fuera del repositorio:

```text
C:\Users\jcsem\SGA-backups\sga_rio_grande_2026-10-13_pre_HU03_38445a31.sql
Tamaño: 12 270 bytes
SHA-256: de6bdb9a4175f52238f4321cf56f9bc37f69ab30bcd25efb44a0c427bea5d8d5
```

Se ejecutó el comando Laravel `migrate` mediante su Kernel, limitado por `--path` a los cinco archivos aprobados. Antes de llamar al comando se repitió la validación de conexión y conjunto exacto de migraciones pendientes. Las cinco finalizaron correctamente y se registraron en el lote 3, sin seeders adicionales ni comandos destructivos.

| Comprobación posterior | Resultado |
|---|---|
| Migraciones | Doce aplicadas; las cinco nuevas en lote 3, sin pendientes. |
| Tablas | 14. |
| Roles | IDs 1, 2 y 3 y datos originales conservados; activos, versión de permisos 1; capacidades iniciales 17, 5 y 3. |
| Catálogo | 17 permisos, 25 enlaces activos y tres eventos `rbac.initialized`. |
| Usuarios y trabajadores | Cero y cero; no se crearon cuentas. |
| Conservación | Huellas y cantidades idénticas de todos los datos anteriores, comparando las columnas originales de cada tabla y las siete filas originales de migraciones. También se conservaron las sesiones existentes. |
| Integridad | FKs, unicidad de código y asociación y CHECK `aal_one_subject` presentes en MariaDB. |
| `.env` | SHA-256 idéntico antes y después. |

El DDL de MariaDB no es una operación atómica de cinco migraciones. No fue necesario restaurar el respaldo ni revertir cambios. La inicialización no ofrece un `down` que borre configuración o auditoría.

El esquema principal ya permite utilizar el correctivo, pero sigue sin haber cuentas: el responsable deberá ejecutar `php artisan sga:create-admin` para crear explícitamente el primer administrador con sus credenciales elegidas. Quedan pendientes esa configuración de acceso y la aceptación visual. La publicación autorizada comprende código, cinco migraciones nuevas, pruebas y documentación; `.env`, datos y respaldo SQL permanecen fuera del repositorio.

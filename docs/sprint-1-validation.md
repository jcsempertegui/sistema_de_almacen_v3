# Validación del Sprint 1

Fecha: 12 de octubre de 2026, America/La_Paz.

Implementación examinada: `3a0045b83060fea8d7b85e72aa077996306d4224`, rama `main` de `jcsempertegui/sistema_de_almacen_v3`.

## Resultado

El Sprint 1 funciona según la implementación existente, pero HU-03 está incompleta respecto del requisito original de roles y permisos configurables. Las pruebas aprobadas acreditan el comportamiento implementado; no acreditan esa funcionalidad pendiente.

La inspección y ejecución de pruebas no modificaron código, `.env`, migraciones aplicadas ni datos de la base principal. Posteriormente se autorizó publicar este informe y la planificación; esa autorización no comprende programar el Sprint 2.

## Evidencia del entorno

| Elemento | Resultado observado |
|---|---|
| Local y GitHub `main` | Coincidían en el commit examinado; árbol de trabajo limpio al iniciar. |
| Laravel | 13.35.0, confirmado en dependencias instaladas y lockfile. |
| PHP CLI | 8.3.10 de Laragon. |
| PHPUnit | 12.5.38. |
| Node | 18.8.0; no se actualizó. |
| MariaDB | 10.4.32, mediante XAMPP. |
| Base principal | `sga_rio_grande`, con 11 tablas y siete migraciones aplicadas. |
| Datos principales | Tres roles, cero usuarios y cero trabajadores durante la inspección. |
| Base de pruebas | `sga_rio_grande_testing`, existente y con las siete migraciones aplicadas. |
| Sesiones | `APP_KEY` configurada; su valor no se incluye en la documentación. |
| Apache de XAMPP | Carga PHP 8.2.12; no satisface PHP 8.3 requerido por el proyecto. |

Para el entorno actual debe ejecutarse la aplicación con PHP 8.3 de Laragon mediante `php artisan serve`, manteniendo MariaDB de XAMPP. No se requiere actualizar herramientas para las pantallas Blade existentes.

## Inspección realizada

Se revisaron `AGENTS.md`, `docs/sprint-1.md`, Composer, npm, rutas, modelos, controladores, solicitudes de validación, middleware, Policies, acciones de usuarios, comando administrativo, migraciones, vistas y pruebas.

Relaciones e integridad verificadas en MariaDB:

- `users.role_id` referencia `roles.id`.
- `workers.created_by` y `workers.updated_by` referencian `users.id`, con eliminación restringida.
- Trabajadores sin `user_id`, contraseña o correo obligatorio de acceso.
- Códigos de trabajadores, documentos informados, correos y códigos de rol con restricciones únicas.
- Tablas InnoDB y collation `utf8mb4_unicode_ci`.

Las siete migraciones son las tres iniciales de Laravel y las cuatro del Sprint 1. No se modificó ni volvió a aplicar ninguna migración de la base principal.

## Comandos y ejecución

Los comandos se presentaron antes de la validación:

```powershell
git status --short
git rev-parse HEAD
git -c credential.interactive=never ls-remote origin refs/heads/main
php --version
node --version
php vendor/laravel/pint/builds/pint --test app bootstrap/app.php routes database tests
git diff --check
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
```

También se realizaron consultas de solo lectura a las migraciones, tablas, claves foráneas y parámetros del servidor. Se compararon huellas del contenido de usuarios, trabajadores, roles y `.env` antes y después de las pruebas, sin divulgar datos personales ni secretos.

| Comprobación | Resultado |
|---|---|
| PHPUnit | 38 pruebas aprobadas y 251 verificaciones. |
| Pint en modo `--test` | Aprobado; no reformateó archivos. |
| `git diff --check` | Sin errores. |
| `.env` antes/después | Contenido idéntico. |
| Datos principales de usuarios, trabajadores y roles | Contenido y cantidades idénticos. |
| Tablas y migraciones principales | Sin cambios. |

No se ejecutaron `migrate:fresh`, `migrate:reset`, `db:wipe` ni operaciones equivalentes.

## Aislamiento de las pruebas

Se comprobó el aislamiento antes de ejecutar PHPUnit:

1. `phpunit.xml` fuerza `APP_ENV=testing`, conexión `mysql`, nombre `sga_rio_grande_testing` y `DB_URL` vacío.
2. Caché y sesiones se fuerzan a memoria; correo usa el transporte de pruebas.
3. `tests/bootstrap.php` rechaza configuración cacheada y comprueba los valores del entorno.
4. `tests/TestCase.php` valida la base configurada y el resultado real de `SELECT DATABASE()` antes de preparar el esquema.
5. Se usan `DatabaseTransactions` y migraciones normales, sin `RefreshDatabase`.

El bootstrap contiene `CREATE DATABASE IF NOT EXISTS` para la base exclusiva y el TestCase llama a migraciones normales. En esta revisión ambas bases ya existían y no había migraciones pendientes: no se creó ni recreó una base y no se alteró el esquema principal.

Para esta ejecución se desactivó la caché de resultados de PHPUnit, se usó el canal de logs `null` y se redirigieron las compilaciones Blade a una carpeta temporal fuera del proyecto. Estos ajustes fueron variables del proceso, sin editar `.env`.

## Evaluación de historias

| Historia | Estado | Evidencia y límite |
|---|---|---|
| HU-01 — Inicio y cierre de sesión | Implementada | Usuarios activos, validación, límite de intentos por correo/IP, regeneración de sesión, CSRF y cierre mediante POST. Sesiones antiguas bloqueadas al cambiar acceso. |
| HU-02 — Gestión de usuarios | Implementada para la arquitectura actual | Administración exclusiva, contraseñas protegidas, validación de rol/estado, desactivación y conservación del último administrador activo. |
| HU-03 — Roles y autorizaciones | Parcial | Policies y Gate protegen las operaciones, pero se basan en tres roles fijos y un rol por usuario. No hay administración de roles ni asignación configurable de permisos. |
| HU-04 — Gestión de trabajadores | Implementada | Identidad independiente de cuentas, autoría desde sesión, validaciones, búsqueda, estados y acceso de consulta sin escritura. |

Las interfaces se comprobaron mediante respuestas HTTP y revisión de Blade. Falta aceptación visual en navegador para distribución, navegación, tamaños de pantalla y accesibilidad básica.

## Errores y riesgos

| Hallazgo | Prioridad propuesta | Recomendación |
|---|---|---|
| HU-03 no satisface permisos configurables | Alta | Aprobar un correctivo separado antes de construir las autorizaciones del catálogo. |
| Filtro de estado inválido devuelve `The selected estado is invalid.` | Baja | Añadir traducción de la regla `in` a `SgaFormRequest`. |
| Concurrencia del último administrador sin prueba de dos conexiones | Media | Añadir una prueba real de solicitudes concurrentes; el bloqueo actual fue revisado. |
| Sin aceptación visual en navegador | Media | Ejecutar casos de navegación, teclado y tamaños de pantalla. |
| Pruebas con las mismas credenciales locales de conexión | Media | Planificar un usuario MariaDB restringido exclusivamente a pruebas. |
| Apache de XAMPP utiliza PHP 8.2 | Alta para el arranque con Apache | Usar el PHP 8.3 disponible, sin reconfigurar herramientas automáticamente. |
| Scripts `composer setup` y `composer dev` conservan tareas incompatibles con las restricciones actuales | Media | Usar comandos directos; revisar scripts solo bajo autorización. |
| MariaDB 10.4 fuera de mantenimiento | Riesgo de despliegue | Planificar actualización antes de producción. |

El catálogo de roles ya probado no sustituye el requisito original: el usuario confirmó que desea roles y permisos configurables. La arquitectura no se cambió durante la revisión.

## Correctivo propuesto de HU-03

Estado: pendiente de aprobación e implementación.

- Mantener inicialmente un rol por usuario; varios roles simultáneos no fueron especificados.
- Añadir `permissions` y `permission_role`, con códigos y asociaciones únicos.
- Añadir estado y versión de permisos a `roles` para revocar sesiones cuando cambien capacidades.
- Conservar los identificadores y asignaciones de los tres roles actuales como configuración inicial.
- Autorizar por capacidades en Policies/Gates, sin depender de códigos de rol fijos.
- Revisar el selector de roles, creación del primer administrador y protección del último operador con administración de usuarios y permisos.
- Permitir configurar roles/asignaciones únicamente a usuarios autorizados.
- Añadir pruebas de concesión, revocación, roles nuevos, sesiones antiguas y conservación del acceso administrativo.

No se proponen excepciones automáticas para el Administrador que eludan los permisos configurados. El control de acceso administrativo mínimo debe impedir que una modificación deje al sistema sin una cuenta activa capaz de administrar usuarios y permisos.

## Configuración operativa pendiente

No había un usuario real registrado durante la inspección. Ya existe `php artisan sga:create-admin`, que solicita los datos del primer administrador sin contraseñas predeterminadas. No se ejecutó durante la validación de solo lectura.

## Documentos relacionados

- [Implementación del Sprint 1](sprint-1.md).
- [Propuesta del Sprint 2](sprint-2.md).
- [Planificación global](global-plan.md).

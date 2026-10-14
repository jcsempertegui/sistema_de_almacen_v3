# Sprint 2 — Etapa 1: categorías y unidades de medida

Fecha: 13 de octubre de 2026, America/La_Paz.

Estado: HU-05 y HU-06 implementadas y verificadas en `sprint-2/hu05-hu06`. Después de presentar los resultados, el usuario autorizó el 13 de octubre de 2026 el commit, la integración en `main` y la publicación del código y documentación. Las tres migraciones nuevas se aplicaron exclusivamente a `sga_rio_grande_testing`. La base principal y `.env` permanecen intactos; la aplicación a `sga_rio_grande` sigue pendiente de autorización específica.

## Verificación previa y dependencias

Se leyeron `AGENTS.md`, el plan, el diccionario y la matriz del Sprint 2. El árbol de trabajo estaba limpio y `main` local y GitHub coincidían en `e02ed1c8c7ea09f536f1a93f4a2560365939d269`. Se creó la rama de trabajo desde ese commit.

La conexión principal se comprobó por configuración Laravel y `SELECT DATABASE()`: `sga_rio_grande`, con PHP 8.3.10 y MariaDB 10.4.32. Tenía 14 tablas, 12 migraciones aplicadas, 17 permisos, tres roles, cero usuarios y cero trabajadores. La base exclusiva de pruebas ya existía y no había configuración cacheada.

Se confirmó el correctivo HU-03: permisos configurables, Policies, Gates, revocación por versiones y `SecurityMutation`. Los autores se asignan desde el servidor. No fue necesario instalar paquetes, actualizar Node ni modificar autenticación. La ausencia del primer administrador real no bloquea las pruebas, pero su creación explícita y una migración principal autorizada serán necesarias para usar estas pantallas en el entorno principal.

## Funcionalidad implementada

HU-05: registrar y editar código, nombre y descripción; listado paginado de 20 filas, búsqueda por código/nombre y filtro de estado. Código normalizado a mayúsculas, de hasta 50 caracteres; nombre único según `utf8mb4_unicode_ci`, de hasta 150. Descripción opcional TEXT, con límite de formulario de 10 000 caracteres. Activación/desactivación reversibles y sin eliminación ni reutilización de códigos o nombres inactivos.

HU-06: registrar y editar código, nombre, símbolo, dimensión y precisión; listado paginado, búsqueda por código/nombre/símbolo y filtro de estado. Código normalizado a mayúsculas de hasta 30; nombre de hasta 100, sin unicidad obligatoria; símbolo de hasta 20, recortado en extremos y con capitalización conservada. El símbolo tiene unicidad real `utf8mb4_bin`: `m` y `M` pueden coexistir. Dimensiones `count`, `length`, `mass`, `volume`, `time`, `other`, mostradas como Conteo, Longitud, Masa, Volumen, Tiempo y Otra. Precisión entera de 0 a 6. Sin conversiones ni unidades precreadas automáticamente.

Rutas `/categorias` y `/unidades-de-medida`, con pantallas Blade en español, layout y CSS existentes. Escrituras con CSRF y validaciones del servidor; campos de autoría y campos ajenos al endpoint se ignoran. Los listados y formularios escapan contenido HTML. Las acciones que cambian estado se autorizan independientemente de las que editan datos, tanto en PATCH como en el formulario general.

`SaveCategory` y `SaveMeasurementUnit` reutilizan el bloqueo y reautorización de `SecurityMutation`, y bloquean la fila editada dentro de una transacción. Las colisiones UNIQUE detectadas durante guardado se convierten en errores españoles y no dejan cambios parciales. Se conservan `created_by` y se actualiza `updated_by` con el operador real.

## Dependencias históricas y alcance de su evidencia

`CatalogReferences` es una integración de solo lectura que comprueba las tablas futuras cuando existan. No crea modelos, rutas, migraciones ni datos de productos o atributos. Si una tabla dependiente no existe, no hay referencias; si existe con columnas incompatibles, el guard rechaza la operación con un mensaje español.

- Categoría: desactivar se bloquea ante productos activos asociados. Las referencias futuras usan el ID estable, que no se modifica al editar un código.
- Unidad: desactivar se bloquea ante productos o atributos activos. Cualquier referencia, incluso inactiva, impide modificar código, símbolo, dimensión o precisión; se permite aclarar el nombre. Un cambio de significado necesita otra unidad.
- Los guardados de historias posteriores deberán tomar el mismo bloqueo y comprobar nuevamente la actividad de categoría/unidad para conservar estas reglas ante operaciones concurrentes.

Los tests de esta etapa verifican rechazo, mensajes y rollback mediante referencias simuladas en `CatalogReferences`. **No se afirma haber probado dependencias con productos o atributos reales**, pues esos módulos están fuera del alcance y sus tablas no existen. Las relaciones Eloquent hacia esos modelos y su integración quedan pendientes de HU-07/HU-08; actualmente los modelos solo exponen `creator` y `updater` hacia usuarios.

## Permisos y migraciones nuevas

| Recurso | Consulta | Creación | Edición | Estado |
|---|---|---|---|---|
| Categorías | `categories.view` | `categories.create` | `categories.update` | `categories.change_status` |
| Unidades | `measurement_units.view` | `measurement_units.create` | `measurement_units.update` | `measurement_units.change_status` |

Administrador y Encargado reciben inicialmente las ocho capacidades; Consulta/Auditor solo las dos de consulta. Las Policies y Gates no consultan nombres de roles. Los roles personalizados se configuran desde la gestión ya existente de HU-03.

| Migración | Efecto |
|---|---|
| `2026_10_13_000001_create_categories_table` | Tabla InnoDB `categories`, campos del diccionario, UNIQUE de código/nombre, índice `(is_active,name)`, CHECK booleano y FKs de autoría con RESTRICT. |
| `2026_10_13_000002_create_measurement_units_table` | Tabla InnoDB `measurement_units`, UNIQUE de código y símbolo binario, índice `(is_active,name)`, CHECK de precisión/dimensión/estado y FKs de autoría con RESTRICT. |
| `2026_10_13_000003_add_category_and_measurement_unit_permissions` | Ocho permisos explícitos y concesiones iniciales solo a los tres códigos de rol conocidos. No toca usuarios ni capacidades antiguas. |

La inicialización toma el bloqueo común, omite enlaces existentes tanto activos como revocados, y no concede nada a roles desconocidos. Solo cuando concede enlaces nuevos incrementa `permissions_version` del rol afectado y añade auditoría de permisos en la misma transacción. No cambia autores de ediciones anteriores. Repetirla o ejecutar los seeders no restaura revocaciones ni duplica eventos. No se modificó ninguna de las 12 migraciones del Sprint 1.

Para el estado principal observado, la aplicación autorizada añadiría dos tablas, ocho permisos, 18 enlaces y tres eventos: 16 tablas, 15 migraciones, 25 permisos, 43 enlaces y seis eventos de auditoría en total. Los tres roles pasarían de versión 1 a 2, invalidando sus sesiones anteriores. No crearía usuarios, trabajadores, categorías ni unidades iniciales. Estos totales se deben contrastar nuevamente si cambia la configuración antes de migrar.

## Requerimiento → historia → código → pruebas → evidencia

| Requerimiento / caso | HU | Código | Prueba y evidencia |
|---|---|---|---|
| CAT-01: registro y autoría | HU-05 | `Category`, `CategoryRequest`, `CategoryController`, `SaveCategory` | `CategoryManagementTest`: Administrador y Encargado registran/editar; autoría desde sesión y creador conservado. Aprobado por HTTP/MariaDB. |
| CAT-02: obligatorios y duplicados | HU-05 | Solicitud, UNIQUE de `categories`, acción transaccional | `CategoryManagementTest`, `CatalogIntegrityTest`, `CatalogUniqueConflictTest`: longitudes, vacíos, duplicados inactivos, collation y colisiones directas. Aprobado. |
| CAT-03: dependencias al desactivar | HU-05 | `CatalogReferences`, `SaveCategory` | Guard, dos endpoints y rollback aprobados con referencias simuladas; integración con productos reales pendiente. |
| CAT-04: búsqueda y filtros | HU-05 | Controlador y listado de categorías | `CategoryManagementTest`: paginación, filtros preservados y comodines literales. Aprobado. |
| MED-01: gestión del Encargado | HU-06 | `MeasurementUnit`, solicitud, Policy, controlador y acción | `MeasurementUnitManagementTest`: unidad, metro, kilogramo y litro como datos sintéticos; edición, autores, estados y conservación. Aprobado. |
| MED-02: precisión y dimensión | HU-06 | Solicitud y CHECK de `measurement_units` | `MeasurementUnitManagementTest`, `CatalogIntegrityTest`: negativos, más de seis, fracciones, dimensiones inválidas, rango y seis dimensiones válidas. Aprobado. |
| MED-03: símbolos técnicos | HU-06 | `symbol` con collation binaria y validación UNIQUE | `MeasurementUnitManagementTest`, `CatalogIntegrityTest`: capitalización intacta, `m`/`M` distintos y rechazo de repetición exacta. Aprobado sobre MariaDB. |
| MED-04: evitar reinterpretación | HU-06 | `SaveMeasurementUnit`, `CatalogReferences` | Bloqueo de campos semánticos con referencias, nombre editable, desactivación protegida y rollback aprobados con referencias simuladas; integración futura pendiente. |
| SEC-01/02/03: autorización y datos manipulados | Ambas | Policies, FormRequests, `SecurityMutation` y acciones | `CatalogAuthorizationTest`: Auditor solo lectura, invitados/inactivos, permisos independientes, roles personalizados, revocación en vuelo; tests de gestión: autores y campos adicionales ignorados. Aprobado. |
| SEC-04: CSRF y escape HTML | Ambas | Middleware existente y vistas Blade | `CatalogAuthorizationTest`, tests de gestión: POST sin token rechazado, nombres/descripciones/símbolos escapados. Aprobado por HTTP; aceptación visual pendiente. |
| Integridad y compatibilidad | Ambas | Migraciones, FKs, índices y CHECK | `CatalogIntegrityTest`: SQL directo prueba restricciones, símbolos y FK de autores; InnoDB y ausencia de tablas futuras. Aprobado sobre MariaDB 10.4.32. |
| Permisos iniciales sin restaurar revocaciones | Ambas | Migración de ocho permisos y catálogo | `CatalogPermissionInitializationTest`: inicialización, idempotencia, rol desconocido, usuarios intactos, versiones/auditoría y sesiones anteriores. Aprobado. |
| REG-01: conservar Sprint 1 | Ambas | Reutilización de autorización/layout; ajustes de expectativas de catálogo en dos pruebas | Suite completa incluye las 74 pruebas originales y su concurrencia de dos procesos. Aprobado. |
| UI-01/UI-02: navegador/teclado | Ambas | Pantallas y navegación | Renderizado HTTP automatizado aprobado. Aceptación visual en navegador, teclado y tamaños de pantalla pendiente del responsable. |

ATR, ASC, PRO, VAL, LOC, PLC e IND permanecen planificados, sin implementación ni ejecución. No se registran como aprobados por esta etapa.

## Ejecución real y resultados

```powershell
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
php vendor/laravel/pint/builds/pint --test app bootstrap/app.php routes database tests
git diff --check
```

Suite completa: **120 pruebas aprobadas y 819 verificaciones**, 90,528 segundos, PHPUnit 12.5.38, PHP 8.3.10 y MariaDB 10.4.32. Incluye 46 casos nuevos de categorías/unidades y 74 de regresión del Sprint 1. Pint y revisión de espacios aprobados.

Una ejecución parcial inicial tuvo 40 casos aprobados y un error en los datos del caso de símbolos: utilizaba códigos `CODE-m`/`CODE-M`, equivalentes para su índice no binario. Se corrigieron a códigos distintos y la suite completa verificó exitosamente los símbolos sensibles a mayúsculas. Ese error inicial no se contabiliza como evidencia aprobada.

Se mantuvieron `phpunit.xml`, `tests/bootstrap.php` y `TestCase`: fuerzan testing/mysql, `sga_rio_grande_testing`, `DB_URL` vacío, clave efímera, caché/sesiones de memoria, rechazo de caché de configuración y verificación del destino real antes de migraciones. No se recreó la base. Los casos ordinarios usan rollback; la concurrencia existente conserva su aislamiento y limpieza de sus propios IDs.

Comparación antes/después de `.env`, esquema y contenido de todas las tablas principales: huellas idénticas. `sga_rio_grande` conserva 14 tablas y 12 migraciones. La base de pruebas termina con 15 migraciones, 25 permisos, 43 enlaces, seis eventos iniciales, roles versión 2 y cero usuarios, trabajadores, categorías o unidades residuales.

## Archivos creados y modificados

30 archivos nuevos:

```text
app/Actions/Catalog/SaveCategory.php
app/Actions/Catalog/SaveMeasurementUnit.php
app/Http/Controllers/Catalog/CategoryController.php
app/Http/Controllers/Catalog/MeasurementUnitController.php
app/Http/Requests/Catalog/CategoryRequest.php
app/Http/Requests/Catalog/CategoryStatusRequest.php
app/Http/Requests/Catalog/MeasurementUnitRequest.php
app/Http/Requests/Catalog/MeasurementUnitStatusRequest.php
app/Models/Category.php
app/Models/MeasurementUnit.php
app/Policies/CategoryPolicy.php
app/Policies/MeasurementUnitPolicy.php
app/Services/CatalogReferences.php
database/factories/CategoryFactory.php
database/factories/MeasurementUnitFactory.php
database/migrations/2026_10_13_000001_create_categories_table.php
database/migrations/2026_10_13_000002_create_measurement_units_table.php
database/migrations/2026_10_13_000003_add_category_and_measurement_unit_permissions.php
resources/views/catalog/categories/form.blade.php
resources/views/catalog/categories/index.blade.php
resources/views/catalog/measurement-units/form.blade.php
resources/views/catalog/measurement-units/index.blade.php
routes/catalog.php
tests/Feature/Catalog/CatalogAuthorizationTest.php
tests/Feature/Catalog/CatalogIntegrityTest.php
tests/Feature/Catalog/CatalogPermissionInitializationTest.php
tests/Feature/Catalog/CatalogUniqueConflictTest.php
tests/Feature/Catalog/CategoryManagementTest.php
tests/Feature/Catalog/MeasurementUnitManagementTest.php
docs/sprint-2-stage-1.md
```

13 archivos modificados:

```text
README.md
app/Providers/AppServiceProvider.php
app/Support/PermissionCatalog.php
docs/data-dictionary-sprint-2.md
docs/global-plan.md
docs/sprint-2-test-matrix.md
docs/sprint-2.md
resources/views/dashboard.blade.php
resources/views/layouts/app.blade.php
resources/views/roles/form.blade.php
routes/web.php
tests/Feature/RbacManagementTest.php
tests/Feature/RbacSchemaTest.php
```

Los dos tests anteriores ajustan conteos/versión inicial a la ampliación aprobada del catálogo, manteniendo sus casos de protección. No se cambiaron Composer, npm, `.env`, CSS, bootstrap de pruebas, migraciones anteriores ni módulos futuros.

## Comandos pendientes para la base principal

**No ejecutados; requieren autorización específica, verificación del destino y respaldo previo.** El comando solo debe utilizarse después de confirmar `sga_rio_grande` y que estas tres sean las únicas migraciones pendientes:

```powershell
php artisan migrate:status
php artisan migrate --path=database/migrations/2026_10_13_000001_create_categories_table.php --path=database/migrations/2026_10_13_000002_create_measurement_units_table.php --path=database/migrations/2026_10_13_000003_add_category_and_measurement_unit_permissions.php
php artisan migrate:status
```

La publicación Git se autorizó después de revisar los resultados de esta etapa. Comprende los 43 archivos enumerados y la integración de la rama de trabajo en `main`; no incluye `.env`, datos ni respaldos. Esta autorización no aplica las migraciones principales ni inicia HU-07 u otro módulo. La aceptación visual y la integración con dependencias reales siguen pendientes.

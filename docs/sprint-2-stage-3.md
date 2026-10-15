# Sprint 2 — Etapa 3: HU-09, ubicaciones físicas

Fecha: 14 de octubre de 2026, America/La_Paz.

Estado: implementación aprobada y probada en `sprint-2/hu09-ubicaciones`, basada en `08783b467a43b34175a83a86e7afc908ffa72e06`. Migraciones nuevas aplicadas únicamente a `sga_rio_grande_testing`. Sin commit, merge, push ni aplicación principal.

## Revisión previa y autorización

Se revisaron `AGENTS.md`, el plan, el diccionario, la matriz del Sprint 2, el informe de HU-07, README y el plan global. El repositorio estaba limpio y `main` coincidía con GitHub en el commit indicado. Se creó la rama de HU-09 sin sobrescribir trabajo previo.

La conexión Laravel y `SELECT DATABASE()` confirmaron `sga_rio_grande`, PHP 8.3.10, Laravel 13.35.0 y MariaDB 10.4.32. Había 18 migraciones aplicadas, ninguna pendiente, 18 tablas, 33 permisos, 61 vínculos y tres roles con versión 3 y capacidades 33/21/7. La tabla `locations` no existía. La principal tenía cero usuarios, trabajadores, categorías, unidades y atributos; no se crearon cuentas desconocidas.

La base exclusiva ya existía con 18 migraciones, cero usuarios, aislamiento REPEATABLE-READ y sin configuración cacheada. Se capturaron las huellas SHA-256 de los datos y definiciones de todas las tablas principales y de `.env` antes de implementar.

El usuario aprobó el plan técnico de HU-09. La autorización abarca implementación y pruebas aisladas; publicación y migración principal requieren autorización posterior. HU-08/HU-10, productos, valores, unidades individualizadas, movimientos, transferencias y existencias no se implementan.

## Modelo y esquema

`Location` contiene código único normalizado a mayúsculas (60), nombre (150), tipo `sector/shelf/cabinet`, padre opcional, indicador almacenable, descripción, estado, autores y timestamps. Los tipos se muestran como Sector, Estante y Gabinete. No se impone unicidad de nombre, ni una combinación fija de tipos padre/hijo, ni una profundidad de negocio arbitraria.

La tabla usa InnoDB y utf8mb4_unicode_ci; `type` usa utf8mb4_bin. Tiene UNIQUE del código incluso para inactivos; CHECK del tipo y de ambos booleanos; FK propia y dos FKs de autoría con RESTRICT en actualización/eliminación; índices `(parent_id,is_active,type)` y `(is_active,name)`. Los booleanos predeterminan true. Descripción TEXT con validación de formulario de hasta 10.000 caracteres.

Relaciones implementadas: padre, hijos, creador y modificador. No se definen relaciones a modelos futuros inexistentes. Los autores son asignados por el operador real y no por datos del formulario.

Migraciones nuevas:

1. `2026_10_14_000004_create_locations_table.php`.
2. `2026_10_14_000005_add_location_permissions.php`.

No se modificaron las 18 migraciones anteriores. Los métodos `down()` nuevos rechazan la reversión destructiva; cualquier recuperación principal futura requiere un procedimiento autorizado que conserve referencias.

## Jerarquía, estados y conservación

`SaveLocation` usa `SecurityMutation` con la protección administrativa existente conservada y `protectAdministrativeAccess: false` para el catálogo. El bloqueo común de la fila `roles.assign_permissions` se adquiere antes de revalidar al operador y consultar la jerarquía. Se comprueban permisos y versiones actuales; no se autorizan operaciones por el nombre del rol.

`LocationHierarchy::validateParent` recorre los ancestros con FOR UPDATE después de adquirir el mutex. Las lecturas son actuales, sin reutilizar la relación Eloquent o un snapshot anterior a la espera. Un conjunto de IDs visitados detecta autorreferencia, descendientes y ciclos preexistentes. El recorrido incluye ubicaciones inactivas.

Todas las escrituras de ubicación pasan por esta acción. La FK solo asegura existencia del padre: no se presenta como garantía SQL contra todos los ciclos. No se usan GET_LOCK, otra tabla de bloqueos, CTE recursivas ni paquetes.

- Crear, cambiar padre o reactivar exige padre y antecesores activos, incluso al crear una ubicación inicialmente inactiva.
- La raíz admite padre null. Una ubicación no almacenable puede contener ubicaciones almacenables.
- Desactivar exige ausencia de hijos activos. No se realiza una cascada.
- Se puede corregir una ubicación histórica inactiva conservando su padre inactivo. Ese vínculo no se elimina al desactivar; no se permite reasignar un padre inactivo.
- Se conservan IDs y registros; no hay endpoints DELETE ni reutilización de códigos inactivos.
- Las colisiones UNIQUE producidas al guardar se traducen a validación española y rollback.
- La edición general necesita además el permiso de estado si pretende modificarlo. El endpoint PATCH de estado solo acepta el cambio de estado, sin alterar padre u otros metadatos.

`LocationReferences` prepara integración de lectura con `individual_units` y `product_locations`, sin crear sus tablas o modelos. Si una tabla existe sin la columna necesaria, la operación se bloquea con mensaje español.

Criterio conservador aprobado: cualquier fila que conserve una asignación, incluso inactiva, cuenta como referencia. Se bloquea desactivar con unidades en el nodo/subárbol, cambiar padre/tipo con referencias del subárbol, y deshabilitar almacenamiento con referencias propias. Un contenedor puede dejar de ser almacenable si las referencias están solo en sus hijos y no hay referencias propias.

Estas dependencias futuras se prueban mediante simulaciones explícitas. Los futuros guardados de productos/unidades deberán compartir el mutex y validar actividad/almacenamiento; su integración real corresponde a HU-08/HU-10. El árbol muestra la estructura actual, sin versiones históricas completas de rutas ni movimientos.

## Permisos

| Acción | Código | Administrador | Encargado | Consulta/Auditor |
|---|---|---|---|---|
| Consultar listado y árbol | `locations.view` | Sí | Sí | Sí |
| Crear | `locations.create` | Sí | Sí | No |
| Editar metadatos/estructura | `locations.update` | Sí | Sí | No |
| Activar/desactivar | `locations.change_status` | Sí | Sí | No |

Es configuración inicial editable mediante el formulario existente de roles. La migración usa datos congelados, agrega solo permisos y vínculos ausentes a los códigos iniciales conocidos, conserva revocaciones y no concede privilegios a roles/usuarios desconocidos. No modifica permisos previos. Versiona y audita solo roles que reciben vínculos nuevos, invalidando sus sesiones anteriores. Repetirla conserva la configuración.

En una base con el estado inicial verificado, el resultado previsto de HU-09 es 37 permisos, 70 vínculos y capacidades 37/25/8, con versiones 4 y tres auditorías nuevas. La base principal mantiene su configuración anterior hasta una aplicación autorizada.

## Pantallas y consultas

`/ubicaciones`: listado de 20 filas, búsqueda literal por código/nombre, filtros de tipo, estado, padre y solo raíces; ruta completa, indicador almacenable y acciones autorizadas. El selector de filtro ofrece padres que realmente tienen hijos.

`/ubicaciones/arbol`: árbol renderizado como tabla en profundidad, con sangría, nivel numérico, ruta completa, tipo, estado y almacenable. Incluye nodos inactivos. Se limita la sangría visual, sin limitar la profundidad de negocio ni truncar la ruta.

Crear/editar: los padres ofrecidos son activos y excluyen la propia ubicación y descendientes. El padre histórico inactivo puede conservarse al editar metadatos de una ubicación inactiva. La acción valida nuevamente al enviar.

La página y su catálogo de rutas se leen en una misma transacción de lectura REPEATABLE-READ para no mezclar snapshots ante altas/reasignaciones concurrentes. El árbol deriva de una consulta de columnas ligeras. El armado de rutas usa memoria y detección de ciclos, sin consultas Eloquent por cada nivel ni recursión PHP sin control.

Se mantiene Blade/CSS existente, CSRF, etiquetas y mensajes españoles, HTML escapado y navegación por permisos. El FormRequest de búsqueda autoriza antes de validar filtros. No se instala frontend ni se actualiza Node.

Límite de escala: árbol y opciones de padre cargan el catálogo ligero completo. Si crece de forma importante, podrá evaluarse navegación por ramas; no se incorporó infraestructura adicional en esta etapa.

## Pruebas, aislamiento y resultados

Comandos de verificación:

```powershell
php vendor/bin/phpunit --colors=never --log-junit storage/logs/hu09-phpunit.xml
php vendor/bin/pint --test app routes tests database/factories/LocationFactory.php database/migrations/2026_10_14_000004_create_locations_table.php database/migrations/2026_10_14_000005_add_location_permissions.php
git diff --check
```

El bootstrap y TestCase existentes fuerzan testing/mysql y la base exclusiva, rechazan DB_URL y configuración cacheada, generan una clave efímera y verifican SELECT DATABASE() antes de preparar el esquema mediante migraciones normales. No se usa RefreshDatabase, fresh, reset o wipe.

Los casos ordinarios se aíslan con transacciones. Las cuatro carreras nuevas usan dos procesos PHP, dos operadores distintos y conexiones independientes. Cada subproceso verifica destino antes de escribir; una barrera coordina READY/GO. Se mide la espera real del mutex y se comprueba que una operación cambió y la otra quedó protegida. Se inspecciona la jerarquía final, además del resultado de cada proceso.

Fixtures concurrentes confirmados exclusivamente en testing: dos roles/usuarios propios y ubicaciones con códigos únicos por escenario. En finally se detienen procesos pendientes, se desconectan/eliminan solo las ubicaciones de esos códigos y se eliminan solo sus usuarios, vínculos y roles. No se ejecuta limpieza global ni se toca la principal. La suite completa se ejecuta en un solo proceso coordinador; los subprocesos son parte controlada de las pruebas de concurrencia.

Resultados reales de la ejecución final, sobre el código definitivo:

| Comprobación | Resultado |
|---|---|
| PHPUnit 12.5.38, PHP 8.3.10, MariaDB 10.4.32 | **221 pruebas, 1.602 verificaciones, cero errores/fallos.** |
| Regresión | Las 185 pruebas anteriores y 36 casos nuevos de HU-09. |
| Concurrencia | Cuatro carreras de ubicaciones aprobadas y dos administrativas anteriores conservadas. |
| Duración / memoria informadas por PHPUnit | 2 minutos y 40,116 segundos; 68 MB. |
| Esquema de testing | 20 migraciones; tabla locations creada y vacía al terminar. |
| Permisos de testing | 37 permisos, 70 vínculos y 12 auditorías; versiones 4 y capacidades 37/25/8 en los roles iniciales. |
| Fixtures finales | Cero usuarios, trabajadores, categorías, unidades, atributos, asociaciones de atributos y ubicaciones en testing. |
| Principal | Huellas de datos y definiciones de sus 18 tablas idénticas antes/después; 18 migraciones aplicadas y solo las dos de HU-09 pendientes. |
| .env | SHA-256 conservado. |
| Pint y git diff --check | Aprobados. |
| Archivos anteriores protegidos | Sin cambios en migraciones previas, SecurityMutation ni dependencias; no se instalaron paquetes. |

Evidencia JUnit local: `storage/logs/hu09-phpunit.xml`, ignorada por Git. Los casos SQL comprobaron UNIQUE, CHECK y FKs mediante operaciones inválidas rechazadas por MariaDB. El estado principal se inspeccionó únicamente con consultas de lectura y `migrate:status`; no se ejecutó ninguna prueba ni migración allí.

### Trazabilidad

| Requerimiento → Historia | Código | Pruebas ejecutadas y alcance de la evidencia |
|---|---|---|
| LOC-01 → HU-09: tipos, padres, árbol y rutas | Location, LocationController, LocationHierarchy, vistas | LocationManagementTest: tres tipos, relaciones padre/hijos, recorrido y ruta |
| LOC-02 → HU-09: padre válido y activo | LocationRequest, validateParent, SaveLocation | Inexistente, inactivo, antecesor inactivo, autorreferencia, reactivación y padre histórico |
| LOC-03 → HU-09: no descendientes ni ciclos | validateParent, subtreeIds | Descendiente directo/indirecto/inactivo; inconsistencia preexistente detectada sin bucle |
| LOC-04 → HU-09: concurrencia | SecurityMutation reutilizado, FOR UPDATE | LocationConcurrencyTest: padres cruzados con y sin snapshot previo, espera real y árbol acíclico final |
| LOC-05 → HU-09: desactivaciones compatibles | SaveLocation, LocationReferences | Hijos reales, carreras alta/reactivación frente a desactivación; unidades futuras simuladas |
| Integridad → HU-09 | Migración locations y mutadores | LocationIntegrityTest: UNIQUE, CHECK, FKs y RESTRICT reales en MariaDB |
| SEC-01/02/03/04 → HU-09 | Policy, Requests, acción, Blade | LocationAuthorizationTest y ManagementTest: roles/permisos independientes, peticiones directas, autores, CSRF, HTML escapado |
| RBAC-02/03/07 → HU-03 + HU-09 | Migración de permisos, Gate, formulario de roles | LocationPermissionInitializationTest: revocaciones, desconocidos, idempotencia, versiones y sesiones |
| REG-01 → historias anteriores | Código previo conservado | Suite completa de Sprint 1 y HU-05/HU-06/HU-07; huellas de principal y .env |

Aceptación visual/manual, teclado y distintos tamaños de pantalla pendientes. Las pruebas HTTP no equivalen a aceptación en navegador. LOC-05 no se presenta como integración con unidades reales. No se ejecutaron casos de HU-08/HU-10, existencias, movimientos o transferencias.

## Archivos creados y modificados

Total: 38 archivos, 22 nuevos y 16 modificados.

Nuevos:

- `app/Models/Location.php`, `database/factories/LocationFactory.php`.
- `database/migrations/2026_10_14_000004_create_locations_table.php`, `2026_10_14_000005_add_location_permissions.php` (misma carpeta).
- `app/Actions/Catalog/SaveLocation.php`.
- `app/Services/LocationHierarchy.php`, `LocationReferences.php` (misma carpeta).
- `app/Policies/LocationPolicy.php`.
- `app/Http/Controllers/Catalog/LocationController.php`.
- `app/Http/Requests/Catalog/LocationRequest.php`, `LocationStatusRequest.php`, `LocationSearchRequest.php` (misma carpeta).
- `resources/views/catalog/locations/index.blade.php`, `tree.blade.php`, `form.blade.php` (misma carpeta).
- `tests/Feature/Catalog/LocationManagementTest.php`, `LocationIntegrityTest.php`, `LocationAuthorizationTest.php`, `LocationPermissionInitializationTest.php`, `LocationConcurrencyTest.php` (misma carpeta).
- `tests/Support/concurrent-location.php`.
- Este informe `docs/sprint-2-stage-3.md`.

Modificados:

- `app/Support/PermissionCatalog.php`, `app/Providers/AppServiceProvider.php`, `routes/catalog.php`.
- `resources/views/layouts/app.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/roles/form.blade.php`.
- `tests/Feature/RbacSchemaTest.php`, `tests/Feature/Catalog/CatalogPermissionInitializationTest.php`, `AttributePermissionInitializationTest.php`, `CatalogIntegrityTest.php`, `TechnicalAttributeIntegrityTest.php` (los últimos cuatro en la carpeta de catálogo): conteos ampliados y ubicación retirada de la lista de tablas futuras. Las garantías de autorización/regresión se conservan.
- `README.md`, `docs/global-plan.md`, `docs/sprint-2.md`, `docs/data-dictionary-sprint-2.md`, `docs/sprint-2-test-matrix.md`: estados por rama/base y trazabilidad actualizados. El informe de HU-07 conserva su evidencia histórica.

No se modifican SecurityMutation, .env, dependencias, migraciones previas ni los módulos anteriores para sustituir su funcionamiento.

## Migraciones principales pendientes

No se ejecutaron sobre `sga_rio_grande`. Después de revisión y autorización específica, se deberá verificar nuevamente Git/destino/pendientes, obtener un respaldo SQL completo fuera del repositorio y verificarlo, abrir mantenimiento y aplicar únicamente:

```powershell
php artisan down --retry=60
php artisan migrate --force --path=database/migrations/2026_10_14_000004_create_locations_table.php --path=database/migrations/2026_10_14_000005_add_location_permissions.php
php artisan migrate:status
# Comprobar esquema, claves, datos previos, permisos, versiones y auditoría.
php artisan up
```

Comprobar primero la conexión Laravel y SELECT DATABASE() = sga_rio_grande; no cambiar .env. El resultado previsto es una tabla vacía nueva, 20 migraciones aplicadas y solo las concesiones iniciales autorizadas. Comparar por huella las filas previas de permisos/vínculos y los demás datos; solo cambian versiones/fecha de roles y las nuevas filas administrativas previstas. Revisar FKs, CHECK e índices, y reabrir únicamente tras verificar.

MariaDB confirma DDL implícitamente; ante un fallo se debe inspeccionar el estado y definir recuperación autorizada, sin rollback destructivo ni reintentos ciegos. Los verificadores de etapas previas están congelados a otros commits y no deben reutilizarse como procedimiento de HU-09 sin revisión.

Publicación, merge/commit/push y migración principal permanecen pendientes de autorización posterior. No se inicia HU-08 ni HU-10.

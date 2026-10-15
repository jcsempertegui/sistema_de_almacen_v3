# Sprint 2 — Etapa 3: HU-09, ubicaciones físicas

Fecha: 14 de octubre de 2026, America/La_Paz.

Estado: HU-09 implementada, probada e integrada en `main` tras autorización del usuario. Código en `1fc5aab5410f35b6a8983c1d14afd570cd660fc9`, basado en `08783b467a43b34175a83a86e7afc908ffa72e06`. Sus dos migraciones principales se aplicaron con respaldo y verificación el 14 de octubre de 2026, en el lote 6.

## Revisión previa y autorización

Se revisaron `AGENTS.md`, el plan, el diccionario, la matriz del Sprint 2, el informe de HU-07, README y el plan global. El repositorio estaba limpio y `main` coincidía con GitHub en el commit indicado. Se creó la rama de HU-09 sin sobrescribir trabajo previo.

La conexión Laravel y `SELECT DATABASE()` confirmaron `sga_rio_grande`, PHP 8.3.10, Laravel 13.35.0 y MariaDB 10.4.32. Había 18 migraciones aplicadas, ninguna pendiente, 18 tablas, 33 permisos, 61 vínculos y tres roles con versión 3 y capacidades 33/21/7. La tabla `locations` no existía. La principal tenía cero usuarios, trabajadores, categorías, unidades y atributos; no se crearon cuentas desconocidas.

La base exclusiva ya existía con 18 migraciones, cero usuarios, aislamiento REPEATABLE-READ y sin configuración cacheada. Se capturaron las huellas SHA-256 de los datos y definiciones de todas las tablas principales y de `.env` antes de implementar.

El usuario aprobó primero la implementación y pruebas aisladas de HU-09. Tras recibir los resultados, autorizó también su publicación y las dos migraciones principales. HU-08/HU-10, productos, valores, unidades individualizadas, movimientos, transferencias y existencias no se implementan.

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

El resultado verificado de HU-09 en testing y, después de la aplicación autorizada, en la principal es 37 permisos, 70 vínculos y capacidades 37/25/8, con versiones 4 y tres auditorías nuevas. Las filas anteriores de permisos y vínculos se conservaron.

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
| Principal durante las pruebas | Huellas de datos y definiciones de sus 18 tablas idénticas antes/después de la suite. Entonces tenía 18 migraciones; la aplicación autorizada posterior se documenta más abajo. |
| .env | SHA-256 conservado. |
| Pint y git diff --check | Aprobados. |
| Archivos anteriores protegidos | Sin cambios en migraciones previas, SecurityMutation ni dependencias; no se instalaron paquetes. |

Evidencia JUnit local: `storage/logs/hu09-phpunit.xml`, ignorada por Git. Los casos SQL comprobaron UNIQUE, CHECK y FKs mediante operaciones inválidas rechazadas por MariaDB. Durante la validación se inspeccionó la principal únicamente mediante consultas de lectura y `migrate:status`. Ninguna prueba automatizada se ejecutó allí; las migraciones principales se aplicaron después de una autorización separada.

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

## Aplicación principal autorizada y realizada

El 14 de octubre de 2026, después de la autorización, se guardó el código probado en el commit indicado y se integró mediante fast-forward. La prevalidación específica verificó `main` limpia, las huellas de las dos migraciones, Laravel/PHP/MariaDB, la conexión Laravel y SELECT DATABASE() = sga_rio_grande, restricciones habilitadas y exactamente las dos migraciones aprobadas pendientes.

Se comprobó la ausencia de workers PHP de colas/planificador y de triggers, rutinas y eventos programados. Se abrió mantenimiento, se capturó el estado anterior y se obtuvo un respaldo con `C:\xampp\mysql\bin\mysqldump.exe`, versión MariaDB 10.4.32, usando:

```text
--single-transaction --quick --skip-lock-tables --routines --events --triggers
--hex-blob --default-character-set=utf8mb4 --skip-add-drop-table
--databases sga_rio_grande --result-file=<ruta fuera del repositorio>
```

Respaldo conservado fuera del repositorio:

- Archivo: `C:\Users\jcsem\SGA-backups\sga_rio_grande_pre_hu09_20261014_214552_f367874d.sql`.
- Tamaño: 40.658 bytes.
- SHA-256: `8df4950e4d502c7a71cb658327acb6157f9eb233fd2e15433e4e6757688deaa9`.
- Manifiesto con sufijo `.manifest.json`; referencias de conservación con sufijos `.before.json` y `.after.json`.
- Procedimiento y verificador locales: `C:\Users\jcsem\SGA-backups\prevalidacion-hu09-2026-10-14`, congelados al commit de implementación. Los verificadores de etapas anteriores se conservaron intactos.

Integridad verificada: salida 0 de mysqldump, marca de finalización, definiciones de las 18 tablas, INSERT para todas las tablas no vacías y SHA-256. Las huellas de datos/esquemas originales no cambiaron durante el respaldo. No se realizó un ensayo de restauración ni se restauró el dump sobre testing.

Comandos de aplicación utilizados:

```powershell
php artisan down --retry=60
php artisan migrate --force --path=database/migrations/2026_10_14_000004_create_locations_table.php --path=database/migrations/2026_10_14_000005_add_location_permissions.php
php artisan migrate:status
# Comprobar esquema, claves, datos previos, permisos, versiones y auditoría.
php artisan up
```

Se confirmó de nuevo el destino inmediatamente antes de aplicar. Se ejecutaron únicamente las dos migraciones indicadas, sin seeders generales. Después se compararon los estados anterior/posterior mediante un verificador de solo lectura y se reabrió la aplicación solo al aprobar todas las comprobaciones.

| Comprobación principal | Resultado real |
|---|---|
| Tablas y migraciones | 19 tablas y 20 migraciones; ninguna pendiente. Las dos de HU-09 en lote 6. |
| Ubicaciones | Tabla creada y vacía; no se inventaron registros de negocio. |
| Integridad nueva | Tres FKs RESTRICT, PK, UNIQUE de código y dos CHECK; siete restricciones en total, con índices presentes. |
| Esquema probado | Definición de locations igual a testing, normalizando únicamente el contador AUTO_INCREMENT. |
| Tablas anteriores | Sus definiciones se conservaron, normalizando el contador AUTO_INCREMENT esperado por nuevas filas administrativas. |
| Permisos y vínculos | 37 permisos y 70 vínculos; todas las filas previas conservadas por huella y cantidad. |
| Roles | IDs y configuración original conservados; capacidades 37/25/8 y versiones 4. |
| Auditoría | 12 registros: nueve originales intactos y tres nuevas concesiones, acción roles.permissions_changed y origen migration. |
| Datos existentes | Usuarios, trabajadores, catálogos y las tres sesiones previas conservaron sus huellas. No se crearon cuentas reales. |
| Archivos y entorno | .env intacto; migraciones anteriores y dependencias sin cambios. |
| Mantenimiento | Cerrado mediante php artisan up después de la verificación. |

MariaDB confirma DDL implícitamente; ante un fallo se debe inspeccionar el estado y definir recuperación autorizada, sin rollback destructivo ni reintentos ciegos. Los verificadores de etapas previas están congelados a otros commits y no deben reutilizarse como procedimiento de HU-09 sin revisión.

Las 221 pruebas/1.602 verificaciones corresponden a la base exclusiva y al código aprobado. La comprobación posterior de la principal fue de solo lectura; no se ejecutaron pruebas automatizadas contra ella. La aceptación visual y HU-08/HU-10 permanecen pendientes.

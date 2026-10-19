# Sprint 2, Etapa 5 — HU-10: unidades individualizadas

Fecha: 19 de octubre de 2026, America/La_Paz.

Diseño e implementación autorizados por el usuario, incluida la actualización del repositorio. Base de partida: `eb4b476da16e6a4b2a5ff8ef347a7db973301459`, rama de trabajo `feature/hu10-individual-units`.

**Estado: código implementado y verificado; 315 pruebas y 2.411 verificaciones aprobadas el 19 de octubre de 2026. Las dos migraciones de HU-10 solo se aplican en `sga_rio_grande_testing`. La principal conserva las 25 migraciones anteriores. Aplicar HU-10 a la principal requiere una prevalidación, respaldo actual y autorización específica.**

## Alcance implementado

- Registro, listado paginado, detalle y edición restringida de unidades individualizadas.
- Búsqueda por identificador o serie; filtros por producto, ubicación inicial y estado.
- Identificador obligatorio, normalizado en mayúsculas, único globalmente e inmutable; no se reutiliza después de desactivar.
- Producto activo con control `individual`, inmutable después del registro.
- Serie opcional: recorte exterior, vacío convertido a NULL, comparación `utf8mb4_bin`, única por producto incluso entre inactivos. La serie `0` es válida. Se permite completar una serie vacía; una informada no se sustituye ni borra.
- Ubicación inicial opcional, activa, almacenable, con ancestros activos y asociación producto–ubicación activa. Se conserva incluso si inicialmente es NULL; este CRUD no permite traslados.
- Observaciones, autoría de servidor, fechas y activación/desactivación. La reactivación comprueba nuevamente las dependencias.
- `lock_version` evita sobrescrituras desde formularios desactualizados, con respuesta 409.
- El alta no incrementa existencias físicas. No hay saldos, préstamos, remitos, entregas, devoluciones ni movimientos.

Los registros inactivos conservan identidad y referencias. El bloqueo de asociaciones con unidades asignadas incluye unidades inactivas. Los productos con unidades activas no pueden desactivarse; al desactivar todas sus unidades se permite desactivar el producto. Las unidades inactivas mantienen sus datos y pueden editar observaciones con referencias históricas inactivas; reactivarlas exige referencias válidas.

## Esquema y migraciones nuevas

1. `2026_10_18_000001_create_individual_units_table.php`.
2. `2026_10_18_000002_add_individual_unit_permissions.php`.

`individual_units`: `id`, `identifier VARCHAR(64)`, `product_id`, `serial_number VARCHAR(100) NULL`, `location_id NULL`, `notes TEXT NULL`, `is_active`, `lock_version`, `created_by`, `updated_by`, timestamps. PK/FKs BIGINT UNSIGNED, InnoDB, `utf8mb4_unicode_ci` salvo la serie binaria. Estado predeterminado activo, versión inicial 1.

- UNIQUE global de identificador y UNIQUE de `(product_id,serial_number)`.
- FKs a producto, ubicación y los dos autores, con ON DELETE/UPDATE RESTRICT.
- FK compuesta `(product_id,location_id)` hacia `product_locations(product_id,location_id)`, con RESTRICT.
- La FK compuesta utiliza el índice padre existente `product_locations_pair_unique`; no modifica su tabla ni sus migraciones anteriores.
- Índices hijos para la FK compuesta y los filtros `(product_id,is_active)`, `(location_id,is_active)`.
- CHECK de estado 0/1, versión positiva, identificador no vacío y serie no vacía cuando no es NULL.
- Varias series NULL son válidas; una ubicación NULL no exige asociación, pero conserva la FK independiente de producto.

Las FKs no comprueban actividad, modo de control ni almacenamiento. Esas reglas se verifican en `SaveIndividualUnit` con lecturas bloqueantes dentro de una transacción. Las migraciones no incluyen seeders generales ni restauración de permisos revocados. Sus métodos `down()` rechazan eliminar identidad/configuración histórica.

## Autorización y concurrencia

Capacidades: `individual_units.view`, `individual_units.create`, `individual_units.update`, `individual_units.change_status`. Administrador y Encargado reciben inicialmente las cuatro; Consulta/Auditor solo consulta. Se utilizan códigos de permisos en Policy y acción; los nombres de roles no autorizan operaciones.

La inicialización solo añade los vínculos nuevos ausentes de los roles iniciales reconocidos. Respeta vínculos revocados y roles desconocidos, conserva usuarios y trabajadores, y actualiza versión/auditoría únicamente si añade capacidades. En una instalación de configuración inicial completa, el catálogo pasa de 44 a 48 permisos y de 86 a 95 vínculos.

`SecurityMutation` serializa escrituras mediante el mutex existente, vuelve a comprobar usuario, rol, permisos y versiones de sesión, y mantiene los reintentos transaccionales existentes. Luego se releen unidad, producto, asociación, ubicación y ancestros con bloqueos actuales. Se conservan las protecciones de `SaveProductLocation` y `SaveLocation`; `SaveProduct` incorpora el bloqueo por unidades activas tanto en PUT como en PATCH.

Las carreras se prueban con dos procesos y dos conexiones MariaDB, sincronización READY/GO, espera comprobada del mutex y una vista REPEATABLE READ anterior al primer commit. Casos: identificador repetido, serie repetida, edición obsoleta, alta/desactivación de producto en ambos órdenes, alta/desactivación de asociación en ambos órdenes, alta/desactivación de ubicación en ambos órdenes y revocación/alta en ambos órdenes.

Las carreras de productos y unidades usan una barrera adicional: el primer proceso conserva el mutex hasta que el coordinador confirma al segundo en `information_schema.INNODB_LOCK_WAITS`, espera 150 ms y autoriza el commit. Se mantiene la aserción de espera mínima de 100 ms. El usuario de testing necesita acceso a esos metadatos; el entorno local ya lo permite y no se conceden privilegios nuevos. Esto evita depender de una pausa fija y del tiempo de planificación de procesos de Windows.

Una ubicación con asociación activa ya está protegida contra desactivación aunque todavía no tenga unidades. Por eso, en esa carrera se rechaza desactivar y se permite el alta. Si el alta termina antes de revocar, permanece como operación autorizada previa; si la revocación termina antes, se rechaza el alta. No se promete cancelar retrospectivamente una operación confirmada.

El mutex global serializa escrituras de catálogo y administración; se conserva esa limitación de rendimiento. No se requieren paquetes, Vite ni actualizaciones de herramientas.

## Trazabilidad

| Requisito | Historia/caso | Código | Prueba y evidencia |
|---|---|---|---|
| Alta individual sin existencias | HU-10 / IND-01 | Modelo, controlador y `SaveIndividualUnit` | `IndividualUnitManagementTest` |
| Identificador reservado | IND-02 | Normalización y UNIQUE | Management, Integrity y carrera de identificador |
| Producto individual activo | IND-03 | Acción transaccional | Management, History y carreras producto/alta |
| Serie opcional y única por producto | IND-04 | Normalización, collation y UNIQUE compuesto | Management, Integrity y carrera de serie |
| Ubicación válida y asociación propia | IND-05 | Acción, `LocationHierarchy`, FK compuesta | Management, Integrity y carreras de asociación/ubicación |
| Conservación de identidad/referencias | IND-06 | Edición restringida, FKs y ausencia de DELETE | History; parte operativa pendiente del módulo futuro |
| Bloqueo de asociación con unidades | PLC-03 | `SaveProductLocation`, `ProductReferences` | History y ProductLocationManagement con unidades reales |
| Protección de ubicaciones/subárbol | LOC-05 | `SaveLocation`, `LocationReferences` | LocationManagement con unidades reales activas/inactivas |
| Permisos, sesiones, CSRF y autoría | SEC-01 a SEC-04 | Policy, Requests, `SecurityMutation` | Authorization, PermissionInitialization y concurrencia |
| Regresión de historias previas | REG-01 | Componentes existentes integrados | Suite completa en la base exclusiva |

Las pruebas de integridad anteriores ahora reconocen `individual_units` como tabla implementada y siguen exigiendo ausencia de módulos operativos futuros. Se actualizan las expectativas del catálogo de permisos. Las simulaciones de dependencias de unidades de HU-08/HU-09 se sustituyen por registros reales en testing.

## Ejecución y evidencia

Resultado final: **315 pruebas, 2.411 verificaciones, cero fallos, errores u omisiones**, exclusivamente en `sga_rio_grande_testing`, en 423.555 segundos. Incluye once carreras de HU-10 y la regresión completa anterior. Pint y `git diff --check` aprobados.

La comparación de referencias anterior/posterior confirmó igualdad de hashes de esquema y datos de las 23 tablas principales, las mismas 25 migraciones aplicadas, los archivos de esas migraciones intactos y `.env` sin cambios. La consulta Laravel confirmó `sga_rio_grande`; `migrate:status` muestra únicamente las dos migraciones nuevas pendientes. Testing tiene 27 migraciones, 48 permisos y 95 vínculos; no quedan usuarios, trabajadores, productos ni unidades de las pruebas.

Evidencia resumida y hashes de código/archivos de ejecución: [sprint-2-stage-5-verification.json](evidence/sprint-2-stage-5-verification.json). Los registros completos JUnit, consola y referencias se conservan fuera del repositorio en `C:\Users\jcsem\SGA-backups\hu10-implementation-20261019`.

Incidencias corregidas durante la verificación: comparación de atributos dependiente del orden de columnas en una prueba; normalización de un identificador de fixture insertado con minúsculas; ventana temporal insuficiente en una carrera previa de productos. Se corrigieron las pruebas y se sustituyó la pausa fija por comprobación del bloqueo real, sin debilitar la espera mínima. La última ejecución completa aprobó todos los casos.

Comandos de validación (solo testing, con los controles previos de `tests/bootstrap.php` y `tests/TestCase.php`):

```powershell
php vendor/bin/phpunit
php vendor/bin/pint --test --dirty
```

La configuración de pruebas exige `APP_ENV=testing`, conexión MySQL sin DB_URL, base exacta `sga_rio_grande_testing` y configuración sin caché. Se comprueba `SELECT DATABASE()` antes de migrar; los procesos concurrentes verifican el destino antes de crear o limpiar fixtures. La limpieza se limita a registros propios de testing. No se usan comandos de recreación de esquema.

La aceptación visual UI-01/UI-02 y la parte de IND-06 que requiere documentos operativos permanecen pendientes. Renderizar Blade en pruebas automatizadas no sustituye la revisión visual.

## Aplicación principal pendiente

No ejecutar todavía. Después de la prevalidación, respaldo completo actualizado y autorización específica, las rutas limitadas serán:

```powershell
php artisan migrate --path=database/migrations/2026_10_18_000001_create_individual_units_table.php --path=database/migrations/2026_10_18_000002_add_individual_unit_permissions.php --force
```

No volver a ejecutar las cinco migraciones de HU-08. MariaDB 10.4 no permite asumir rollback atómico de un conjunto de DDL: un fallo parcial requerirá inspeccionar tabla, restricciones y registro de migraciones antes de cualquier reparación. No ejecutar rollback ni borrar tablas para repetir automáticamente.

Hasta aplicar HU-10, la principal conserva 25 migraciones, 23 tablas, 44 permisos y 86 vínculos. El enlace de navegación está sujeto a permisos: las capacidades nuevas aún no están instaladas allí.

## Archivos

Archivos de esta entrega (51). No incluye `.env`, dependencias ni migraciones anteriores:

```text
README.md
app/Actions/Catalog/SaveIndividualUnit.php
app/Actions/Catalog/SaveProduct.php
app/Http/Controllers/Catalog/IndividualUnitController.php
app/Http/Requests/Catalog/IndividualUnitRequest.php
app/Http/Requests/Catalog/IndividualUnitSearchRequest.php
app/Http/Requests/Catalog/IndividualUnitStatusRequest.php
app/Models/IndividualUnit.php
app/Models/Location.php
app/Models/Product.php
app/Policies/IndividualUnitPolicy.php
app/Providers/AppServiceProvider.php
app/Services/ProductReferences.php
app/Support/PermissionCatalog.php
database/factories/IndividualUnitFactory.php
database/migrations/2026_10_18_000001_create_individual_units_table.php
database/migrations/2026_10_18_000002_add_individual_unit_permissions.php
docs/data-dictionary-sprint-2.md
docs/evidence/sprint-2-stage-5-verification.json
docs/global-plan.md
docs/sprint-2-stage-5.md
docs/sprint-2-test-matrix.md
docs/sprint-2.md
resources/views/catalog/individual-units/form.blade.php
resources/views/catalog/individual-units/index.blade.php
resources/views/catalog/individual-units/show.blade.php
resources/views/catalog/products/show.blade.php
resources/views/dashboard.blade.php
resources/views/layouts/app.blade.php
routes/catalog.php
tests/Feature/Catalog/AttributePermissionInitializationTest.php
tests/Feature/Catalog/CatalogIntegrityTest.php
tests/Feature/Catalog/CatalogPermissionInitializationTest.php
tests/Feature/Catalog/IndividualUnitAuthorizationTest.php
tests/Feature/Catalog/IndividualUnitConcurrencyTest.php
tests/Feature/Catalog/IndividualUnitHistoryTest.php
tests/Feature/Catalog/IndividualUnitIntegrityTest.php
tests/Feature/Catalog/IndividualUnitManagementTest.php
tests/Feature/Catalog/IndividualUnitPermissionInitializationTest.php
tests/Feature/Catalog/LocationIntegrityTest.php
tests/Feature/Catalog/LocationManagementTest.php
tests/Feature/Catalog/LocationPermissionInitializationTest.php
tests/Feature/Catalog/ProductConcurrencyTest.php
tests/Feature/Catalog/ProductIntegrityTest.php
tests/Feature/Catalog/ProductLocationManagementTest.php
tests/Feature/Catalog/ProductPermissionInitializationTest.php
tests/Feature/Catalog/TechnicalAttributeIntegrityTest.php
tests/Feature/RbacSchemaTest.php
tests/Support/CreatesIndividualUnitCatalog.php
tests/Support/concurrent-individual-unit.php
tests/Support/concurrent-product.php
```

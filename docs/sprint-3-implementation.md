# Sprint 3 — Implementación y preparación de revisión

## Estado y alcance

Rama `feature/sprint-3-inventory`, basada en `1f6d30e13dd20caa8ae1e5c1ba8c9bc5d411301c`. Se registró la conformidad declarada del Sprint 2, con reservas de pruebas personales sin evidencia. Se implementaron HU-11 a HU-17 según el [alcance autorizado](sprint-3.md).

No se aplican migraciones a la principal, no se modifica `.env`, no se crean cuentas reales ni datos demostrativos principales y no se actualizan herramientas. La implementación se verificó antes de su publicación; el usuario autorizó posteriormente actualizar el repositorio, según [el registro de publicación](sprint-3-publication.md). El entorno de aceptación del Sprint 2 se conserva sin migraciones ni datos nuevos.

## Código y comportamiento

- `SaveInventoryDocument`, `InventoryDraftValidator`: encabezado y hasta 500 líneas dentro del mutex y transacción existentes. Un solo producto/ubicación por línea; hasta 1000 identificadores únicos por documento. Autoría y estado fijados por el servidor. Edición optimista y protección frente a formularios truncados.
- `ConfirmInventoryDocument`: revalidación con lecturas bloqueantes actuales, incluso bajo una vista antigua de REPEATABLE READ. Confirma una sola vez por documento y una sola vez por línea; operaciones aritméticas SQL DECIMAL con límites y débito condicionado a saldo suficiente.
- `CancelInventoryDocument`: cancela exclusivamente borradores, conserva líneas, motivo y autoría. No afecta saldos.
- `InventoryReferences`: protege productos, asociaciones y unidades físicamente presentes. Las referencias de catálogo existentes permanecen vigentes, aunque sean más restrictivas que el saldo físico.
- `InitialStockCsv`: CSV UTF-8 de hasta 1 MiB/1000 filas/500 combinaciones. Revisión sin escritura, selección cifrada vinculada al operador con caducidad de 15 minutos y revalidación al guardar. El CSV no crea ni modifica catálogo. Guardar la revisión crea solo un borrador.
- Apertura: una reserva permanente por producto, creada al confirmar; bloqueada después de cualquier movimiento. Varias ubicaciones del mismo producto deben incluirse en un único lote. Confirmación inicialmente exclusiva de Administrador por permiso configurable.
- Reingreso externo de unidades permitido, conservando el historial y la ubicación inicial del catálogo. No implementa devoluciones de trabajadores.
- Referencia externa opcional: tipo/contraparte/referencia se indexa y advierte coincidencias; no hay bloqueo UNIQUE por esa combinación, conforme a la respuesta del usuario.
- `InventoryDocumentPolicy`, Gates y 14 permisos nuevos. Migración de permisos congelada, aditiva, idempotente, con versiones/auditoría de concesiones y respeto a revocaciones y roles desconocidos.
- Blade, CSS existente, JavaScript nativo solo para añadir/quitar líneas. Formularios manuales permiten añadir hasta 100 líneas; el servidor y los lotes CSV admiten hasta 500. Sin npm, conversiones automáticas o paquetes XLSX.

## Pruebas y evidencias

La [matriz](sprint-3-test-matrix.md) diferencia casos planificados y resultados ejecutados. PHPUnit exige `sga_rio_grande_testing` en configuración y `SELECT DATABASE()` antes de aplicar migraciones; las carreras vuelven a comprobar el destino y limpian exclusivamente sus registros identificados. No se ejecutan pruebas sobre la principal.

Las trece carreras nuevas comprueban espera real en `information_schema.INNODB_LOCK_WAITS`: reintento, retiros competidores, aperturas, doble recepción de identidad, confirmación frente a desactivación de producto/asociación/unidad en ambos órdenes, revocación y cancelación en ambos órdenes. Además contrastan el saldo con la suma exacta de movimientos. Son pruebas de coherencia, no una medición de carga.

Resultados y JUnit: [evidencia agregada](evidence/sprint-3-implementation.json). No se atribuyen pruebas personales ni capturas de navegador del Sprint 3 a los tests HTTP.

Verificación focalizada con código congelado: **33 pruebas, 218 aserciones, cero fallos**, incluyendo los dos casos adicionales de recepción frente a desactivación individual. Las once carreras anteriores también se ejecutaron: 11 pruebas/143 aserciones aprobadas.

Regresión completa posterior: **363 pruebas, 2.783 aserciones, cero fallos de aserción, un error de timeout**, sin omisiones. Los **44 casos nuevos de inventario, incluidas 13 carreras, pasaron**. El error afectó a `ProductConcurrencyTest`, escenario anterior `stale edit`: un proceso superó los 120 segundos mientras el test esperaba observar el bloqueo en MariaDB. No se atribuye una causa no comprobada.

Repeticiones sin modificar código o límites: el escenario aislado pasó (**1 prueba/15 aserciones**) y el grupo completo de carreras de productos pasó (**7 pruebas/99 aserciones**). **No se declara una ejecución completa única sin errores.** La reserva `TEST-S3-01` requiere seguimiento de estabilidad; no se relajaron bloqueos, comprobaciones o timeouts para ocultarla. JUnit conserva el error original y las repeticiones aprobadas.

Comandos ejecutados: `php vendor/bin/phpunit --log-junit <archivo externo>` para la suite completa; `php vendor/bin/phpunit tests/Feature/Catalog/ProductConcurrencyTest.php --filter 'stale edit' --log-junit <archivo externo>` y el mismo archivo sin filtro para las repeticiones. Los nombres exactos, conteos y hashes de cada evidencia figuran en el JSON agregado. `Pint --test` de los PHP nuevos/modificados y `git diff --check` pasaron.

Inspección final de testing: **31 tablas, 29 migraciones, 62 permisos, 126 vínculos**, veinte claves foráneas restrictivas y 54 restricciones en las siete tablas nuevas, además del índice UNIQUE aditivo en `individual_units`. Los registros temporales de usuarios, trabajadores, productos, unidades, documentos, saldos, movimientos y aperturas quedaron en cero. Los hashes de las fuentes congeladas coinciden después de las ejecuciones.

Las ejecuciones de desarrollo conservaron un fallo de comparación de un objeto recién creado con uno recargado, corregido comparando ambos estados persistidos, y una primera regresión de 361 casos con dos fallos de vistas al modificar código durante su ejecución. Esos resultados no se declaran aprobados ni sustituyen la regresión final con fuentes congeladas.

## Conservación de la principal comprobada

Consultas de solo lectura verificaron `SELECT DATABASE() = sga_rio_grande`, PHP 8.3.10, Laravel 13.35.0 y MariaDB 10.4.32. La comparación anterior/posterior confirmó **24 tablas, 27 migraciones aplicadas, 48 permisos, 95 vínculos y cero usuarios**. Las 24 estructuras, conteos y hashes de datos coinciden; `.env` y las 27 migraciones anteriores conservan sus hashes. Solo hay dos migraciones nuevas pendientes. No existe aún un administrador real y no se creó ninguna cuenta.

Las instantáneas de lectura y registros se conservan fuera del repositorio, en `C:\Users\jcsem\SGA-acceptance\sprint-2-20261019\sprint-3-implementation`. No son respaldos SQL restaurables y no se ha creado un respaldo principal en esta etapa.

## Migraciones principales pendientes

1. `2026_10_19_000001_create_inventory_tables.php`: siete tablas, restricciones y un índice aditivo en `individual_units`.
2. `2026_10_19_000002_add_inventory_permissions.php`: 14 permisos y concesiones iniciales sin restaurar vínculos revocados.

**Comandos preparados; no ejecutados sobre la principal:** después de prevalidación, respaldo actual verificado y autorización específica:

```powershell
php artisan migrate:status --database=mysql
php artisan migrate --database=mysql --path=database/migrations/2026_10_19_000001_create_inventory_tables.php --path=database/migrations/2026_10_19_000002_add_inventory_permissions.php --force
php artisan migrate:status --database=mysql
```

Antes: comprobar `SELECT DATABASE() = sga_rio_grande`, versiones, diferencias publicadas y exactamente estas dos pendientes; capturar hashes y conteos de tablas, usuarios, roles, trabajadores y permisos; crear un respaldo SQL completo nuevo fuera del repositorio, verificar SHA-256 y completar un ensayo de restauración en un destino separado autorizado. No sustituirlo por las instantáneas de lectura de esta implementación.

Comprobar también `SELECT @@default_storage_engine, @@foreign_key_checks, @@sql_mode`: motor predeterminado InnoDB, claves foráneas habilitadas y modo estricto. Las creaciones utilizan el motor predeterminado de la conexión; no aplicar sobre una instalación configurada con otro motor.

Después: comparar todas las tablas y datos previos salvo concesiones/versiones/auditoría expresamente nuevas, comprobar claves compuestas y CHECK con consultas de esquema, no con datos ficticios. Con la configuración inicial intacta se esperan **31 tablas, 29 migraciones, 62 permisos y 126 vínculos**; son expectativas de un futuro despliegue, no resultados de la principal. Ningún saldo o movimiento se crea por migrar.

MariaDB ejecuta DDL con commits implícitos: si falla una creación parcial, no repetir a ciegas ni ejecutar rollback destructivo. Detener escrituras, conservar error y estado de esquema/migrations, identificar el último objeto creado y preparar recuperación revisada desde el respaldo actual. El método `down()` bloquea borrado automático del inventario. Cualquier restauración o retirada de objetos parciales requiere autorización específica.

## Riesgos y revisiones pendientes

- `TEST-S3-01`: timeout no reproducido en el escenario aislado ni en el grupo completo repetido; investigar estabilidad antes de considerar una regresión completa única aprobada.
- Los límites máximos de tamaño/filas y variantes de codificación del CSV están implementados, pero faltan casos de borde automatizados específicos. La matriz lo distingue de los cuatro casos de importación ejecutados.
- Revisión funcional y visual del Sprint 3 pendiente; no se reutiliza el entorno del Sprint 2 para simular aprobación de pantallas nuevas.
- El mutex global serializa escrituras administrativas, de catálogo y de inventario; evaluar rendimiento antes de uso intensivo. Límites de carga/formulario son deliberados.
- Permisos SQL del operador de mantenimiento siguen permitiendo cambios directos en la base. La aplicación no ofrece edición/borrado de documentos cerrados ni movimientos; las FK/CHECK no constituyen un sistema contra un administrador SQL malicioso.
- Las confirmaciones tienen fecha física de registro; la fecha comercial y el corte se conservan sin reconstruir saldos retroactivos.
- Las correcciones de documentos confirmados necesitan compensaciones autorizadas posteriores, sin eliminar el historial. No hay transferencias, entregas, préstamos, devoluciones ni ajustes libres en esta etapa.
- El evento interno `inventory.line_posted` se emite dentro de la transacción para comprobación de atomicidad. Cualquier futuro efecto externo debe diferirse hasta commit.
- MariaDB 10.4 y el Apache local con PHP 8.2 mantienen las limitaciones de entorno previamente documentadas. No se actualiza el entorno.

Listado completo: [archivos nuevos/modificados](sprint-3-changed-files.md). Guion de navegador: [aceptación pendiente](sprint-3-acceptance.md). La publicación del código fue autorizada posteriormente; el despliegue principal sigue pendiente y se mantiene separado.

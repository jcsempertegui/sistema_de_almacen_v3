# Sprint 4 — Informe de implementación local

## Autorización

El usuario aprobó expresamente HU-18 a HU-23, las once tablas y la alternativa A. Rama de trabajo: `feature/sprint-4-worker-operations`, base `cd7b4eabc031`. La autorización inicial se limitó a código y pruebas aisladas. Posteriormente autorizó la publicación y, por separado, el respaldo, restauración de ensayo y despliegue principal completado el 20 de octubre. Véase [seguimiento de despliegue](sprint-4-deployment.md).

Estado final: **HU-18 a HU-23 implementadas localmente y regresión completa aprobada: 401 pruebas, 3.143 aserciones, cero errores, fallos u omisiones**. Consola: 15:15,932; tiempo agregado JUnit: 915,473326 segundos. Fuentes congeladas: 323 archivos sin cambios durante la ejecución. No inferir aceptación humana ni autorización de despliegue.

## Implementación

- HU-18/19: SaveDelivery y ConfirmDelivery; productos por cantidad e individuales no prestables, trabajador activo, edición optimista y cancelación de borrador. Guardar no reserva existencias. La confirmación utiliza débito condicionado y origen único.
- HU-20: SaveLoan y ConfirmLoan; herramientas activas prestables, identificadas, físicamente presentes; vencimiento opcional; custodia condicional exclusiva con FK al movimiento de salida.
- HU-21: SaveReturn y ConfirmReturn; un préstamo por documento, ítems originales, recepción parcial o total en destino manual. Trabajador inactivo no bloquea el retorno. Cierre por ítem con PK y FK obligatoria al movimiento físico. La última recepción pendiente deja el préstamo completamente devuelto por consulta derivada, sin cierre administrativo separado.
- HU-21 excepcional: ReturnEligibility y ReturnRegularization; solicitud, aprobación, revocación y consumo auditados; huella de contenido y referencias; caducidad de 24 horas desde aprobar. Un solo usuario puede preparar y aprobar si posee ambas capacidades. Referencias inactivas no se reactivan. Destino siempre activo, almacenable, con ancestros válidos y asociación existente. No autoriza reinterpretar identidad, unidad o producto.
- HU-22/23: listados, filtros, detalle, responsabilidades pendientes y consulta de disponibilidad. Diferencia saldo físico de disponibilidad operativa calculada; no almacena un segundo saldo. Historial reconoce remitos/aperturas, entregas, préstamos y devoluciones.

Confirmaciones dentro de SecurityMutation, con permisos vigentes y mutex compartido; lecturas bloqueantes actuales y versiones optimistas. Reintento confirmado exige autorización vigente y devuelve el resultado existente. Las pruebas entre procesos conservan una vista REPEATABLE READ anterior al ganador y exigen observar espera real en MariaDB.

Los documentos, sus ítems confirmados, movimientos, cierres y eventos se conservan. Las FK/CHECK no pretenden impedir que un administrador SQL altere datos arbitrariamente fuera de la aplicación. No hay ajustes, compensaciones ni anulaciones de confirmados.

## Compatibilidad S3

ConfirmInventoryDocument verifica custodia antes de operar con una identidad y rechaza el reingreso externo de una herramienta prestada. Reingresos externos ordinarios siguen permitidos tras egresos externos. Las escrituras S3 limpian las tres nuevas referencias de última posición; las S4 limpian la referencia S3 y las otras fuentes.

Las comprobaciones de nuevas tablas/columnas en las acciones S3 permiten conservar la ruta anterior mientras la principal no se ha migrado. Las pantallas nuevas necesitan el despliegue autorizado de esquema y permisos. No conceder permisos manualmente para intentar usarlas antes.

Se mantienen DECIMAL(18,6), precisión de unidad y la reserva permanente de apertura. El historial sigue único; no se crea un remito paralelo para contabilizar una entrega o préstamo. Se amplían las referencias históricas del catálogo para reconocer los documentos S4.

## Ensayo de migración ejecutado

Destino confirmado: `sga_rio_grande_testing`, inicialmente con 29 migraciones y tablas de negocio vacías. El script `tests/Support/verify-sprint4-upgrade.php` creó seis documentos S3 mediante sus acciones: ingreso/egreso por cantidad, recepción/egreso/reingreso individual y apertura. Aplicó únicamente las seis migraciones nuevas y comprobó los campos originales antes/después.

Resultado real: seis documentos, seis líneas, tres pertenencias de identidad, seis movimientos, tres saldos, una presencia y una reserva de apertura conservados. Testing quedó con **42 tablas, 35 migraciones y 81 permisos**. Limpió exclusivamente los registros propios; conservó esquema, permisos y auditoría de migración. No se ejecutó fresh/reset/wipe ni se deshabilitaron FK/CHECK.

Las nuevas migraciones son:

1. `2026_10_19_000003_create_worker_operation_documents.php`.
2. `2026_10_19_000004_create_return_regularizations.php`.
3. `2026_10_19_000005_extend_inventory_movement_sources.php`.
4. `2026_10_19_000006_create_loan_custodies_and_settlements.php`.
5. `2026_10_19_000007_extend_individual_unit_position_sources.php`.
6. `2026_10_19_000008_add_worker_operation_permissions.php`.

El script rechaza ejecutarse contra el esquema ya actualizado; no es un comando para repetir el despliegue ni para migrar la principal. Los métodos down bloquean retiradas destructivas.

## Permisos

Diecinueve códigos nuevos. Administrador recibe 19, Encargado 17 (excluidas aprobación/revocación de regularizaciones), Consulta/Auditor cuatro consultas. Configuración inicial resultante en testing: 81 permisos y 166 vínculos; capacidades activas 81/66/19. Migración conserva revocaciones, roles desconocidos y personas, actualiza versiones y audita solo nuevas concesiones. No ejecuta seeders generales ni crea cuentas.

Las pruebas antiguas que fijaban 62 permisos/126 vínculos se actualizan a la configuración completa de 81/166; conservan las aserciones de concesiones, revocaciones, personas, auditoría y sesiones.

## Incidencias de desarrollo y reservas

- CSV-NUL: el nuevo caso descubrió que trim eliminaba NUL antes de validarlo. Corregido validando los bytes originales; primera etapa repetida: 14 pruebas/168 aserciones aprobadas.
- TEST-S3-01: diagnóstico por fase, salida de procesos y esperas SQL al fallar. Detecta terminación anticipada sin esperar una barrera imposible. No cambia los 120 segundos, ni evita la espera SQL, ni relaja la protección frente a edición obsoleta. La causa estaba sin determinar en el informe histórico; nueva evidencia correlaciona ese caso y un timeout S4 con suspensiones de Windows. No se reescribe el JUnit original.
- Fixture de carreras S4: primera ejecución falló porque Role no dispone de factory. Corregido creando explícitamente un rol aislado; repetición inicial de nueve carreras aprobada con 135 aserciones.
- Primera regresión completa: 399 pruebas/3.121 aserciones, un error y tres fallos. El test posterior a devolución usaba la ubicación de retiro anterior; se corrigió para utilizar el destino efectivo. Se actualizaron los conteos RBAC antiguos y la búsqueda de auditoría para no depender de la primera página. El timeout de devolución coincidió con suspensión del equipo. La ejecución no se considera aprobada.
- Repetición focalizada posterior: 47 pruebas/400 aserciones aprobadas en 4:33,544, incluyendo los casos afectados y los grupos completos de carreras S4/productos. La suite completa final se registra por separado.
- Regresión final: 401 pruebas/3.143 aserciones aprobadas. Incluye las once carreras S4 y todos los escenarios anteriores de Sprints 1–3. JUnit, fuentes congeladas, hashes y resultados de todas las ejecuciones: [evidencia agregada](evidence/sprint-4-implementation.json). No se suman como casos distintos las ejecuciones focalizadas superpuestas.
- El mutex global serializa escrituras; no se presenta esta suite como prueba de carga.
- Navegador/aceptación humana: pendientes. No se crearon datos de aceptación ni cuentas reales.

Resultados focalizados, trazabilidad y casos humanos: [matriz](sprint-4-test-matrix.md). Estructuras: [diccionario](data-dictionary-sprint-4.md). La evidencia original se conserva fuera del repositorio en `C:\Users\jcsem\SGA-evidence\sprint-4`.

## Estabilidad del entorno de pruebas

El caso histórico stale edit duró 882,558 segundos. Con el JUnit original se estima su ventana 13:34:07–13:48:50; Windows registra suspensión 13:34:09–13:48:25. El caso return retry de la primera regresión S4 duró 1.593,073 segundos y también se superpone a una suspensión 16:54:17–17:20:31. La reconstrucción de ventanas usa mtime del JUnit original menos duraciones ordenadas y es aproximada. SleepTime/WakeTime proceden del registro System/Power-Troubleshooter. [Evidencia y hashes](evidence/sprint-4-stability.json).

Las repeticiones utilizan temporalmente `SetThreadExecutionState(ES_CONTINUOUS | ES_SYSTEM_REQUIRED)` en el proceso PowerShell que espera a PHPUnit y restauran el estado anterior en finally. No cambian el plan de energía, timeouts, `.env` ni política de ejecución. Se usaron comandos de consola permitidos; no se habilitó ejecución de scripts restringidos. Una orden manual de suspensión no queda cubierta por esta protección y debe evitarse durante las mediciones.

La línea PHP de la suite final es `php -d opcache.enable_cli=1 vendor/bin/phpunit --do-not-cache-result --log-junit <directorio externo>/regression-final.xml`, dentro de esa protección temporal. Los controles de destino siguen en bootstrap y TestCase; el proceso no puede utilizar una base distinta de testing.

## Verificación final de conservación e integridad

Consultas en transacciones de solo lectura verificaron PHP 8.3.10, Laravel 13.35.0 y MariaDB 10.4.32. La principal conserva **31 tablas, 29 migraciones, 62 permisos, 126 vínculos y cero usuarios**. Los conteos y SHA-256 de las filas de las 31 tablas coinciden con la captura anterior al trabajo. `.env` y las 29 migraciones anteriores conservan sus hashes. Dependencias sin cambios.

Testing tiene **42 tablas, 35 migraciones, ninguna pendiente, 81 permisos y 166 vínculos**; roles iniciales versión 8 con 81/66/19 capacidades activas. Usuarios, trabajadores, catálogo de prueba, documentos, movimientos, saldos, custodias, cierres y regularizaciones temporales quedaron en cero. Los huecos de AUTO_INCREMENT de fixtures no se reinician.

Se revisaron **63 FK/44 CHECK/63 índices únicos incluyendo PK en la principal** y **110 FK/71 CHECK/100 índices únicos incluyendo PK en testing**. Cero huérfanos, incumplimientos de CHECK o grupos duplicados; sin FK entre esquemas. InnoDB y restricciones habilitados. Pint --test y git diff --check aprobados.

El ensayo anterior se conserva en [sprint-4-upgrade.json](evidence/sprint-4-upgrade.json). El [listado exacto de archivos](sprint-4-changed-files.md) distingue nuevos y modificados. Los hashes públicos de fuentes corresponden al árbol de trabajo validado, anterior a cualquier commit; Git puede normalizar finales de línea al publicarlo.

## Principal y publicación

El despliegue requirió prevalidación, respaldo actual, ensayo de restauración y autorización específica; esas condiciones se cumplieron antes de aplicarlo. No repetir las migraciones S3/S4 ni ejecutar comandos destructivos o pruebas sobre la principal. No modificar `.env` o dependencias.

Al cerrar la implementación todavía no se había realizado commit, merge ni push. Posteriormente el usuario declaró «autorizado y aceptado. actualiza el repositorio», autorizando la integración y publicación. El [registro de seguimiento](sprint-4-publication.md) distingue esa aceptación declarada de los casos humanos sin evidencia y del despliegue principal todavía pendiente. El JSON de implementación conserva la captura histórica anterior a la publicación.

Actualización del 20 de octubre: las seis migraciones enumeradas ya se aplicaron con autorización en lote 14. Principal verificada: 42 tablas, 35 migraciones, ninguna pendiente, 81 permisos y 166 vínculos. Se conservaron datos y configuraciones previas. Véanse [despliegue y respaldo](sprint-4-deployment.md) y [evidencia](evidence/sprint-4-deployment.json). Las capturas anteriores de este informe y su JSON describen el cierre de implementación, antes del despliegue. No repetir las migraciones ni ejecutar pruebas automatizadas sobre la principal.

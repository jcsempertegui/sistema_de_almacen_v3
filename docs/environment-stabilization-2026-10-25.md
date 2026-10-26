# Estabilización del entorno y preparación del cierre final

Fecha: 25 de octubre de 2026, America/La_Paz. Base revisada: `main`, commit `a6e10b7fa2766ec0ecbba9316c786a62cbdb698a`, árbol inicialmente limpio. Alcance autorizado: configuración, aislamiento, pruebas y documentación; no otro sprint. Cambios locales preparados para revisión, sin commit, merge ni push.

## Diagnóstico de solo lectura

| Elemento | Estado verificado |
|---|---|
| PHP válido | 8.3.36, `D:\PHP83\php.exe`; INI `D:\PHP83\php.ini` |
| PHP del PATH | XAMPP 8.2.12, incompatible; no se cambió el PATH ni su INI |
| Composer | 2.10.3; `check-platform-reqs` aprobado con PHP 8.3 |
| Laravel | 13.35.0, dependencias existentes sin actualizar |
| Servidor | DESKTOP-L0DMVPG, MariaDB 10.4.32, puerto 3306 |
| Directorio SQL real | `D:\XAMPP\mysql\data\` |
| Principal | `sga_rio_grande`: 53 tablas, 43 migraciones, todas en lote 1, ninguna pendiente ni ajena al repositorio |
| Testing | `sga_rio_grande_testing`: 53 tablas, 43 migraciones en lote 1, ninguna pendiente ni ajena al repositorio |
| Datos principales al inspeccionar | un usuario local, cero trabajadores, productos, movimientos, saldos, ciclos y solicitudes de Backup |

La principal y testing son esquemas distintos en la misma instancia. La instalación actual no restauró la información operativa del otro PC. Los lotes 14 y 15 de los informes históricos no describen esta instalación nueva. El administrador local fue creado después de instalar, el 24 de octubre; no se alteró su cuenta ni su contraseña.

`scripts/inspect-local-environment.php` se ejecutó antes de las pruebas y al terminar. Cada conexión comprobó `SELECT DATABASE()`, servidor, puerto y directorio de datos; leyó filas y DDL en una transacción SQL de solo lectura. Solo se conservaron conteos y huellas, sin SQL restaurable ni valores de filas. La [evidencia resumida](evidence/environment-stabilization-2026-10-25.json) registra los resultados.

## Portabilidad implementada de Backup

`config/sga.php` permite las variables de proceso opcionales `SGA_MARIADB_BIN`, `SGA_MARIADB_VERSION` y `SGA_BACKUP_DIRECTORY`, sin editar `.env`. Sin directorio explícito, el cliente busca XAMPP en la unidad de PHP y del sistema, después PATH; en otros sistemas, `/usr/bin` y PATH. Un directorio explícito inválido o un cliente encontrado incompatible provoca rechazo.

Se soportan los nombres MariaDB modernos y los compatibles `mysqldump`/`mysql`, con sufijo Windows cuando corresponde. La versión se verifica mediante un proceso `--version`, sin conectar al servidor ni pasar contraseñas; la serie predeterminada 10.4 admite revisiones de parche distintas. Los errores no incorporan la salida arbitraria del proceso.

El comando `sga:check-backup-client` comprobó realmente solo versiones: `D:\XAMPP\mysql\bin\mysqldump.exe` y `mysql.exe`, ambos 10.4.32. No se generó SQL ni se ejecutó restauración. Los archivos SQL creados por las pruebas existentes de orquestación usan `FakeSqlBackupClient`, exclusivamente con fixtures de testing; no representan un respaldo real.

El destino privado de respaldos sigue fuera del repositorio. La restauración de bajo nivel rechaza principal, testing y nombres que no cumplan el patrón de ensayo; el servicio conserva sus aprobaciones, vigencia, confirmación y rechazo de destinos existentes. Al clonar la configuración Laravel del ensayo se fija ahora `name=sga_rehearsal`, evitando heredar `mysql`. Esa rama no se ejecutó con una restauración real en esta revisión.

## Aislamiento reforzado

Se revisó el [incidente histórico S5](sprint-5-final-validation.md): verificar una conexión aislada no bastó cuando factories reutilizaron otro alias interno. La solución de esta revisión impide esa reutilización en los puntos de entrada de testing:

1. El bootstrap valida configuración sin caché y compara testing con la identidad leída del servidor local configurado, antes de cualquier fixture o migración.
2. Fija la identidad en memoria y Laravel conserva únicamente `sga_testing`, con el mismo nombre interno. Purga conexiones previamente resueltas; elimina las demás configuraciones del proceso de prueba. Las factories y Artisan usan ese destino.
3. El guardián verifica alias, configuración y PDO real, incluyendo host, puerto, base, servidor y directorio. Rechaza URL, socket y conexiones con destinos de lectura/escritura separados. Vuelve a comprobar la identidad antes de las consultas Laravel; bloquea `USE`, DDL de bases y referencias cualificadas a la principal. Instala el bloqueo antes de rechazar una conexión, para conservarlo también si Laravel dejó el objeto rechazado en caché; se probó un segundo intento sobre ese mismo objeto.
4. Los PDO auxiliares concurrentes pasan por el mismo verificador. Los siete trabajadores heredan explícitamente identidad y variables, ejecutan el bootstrap común y abortan si hay deriva antes de cargar el trabajo.
5. TestCase comprueba el guardián antes de las migraciones normales de testing. Se conservaron barreras, consultas InnoDB, pausas de refresco y tiempos máximos existentes; no se aumentaron los límites para ocultar fallos.

La prevalidación independiente, antes de ejecutar PHPUnit, confirmó `default=sga_testing`, `name=sga_testing`, única configuración disponible y la identidad real de testing en el puerto/directorio esperados. Los negativos no enviaron SQL destructivo al servidor: las cadenas peligrosas se clasificaron sin ejecutarlas; una conexión principal simulada se rechazó sin abrir su PDO; el gancho de consultas se comprobó mediante un SELECT inocuo. La factory con alias histórico se probó apuntándolo deliberadamente a testing, nunca a la principal.

## Resultados de validación

| Ejecución | Resultado real |
|---|---|
| Diagnóstico de binarios | ambos clientes 10.4.32; solo `--version` |
| Prevalidación de guardián | identidad y alias exclusivos confirmados mediante lecturas |
| Primera selección: aislamiento y configuración Backup | 29 pruebas, 65 aserciones, aprobadas en 4,577 s |
| Primera regresión completa | 458 pruebas, 3.482 aserciones, aprobadas en 1 min 55,977 s |
| Selección final, incluido reintento de conexión rechazada | 29 pruebas, 67 aserciones, aprobadas en 3,479 s |
| Regresión completa final tras esa revisión | **458 pruebas, 3.485 aserciones, aprobadas en 1 min 43,651 s**, memoria 86 MB |
| Estilo | Pint aprobado sobre los 27 archivos PHP modificados/nuevos |
| Espacios y errores de diff | `git diff --check` aprobado |
| Comparación principal | cero diferencias en filas, DDL y AUTO_INCREMENT de las 53 tablas |
| Configuración/dependencias | `.env`, `composer.json`, `composer.lock` y `package.json` conservados |
| Migraciones | hashes de los 43 archivos idénticos; ninguna nueva migración |
| Limpieza testing | solo tablas iniciales con filas: roles 3, permisos 123, relaciones 260, auditorías de migración 27 y migraciones 43; resto vacío |

El primer intento de la selección de clientes se detuvo tras seis casos completados: un stub vacío no ejecutable provocó una espera de proceso en Windows. No se atribuye un resultado aprobado a esa ejecución interrumpida. Se sustituyó ese negativo por una simulación del fallo de proceso, y se retiraron únicamente sus dos stubs vacíos y su carpeta temporal propia. Después pasaron la selección de 29 casos y la regresión completa. No fue un intento de respaldo/restauración.

La preparación de este PC había aprobado 429/3.414. La evidencia del PC anterior conserva 429/3.406 y su tanda posterior de presentación 3/75. La nueva tanda incluye 29 casos adicionales; cada total de aserciones corresponde a su ejecución y no se obtiene sumando tandas parciales ni contando las presentaciones como casos independientes.

## Documentación y cierre

- [Manual de instalación](installation.md): versiones, arranque explícito, configuración portable, restricciones e identidad de procesos.
- [Guion de aceptación funcional](final-functional-acceptance.md): casos de todos los módulos, lectura SQL frente a operaciones en aceptación, registro y decisión humana. Preparado, no ejecutado.
- [Propuesta académica](academic-traceability-proposal.md): trazabilidad técnica de HU-01 a HU-29 y plantilla de relación muchos-a-muchos. Sin fuentes externas todavía; objetivos, requisitos 2.0 e historias históricas no se declaran contrastados.
- README actualizado y anexo al registro local, conservando los informes S1–S5 y sus resultados originales.

## Pendientes y límites

Se espera la tesis anterior, la versión 2.0 y los enunciados HU1–HU13 que el usuario anunció que enviará. No se inventaron identificadores de requisitos ni se modificó la tesis original. La propuesta no supone aprobación académica.

No se ejecutó revisión visual final, generación real de Backup ni restauración en este equipo. Requieren sus entornos y autorizaciones específicos. El reconocimiento de la serie de cliente no sustituye un ensayo de recuperación ni certifica otras versiones de servidor.

El guardián cubre Laravel y los auxiliares revisados. Un programa externo que cree su propio PDO/cliente SQL con credenciales amplias puede evitar esas defensas; no se afirma imposibilidad universal ni se modificaron usuarios/concesiones SQL. Una futura separación de credenciales por esquema debe aprobarse como operación administrativa. El entorno de aceptación aún no se creó.

No se ejecutaron migraciones, seeders, operaciones de inventario, HTTP de aceptación ni escrituras de aplicación en la principal. No se cambiaron `.env`, APP_KEY, dependencias, versiones o migraciones; tampoco se eliminaron registros existentes, se importaron respaldos o se crearon nuevas bases. La comparación final de la principal respalda estas restricciones. No se realizaron commits, merges o push; corresponde revisar y autorizar su publicación posteriormente.

## Archivos modificados o nuevos

Son 34 archivos (27 PHP y siete de documentación/evidencia). No contienen archivos privados:

```text
README.md
app/Console/Commands/CheckBackupClient.php
app/Providers/AppServiceProvider.php
app/Services/DatabaseBackups.php
app/Services/SqlBackupClient.php
app/Support/TestingDatabaseGuard.php
config/sga.php
docs/academic-traceability-proposal.md
docs/environment-stabilization-2026-10-25.md
docs/evidence/environment-stabilization-2026-10-25.json
docs/final-functional-acceptance.md
docs/installation.md
docs/local-environment-2026-10-23.md
scripts/inspect-local-environment.php
tests/Feature/AdministrativeConcurrencyTest.php
tests/Feature/Catalog/IndividualUnitConcurrencyTest.php
tests/Feature/Catalog/LocationConcurrencyTest.php
tests/Feature/Catalog/ProductConcurrencyTest.php
tests/Feature/Inventory/CorrectionConcurrencyTest.php
tests/Feature/Inventory/InventoryConcurrencyTest.php
tests/Feature/Inventory/SqlBackupClientConfigurationTest.php
tests/Feature/Inventory/WorkerOperationConcurrencyTest.php
tests/Feature/TestingDatabaseIsolationTest.php
tests/Support/concurrent-administration.php
tests/Support/concurrent-correction.php
tests/Support/concurrent-individual-unit.php
tests/Support/concurrent-inventory.php
tests/Support/concurrent-location.php
tests/Support/concurrent-product.php
tests/Support/concurrent-worker-operation.php
tests/Support/probe-isolation.php
tests/Support/verify-sprint5-backup.php
tests/TestCase.php
tests/bootstrap.php
```

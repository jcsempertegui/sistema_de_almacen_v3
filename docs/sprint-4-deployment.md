# Sprint 4 — Despliegue autorizado

Fecha: **20 de octubre de 2026**, America/La_Paz. Código desplegado: `691d80f7e8af678b7c6ae707d945df365d6380c2`.

El usuario autorizó expresamente el procedimiento de prevalidación, respaldo nuevo, restauración de ensayo y aplicación exclusiva de las seis migraciones del Sprint 4. Después confirmó «todo esta perfecto» y solicitó actualizar el repositorio. Se conserva su aceptación declarada; los casos personales sin evidencias de navegador no se completan retrospectivamente. No se inicia el Sprint 5.

## Respaldo y restauración de ensayo

Antes de modificar el esquema se verificaron Git limpio y sincronizado, PHP 8.3.10, Laravel 13.35.0, MariaDB 10.4.32 y `SELECT DATABASE() = sga_rio_grande`. Había 31 tablas InnoDB, 29 migraciones aplicadas y exactamente seis pendientes. Sin DDL parcial ni violaciones de integridad.

Se activó mantenimiento temporal y se creó un respaldo completo nuevo con el cliente MariaDB 10.4.32 de `C:\xampp\mysql\bin`, sin utilizar el cliente MySQL 8 del PATH.

- Archivo privado: `C:\Users\jcsem\SGA-backups\sprint4-deployment-20261020-99d4ebdc\sga_rio_grande_pre_sprint4.sql`.
- Tamaño: **119.013 bytes**; **31 tablas y 244 filas**.
- SHA-256: `66e46fa19774b48c2e88763c3dca4b44a5177adf805579a313b03f6f1595964d`.
- Snapshot InnoDB, estructura y datos, opciones de rutinas/eventos/triggers, cierre completo, lista de tablas y registros por tabla verificados. Sin DROP TABLE/DATABASE, CREATE DATABASE, USE ni referencias calificadas hacia la principal dentro del SQL.
- Directorio protegido para el usuario local y SYSTEM; archivos temporales de credenciales retirados.

Se restauró en la base nueva **`sga_rio_grande_restore_s4_99d4ebdc`**, distinta de la principal, testing y aceptación. Coincidieron los hashes de todas las filas y las 31 estructuras lógicas, excluidos contadores AUTO_INCREMENT. Se comprobaron 63 FK, 44 CHECK y 63 índices únicos, sin huérfanos, incumplimientos ni duplicados. La base de ensayo se conserva y no recibió pruebas automatizadas.

La principal permaneció idéntica durante el respaldo y la restauración. Antes de migrar se verificaron nuevamente su captura y el SHA-256 del SQL.

## Migraciones realmente aplicadas

Las seis finalizaron correctamente en el **lote 14**, registros 30 a 35:

| Orden | Migración |
|---|---|
| 1 | `2026_10_19_000003_create_worker_operation_documents` |
| 2 | `2026_10_19_000004_create_return_regularizations` |
| 3 | `2026_10_19_000005_extend_inventory_movement_sources` |
| 4 | `2026_10_19_000006_create_loan_custodies_and_settlements` |
| 5 | `2026_10_19_000007_extend_individual_unit_position_sources` |
| 6 | `2026_10_19_000008_add_worker_operation_permissions` |

El comando utilizó `php artisan migrate --database=mysql --force` limitado mediante seis opciones `--path` a esos archivos. No repetirlo para este despliegue. No se ejecutaron seeders ni migraciones anteriores. No hubo fallos ni recuperación parcial de DDL.

## Resultados y conservación

| Comprobación | Antes | Después verificado |
|---|---:|---:|
| Tablas | 31 | 42 |
| Migraciones aplicadas | 29 | 35 |
| Migraciones pendientes | 6 | 0 |
| Permisos | 62 | 81 |
| Relaciones rol–permiso | 126 | 166 |
| Auditorías administrativas | 21 | 24 |
| Usuarios / trabajadores | 0 / 0 | 0 / 0 |

Se conservaron las filas anteriores, incluidos los 62 permisos y 126 vínculos, sin restaurar revocaciones. Solo se añadieron 19 permisos y 40 concesiones previstas: Administrador 19, Encargado 17 y Consulta/Auditor cuatro. Capacidades activas finales **81/66/19**; versiones de roles de 7 a 8, con tres auditorías que registran las concesiones y ninguna revocación.

Las once tablas nuevas quedaron vacías. Los documentos, movimientos, saldos y presencias anteriores también estaban vacíos: no se presenta esa conservación como un ensayo principal con movimientos históricos. La prueba representativa previa con seis documentos y movimientos S3 permanece documentada en [sprint-4-upgrade.json](evidence/sprint-4-upgrade.json), exclusivamente en testing.

Las dos tablas ampliadas coinciden con el esquema probado: se conservan las columnas originales y se añaden las fuentes documentales, sus índices, FK y CHECK. `document_line_id` y `last_document_line_id` admiten NULL manteniendo BIGINT UNSIGNED. Las otras 29 estructuras anteriores permanecen iguales; las once nuevas también coinciden con testing. No se modificó `individual_units.location_id`.

La auditoría de solo lectura verificó **110 FK, 71 CHECK y 100 índices únicos incluyendo PK**, sin huérfanos, incumplimientos, duplicados ni FK entre esquemas. Las restricciones SQL permanecen habilitadas. `.env` y los 35 archivos de migración conservan sus hashes; código y dependencias sin cambios.

## Cierre y evidencias

`migrate:status` terminó correctamente con las 35 migraciones aplicadas y ninguna pendiente. El mantenimiento se retiró y se comprobó el estado habilitado de Laravel. Cierre: **11:50:07, America/La_Paz**. Esto no equivale a ejecutar una aceptación funcional en navegador.

La [evidencia agregada](evidence/sprint-4-deployment.json) registra las comprobaciones efectivas. El SQL, capturas completas, script operativo y logs permanecen exclusivamente en el directorio privado externo señalado. No se publican datos ni credenciales.

No se ejecutó PHPUnit sobre la principal ni se crearon cuentas, productos o documentos demostrativos. Las **401 pruebas/3.143 aserciones** corresponden a la regresión previa en `sga_rio_grande_testing`; no se repitieron durante el despliegue o esta actualización documental.

El Sprint 4 queda publicado y desplegado con aceptación declarada del usuario y las reservas de evidencia humana conservadas. Cualquier cambio posterior de esquema requiere su propio procedimiento y autorización. No utilizar rollback destructivo ni repetir las migraciones ya aplicadas.

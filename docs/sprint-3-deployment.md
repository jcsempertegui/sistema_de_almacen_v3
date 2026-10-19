# Sprint 3 — Despliegue autorizado y aceptación declarada

Fecha: 19 de octubre de 2026, America/La_Paz. Código aplicado: `0f63d4258baf193d9a491e583ad6aa3e830ea76f`.

El usuario manifestó **«autorizado y aceptado»** y después solicitó **«retoma las acciones»**. Se registra aceptación funcional declarada del Sprint 3 con las reservas de evidencia existentes y autorización para ejecutar el procedimiento previamente presentado. No se infieren firma, identidad legal ni resultados de casos personales sin constancia.

## Prevalidación y respaldo actual

Se confirmó mediante `SELECT DATABASE()` el destino `sga_rio_grande`, PHP 8.3.10, Laravel 13.35.0 y MariaDB 10.4.32. `main` estaba limpia y sincronizada con GitHub. Había 24 tablas, 27 migraciones aplicadas y exactamente las dos migraciones del Sprint 3 pendientes. Se verificaron InnoDB, modo estricto, FKs y CHECK habilitados y ausencia de DDL parcial.

Con mantenimiento temporal se creó un respaldo SQL completo nuevo fuera del repositorio, protegido para el usuario local y SYSTEM:

- Archivo: `C:\Users\jcsem\SGA-backups\sprint3-deployment-20261019-0037062b\sga_rio_grande_pre_sprint3.sql`.
- Tamaño: **89.928 bytes**; **24 tablas y 194 registros**.
- SHA-256: `f2e835418321556f7fb604771cb84fc5e5c2426358d5a5306656d7b8931c9f4e`.
- `mysqldump` con snapshot InnoDB, datos/estructura y opciones de objetos almacenados, salida satisfactoria y cierre completo. Sin `DROP TABLE`, `CREATE DATABASE` ni `USE` dentro del SQL. No se reutilizó un respaldo anterior.
- Se verificaron la lista de tablas y los registros por tabla, y se comparó el estado principal antes y después de generar el SQL.

**Restauración de ensayo ejecutada:** se creó exclusivamente la base vacía `sga_rio_grande_restore_s3_0037062b`, separada de la principal, testing y aceptación. El cliente SQL restauró allí el respaldo y se compararon las 24 estructuras lógicas y todos los hashes de filas con el estado capturado. Coincidieron. Es validación del respaldo; no se ejecutó PHPUnit ni se insertaron datos demostrativos de negocio.

La base restaurada se conserva para revisión; no se elimina automáticamente. El archivo temporal de opciones del cliente se retiró después de usarlo. El SQL, las capturas completas y los registros privados permanecen fuera del repositorio.

## Aplicación y resultado real

Después de comprobar nuevamente el hash del respaldo, la restauración y la conservación de la principal, se ejecutó una sola vez:

```powershell
php artisan migrate --database=mysql --path=database/migrations/2026_10_19_000001_create_inventory_tables.php --path=database/migrations/2026_10_19_000002_add_inventory_permissions.php --force
```

Ambas finalizaron correctamente, **lote 13**, registros de migración **28 y 29**. No repetir este comando para este despliegue. No se ejecutaron seeders generales ni migraciones de etapas anteriores.

| Comprobación | Antes | Después verificado |
|---|---:|---:|
| Tablas | 24 | 31 |
| Migraciones aplicadas | 27 | 29 |
| Pendientes | 2 | 0 |
| Permisos | 48 | 62 |
| Relaciones rol–permiso | 95 | 126 |
| Auditorías administrativas | 18 | 21 |
| Usuarios / trabajadores | 0 / 0 | 0 / 0 |

Se conservaron todas las filas anteriores. Las siete tablas nuevas quedaron vacías: migrar no genera documentos, saldos, movimientos, presencia individual ni aperturas. No se crearon usuarios o productos.

En las estructuras existentes solo se añadió el índice UNIQUE `(id, product_id)` de `individual_units`. Las demás definiciones lógicas permanecen iguales. `.env` y los 29 archivos de migraciones conservan sus hashes. Las nuevas estructuras coinciden con las verificadas en testing, excluidos contadores AUTO_INCREMENT.

Se preservaron los 48 permisos y 95 vínculos previos. Se añadieron únicamente 14 permisos y 31 concesiones aprobadas: Administrador 14, Encargado 13 y Consulta/Auditor cuatro. Capacidades activas finales: **62, 49 y 15**, respectivamente. Las versiones de permisos avanzaron de 6 a 7; identidad, estado y autoría de los roles permanecen iguales. Las tres auditorías nuevas reflejan esas concesiones, sin revocaciones ni restauración de vínculos retirados.

## Integridad y cierre de mantenimiento

Mediante consultas de solo lectura se comprobaron:

- Veinte FKs nuevas RESTRICT, incluida la pertenencia compuesta de unidades, líneas, productos y ubicaciones; dieciocho CHECK nuevos.
- Integridad global de **63 FKs, 44 CHECK y 63 índices únicos**: cero huérfanos, incumplimientos o grupos duplicados.
- Conservación de datos y esquemas anteriores, con las excepciones aditivas expresamente aprobadas.
- Respaldo todavía coincidente con su SHA-256; FKs y CHECK habilitados.
- `migrate:status` con salida 0 y ninguna pendiente; mantenimiento retirado y aplicación nuevamente disponible.

No se ejecutaron pruebas automatizadas sobre la principal ni se alteraron el entorno de aceptación del Sprint 2 o la base de testing. La evidencia agregada es [sprint-3-deployment.json](evidence/sprint-3-deployment.json). Los registros originales están en el directorio externo indicado.

## Aceptación y reservas

El Sprint 3 queda **aceptado por declaración del usuario y desplegado**, con las reservas registradas. Los casos del [guion personal](sprint-3-acceptance.md) sin resultados documentados siguen pendientes de evidencia; no se completan retrospectivamente.

Se mantiene `TEST-S3-01`: la regresión de 363 casos tuvo un timeout en una carrera anterior de productos, y tanto el escenario aislado como el grupo completo de siete pasaron al repetirlos. No se inventa una regresión completa única sin errores. Permanecen también los casos de borde CSV y las revisiones visuales sin evidencia.

La principal continúa sin administrador real; su creación requiere una instrucción específica. No se inicia el Sprint 4 ni se implementan entregas, préstamos, devoluciones, transferencias o ajustes.

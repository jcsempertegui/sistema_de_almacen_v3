# Simulación anual del contrato — Río Grande

## Autorización y naturaleza de los datos

El usuario solicitó cargar usuarios, trabajadores y artículos con un año de actividad verosímil, para un contrato de provisión de personal en una planta separadora de líquidos. Indicó 20 trabajadores operativos y 4 personas de staff, incluyendo responsable de almacén y referente técnico. Se ofreció un destino independiente; el usuario pidió realizarlo como si fuera real. Se informó que se cargaría en la base local actual, preservando la cuenta existente y registrando expresamente su naturaleza simulada.

Los nombres son ficticios, los correos pertenecen a `demo.sga.test` y no se inventan cédulas, series de fabricante ni certificaciones. Los documentos llevan `[SIMULACIÓN ANUAL RG]` en observaciones y el catálogo identifica su procedencia. Estos registros no acreditan operaciones físicas, firmas de trabajadores ni aceptación funcional humana.

## Escenario previsto

- Período: 1 de noviembre de 2025 a 31 de octubre de 2026.
- 20 operativos: 6 mecánicos, 4 electricistas, 4 instrumentistas, 4 técnicos de mantenimiento y 2 soldadores.
- 4 de staff: responsable de almacén, referente técnico, supervisora de seguridad y salud, y coordinador administrativo. Los dos últimos cargos se asumen para completar el escenario.
- Los 24 son trabajadores independientes de las cuentas de acceso. Solo el staff recibe cuatro cuentas nuevas: almacén y referente con rol Encargado; seguridad y administración con rol Consulta/Auditor. No se crea otro Administrador ni se cambian los permisos de los roles.
- 31 productos: 12 combinaciones de prenda/talla, 4 tallas de calzado, 7 artículos de protección/consumo y 8 tipos de herramientas o instrumentos.
- 64 herramientas identificadas individualmente, con préstamos de largo plazo y préstamos para intervenciones programadas.
- Una apertura inicial autorizada por el Administrador; reposiciones mensuales realizadas por almacén.
- Dos dotaciones semestrales para cada una de las 24 personas: overol, dos camisas, dos pantalones, botines, casco y lentes. Las cantidades son supuestos del escenario, no una política certificada de dotación.
- Solicitudes diarias de guantes y protección consumible, según una distribución determinista de 1 a 3 solicitantes por día. No se supone un retiro de todos los trabajadores cada día.
- Devoluciones parciales y totales; algunas obligaciones vigentes con vencimiento y otras sin fecha.
- Tres borradores finales para revisión: recepción, entrega y préstamo. No generan movimiento físico.

Las categorías distinguen ropa, calzado, protección consumible, herramientas manuales, instrumentos y herramientas eléctricas. Las prendas se distinguen mediante atributos técnicos de tipo y talla. Los productos tienen asociaciones explícitas con estantes o gabinete del único almacén.

## Mecanismo y controles

`AnnualPlantScenario` utiliza las acciones existentes de catálogo, usuarios, trabajadores, apertura, remitos, entregas, préstamos y devoluciones. No usa factories para cargar la principal, ni inserta movimientos o saldos directamente. No modifica documentos confirmados.

La carga completa se ejecuta en una transacción, con el mutex administrativo existente. Requiere catálogo y operaciones vacíos y rechaza la repetición: no borra, mezcla ni reemplaza datos previos. Ante un fallo se revierte todo el escenario; no se reinician identificadores. El reloj simulado es exclusivo del proceso y se restaura al salir, sin cambiar el reloj del equipo ni la configuración permanente.

Antes de cargar se verifica el nombre de la base, servidor SQL, puerto y directorio. La principal exige además `--allow-primary` y un respaldo externo cuyo SHA-256 coincida. La aplicación permanece vinculada a la misma base; no se modifica `.env`.

Comando disponible: `php artisan sga:load-annual-scenario --help`. No es un seeder general ni se ejecuta automáticamente al instalar, migrar o iniciar la aplicación. Las contraseñas de las cuatro cuentas se generan aleatoriamente y se muestran al operador; no deben guardarse en Git ni en evidencias públicas.

## Pruebas y verificaciones

`tests/Feature/AnnualPlantScenarioTest.php` prepara el año completo únicamente en `sga_rio_grande_testing`, verifica las 24 personas, las 48 dotaciones, los 365 días de consumos, las custodias, los saldos, la conservación de la contraseña administrativa y de los permisos. También comprueba rechazo de otra identidad, repetición de la carga y rollback al provocar un fallo de generación.

## Resultados ejecutados

El ensayo completo, el rechazo de identidad incorrecta y el rollback provocado finalizaron correctamente: **3 pruebas, 34 aserciones**, PHPUnit `06:36.545`.

```console
php vendor/bin/phpunit --colors=never --stop-on-failure --filter AnnualPlantScenarioTest
```

Se confirmó previamente `SELECT DATABASE() = sga_rio_grande_testing`, servidor `DESKTOP-U7NQKIM`, puerto `3306`, directorio `c:/xampp/mysql/data`. Los controles de aislamiento de las pruebas permanecieron activos. No se ejecutó la regresión completa del sistema para esta carga.

La ejecución principal autorizada finalizó en `sga_rio_grande`, tras verificar la misma instancia, puerto y directorio y un respaldo nuevo externo. Resultado real:

| Elemento | Cantidad |
|---|---:|
| Trabajadores operativos / staff | 20 / 4 |
| Usuarios totales / nuevos | 5 / 4 |
| Categorías / productos | 6 / 31 |
| Herramientas individualizadas | 64 |
| Aperturas y remitos, incluido un borrador | 13 |
| Entregas, incluido un borrador | 778 |
| Dotaciones semestrales confirmadas | 48 |
| Entregas diarias confirmadas, en 365 fechas distintas | 729 |
| Préstamos, incluido un borrador | 33 |
| Devoluciones confirmadas | 24 |
| Movimientos físicos | 1931 |
| Posiciones de existencias | 31 |
| Herramientas actualmente en custodia | 40 |

La verificación posterior independiente, mediante SQL de solo lectura, encontró **cero** diferencias de saldos, movimientos sin posición, saldos acumulados negativos, fuentes inválidas, direcciones incorrectas, duplicados por origen o conflictos entre custodia y presencia física. También se conciliaron las posiciones individualizadas con el saldo por producto/ubicación.

Se conservaron íntegramente los registros del Administrador original, roles, permisos y relaciones rol–permiso, las 43 migraciones y los archivos protegidos. La base mantiene 53 tablas, 123 permisos y 260 relaciones rol–permiso. No se aplicaron migraciones, se modificó `.env` ni se reiniciaron identificadores.

El renderizado del panel con datos cargados confirmó 29 productos activos con existencias, 2 sin saldo positivo, 40 herramientas prestadas y 8 vencidas al 31/10/2026. Hay tres borradores y cinco movimientos recientes visibles. Estos valores cambian al realizar operaciones o avanzar la fecha; las herramientas sin existencias están asignadas a trabajadores, no representan saldos negativos.

Respaldo anterior a la carga: `C:/Users/jcsem/SGA-evidence/annual-scenario-20261101-003325/before-sga_rio_grande.sql`, 200701 bytes, 53 sentencias `CREATE TABLE`, cierre completo de mysqldump y SHA-256 `cbfdab39cb5dfdb2e7dbe3265c0edebce8d7359e0e839dd8e1e5bd1a46c230b4`. El nombre del directorio usa la fecha UTC; la fecha local de trabajo fue 31/10/2026. No se ensayó una restauración adicional para esta carga.

Las capturas previas y posteriores, la verificación del respaldo y las comprobaciones de SQL/panel están en ese directorio externo. No contienen contraseñas nuevas en texto claro. No hay resultado de aceptación humana asociado a esta simulación. No se realizaron commits ni push.

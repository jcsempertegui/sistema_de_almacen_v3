# Manual técnico de la versión de cierre

Revisión: 5 de noviembre de 2026. Este manual describe el PC actual. Los despliegues y manuales fechados de otros equipos se conservan como antecedentes, no como instrucciones actuales.

## Entorno comprobado

| Elemento | Valor |
|---|---|
| Proyecto | `C:\laragon\www\sistema_de_almacen_v3` |
| PHP | 8.3.10; `C:\laragon\bin\php\php-8.3.10-Win32-vs16-x64\php.exe` |
| Laravel | 13.35.0 |
| MariaDB | 10.4.32; clientes `C:\xampp\mysql\bin` |
| Servidor / puerto / datos | `DESKTOP-U7NQKIM`, 3306, `C:\xampp\mysql\data\` |
| Principal | `sga_rio_grande`, 53 tablas y 44 migraciones, sin pendientes en la inspección inicial |
| Testing | `sga_rio_grande_testing`, esquema separado en la misma instancia; no una segunda instancia física |
| Interfaz | Blade, CSS y JavaScript locales; sin compilación Node para el arranque actual |

El código de Git no contiene la base, las cuentas ni sus contraseñas. El escenario anual cargado es ficticio. No se reinstala ni se repite el generador de datos para iniciar el sistema.

## Arranque y diagnóstico

Con MariaDB disponible y desde la raíz del proyecto:

```powershell
php --version
php artisan --version
php scripts/verificar-cierre.php
php artisan sga:check-backup-client
# Iniciar solo si no existe ya un servidor en el puerto 8000:
php artisan serve --host=127.0.0.1 --port=8000
```

La dirección de ingreso es `http://127.0.0.1:8000/ingresar`; `/estado-sistema` es la comprobación de salud. El diagnóstico de cierre usa una transacción SQL de solo lectura: identidad, migraciones, conteos, acceso administrativo, FK/CHECK/UNIQUE y conciliación del saldo con movimientos. No migra, repara ni genera respaldos. Un error termina con código distinto de cero y requiere revisión.

Conservar `.env` y `APP_KEY` privados. No generar una clave nueva, cambiar conexión, ejecutar seeders ni repetir migraciones para resolver problemas de arranque. No ejecutar otro MariaDB contra el mismo directorio. No iniciar un segundo `artisan serve` si el puerto ya está ocupado.

## Arquitectura

Rutas → middleware de sesión/permisos → controlador/FormRequest → acción o servicio transaccional → Eloquent/MariaDB → Blade. Las Policies y Gates protegen las solicitudes directas, además de ocultar botones. Las definiciones de permisos son configurables y no se sustituyen por comparaciones de nombres de roles.

`movimientos_inventario` es el historial único. Las fuentes alternativas son línea de inventario, línea de entrega, ítem de préstamo, ítem de devolución o línea de corrección. CHECK exige una sola fuente y UNIQUE protege cada origen. `existencias_ubicacion` conserva un único saldo por producto y ubicación; la presencia y la custodia individual se administran separadamente.

Las confirmaciones y correcciones bloquean sus referencias y vuelven a verificar permisos y disponibilidad dentro de la transacción. Los borradores no afectan existencias. Los documentos cerrados conservan sus movimientos y relaciones. No se usa FLOAT para cantidades técnicas.

La copia temporal de permisos de lectura vive solo durante GET/HEAD en una instancia de rol. No es una caché persistente y no autoriza mutaciones anidadas desde una lectura anterior. Los formularios, sesiones y autorizaciones siguen verificando versiones actuales.

El esquema aplicado está en [el diccionario](diccionario-datos-espanol.md), el [modelo lógico](modelo-datos-espanol.md) y la [correspondencia de clases](correspondencia-clases-espanol.md). Los nombres históricos de los informes S1–S5 no se reinterpretan como nombres actuales de tablas.

## Pruebas y aislamiento

`phpunit.xml`, `tests/bootstrap.php` y `TestingDatabaseGuard` fijan exclusivamente `sga_rio_grande_testing`. Verifican `SELECT DATABASE()`, servidor, puerto y directorio antes de escribir; retiran conexiones ajenas y fijan la identidad que heredan los procesos secundarios. Las fixtures usan transacciones o sus procedimientos de aislamiento revisados. Una configuración Laravel cacheada provoca rechazo.

```powershell
php vendor/bin/phpunit --colors=never --stop-on-failure --log-junit RUTA_EXTERNA\regresion.xml
```

Usar una ruta externa privada real para la evidencia. No ejecutar suites paralelas contra la misma base. No abrir una demostración persistente sobre testing mientras se ejecuta PHPUnit. No desactivar el guardián, ampliar timeouts sin diagnóstico ni convertir una prueba fallida en aprobada por omisión.

El guardián no reemplaza los privilegios SQL: credenciales capaces de acceder a la principal desde un programa arbitrario siguen siendo un riesgo operativo. La separación de cuentas SQL por esquema requiere una intervención administrativa independiente; no se cambian concesiones automáticamente.

## Respaldos y recuperación controlada

La configuración de clientes se comprueba sin generar SQL mediante `sga:check-backup-client`. Los respaldos reales se generan por el flujo autorizado, fuera del repositorio y del directorio público. Conservar archivo completo, manifiesto, tamaño, SHA-256, fecha, identidad del origen y custodia de la configuración y archivos necesarios para recuperar la aplicación. Un SQL no contiene por sí solo `APP_KEY` ni todos los archivos externos.

Permisos de consulta/generación/verificación/descarga y de solicitud/autorización/ejecución de ensayo son independientes. Para un Administrador y dos Encargados, revisar la matriz propuesta en el guion de aceptación; no restaurar permisos revocados. Solicitante y autorizador deben ser distintos, incluso si varias cuentas comparten un rol.

Un ensayo real necesita autorización específica y una base nueva con nombre exacto `sga_rio_grande_rehearsal_<16 hex>`, solicitud aprobada y consola del operador autorizado. No se presenta aquí una orden lista para ejecutar sobre la principal. El procedimiento detallado histórico está en [recuperación S5](sprint-5-backup-recovery.md); contrastar siempre sus rutas con este PC y con el esquema español.

Ante fallo parcial de DDL, detener escrituras y conservar evidencias; no repetir a ciegas, invertir tablas manualmente ni confiar en rollback de DDL de MariaDB. La recuperación principal exige plan administrativo y autorización propios. Esta versión de cierre no crea migraciones ni aplica DDL.

## Preparar otra instalación

Verificar versiones y usar exactamente los archivos de bloqueo del repositorio. Si faltan dependencias, base o configuración, preparar una instalación autorizada por separado. Un clon no es una restauración. Revisar directorios de respaldo, binarios, permisos de archivos, destino SQL y necesidad real de cuentas antes de migrar. No copiar credenciales del PC ni publicar volcados dentro de Git.

## Incidencias y mantenimiento

Registrar versión de código, operación, documento, hora y mensaje, sin contraseñas ni sesiones. Revisar `storage/logs` de forma privada. Si hay conflicto, recargar y contrastar el documento antes de reenviar. Para rendimiento, utilizar la evidencia de consultas; no introducir cachés de saldos o permisos sin revisar revocación y concurrencia.

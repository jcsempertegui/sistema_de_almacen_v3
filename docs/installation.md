# Manual de instalación y operación local

Revisión: 25 de octubre de 2026, America/La_Paz. Alcance: continuidad de los cinco sprints; no incorpora funcionalidades de inventario. Véase el [informe actual](environment-stabilization-2026-10-25.md).

## Distinguir las instalaciones

| Evidencia | Equipo anterior | Equipo actual |
|---|---|---|
| Servidor MariaDB | DESKTOP-U7NQKIM | DESKTOP-L0DMVPG |
| Directorio de datos | `C:/xampp/mysql/data/` | `D:/XAMPP/mysql/data/` |
| Estado S5 | ocho migraciones en lote 15; 43 acumuladas | instalación nueva: 43 migraciones en lote 1 |
| Esquema final | 53 tablas | 53 tablas |
| Datos | consultar informes históricos | no se restauraron los datos anteriores; administrador local creado el 24 de octubre |
| Regresiones | 429/3.406; presentación 3/75 separada | preparación 429/3.414; estabilización final 458/3.485 |

Los SQL, las claves y las credenciales no forman parte del repositorio. Un clon recupera código y migraciones, no los registros de otro equipo. Conservar los informes históricos sin reinterpretarlos como estado actual.

## Requisitos y comprobaciones

Usar PHP 8.3.36 de `D:\PHP83\php.exe`, Laravel 13.35.0 y MariaDB 10.4.32 existentes. No actualizar componentes para seguir este manual. En otro Windows, sustituir únicamente la ruta del ejecutable por la instalación PHP 8.3 aprobada.

Desde la raíz del proyecto:

```powershell
& "D:\PHP83\php.exe" --version
& "D:\PHP83\php.exe" --ini
& "D:\PHP83\php.exe" C:\composer\composer.phar --version
& "D:\PHP83\php.exe" C:\composer\composer.phar check-platform-reqs
& "D:\PHP83\php.exe" artisan --version
& "D:\PHP83\php.exe" scripts/inspect-local-environment.php
```

El inspector solo lee ambas bases en transacciones de lectura. Muestra identidad, migraciones y huellas; no exporta filas, contraseñas ni SQL restaurable. Usarlo cuando ambas bases ya existen. No guardar su salida en una ubicación pública.

`php` del PATH actual apunta a PHP 8.2 de XAMPP y su INI intenta cargar extensiones de PHP 8.3. No copiar DLL entre versiones. La ruta explícita evita esa mezcla sin cambiar el sistema. Composer también debe ejecutarse con el PHP correcto.

Las dependencias actuales ya existen y se verificaron sin instalarlas. En una instalación futura que carezca de `vendor`, preparar primero un plan autorizado para instalar exactamente `composer.lock`, sin actualizarlo ni ejecutar scripts automáticos. No usar `composer setup`, `composer test` ni scripts que limpien configuración o migren automáticamente.

## Principal y arranque

La configuración privada existente permanece intacta. No cambiar `.env` ni generar `APP_KEY`; ya existe una clave autorizada. No publicar sus valores. Mantener MariaDB activo mediante la instalación local; no iniciar otra instancia apuntando al mismo directorio de datos.

```powershell
& "D:\PHP83\php.exe" artisan migrate:status --database=mysql
& "D:\PHP83\php.exe" artisan serve --host=127.0.0.1 --port=8000
```

`migrate:status` consulta el estado, no aplica migraciones. No ejecutar el segundo servidor si el puerto está ocupado por el ya iniciado. Abrir `/login` en operación normal puede escribir sesiones; no hacerlo durante una comparación que exige cero escrituras en la principal. Ya existe un administrador local y no hay contraseñas predeterminadas. Nuevas cuentas se gestionan con permisos desde el sistema.

No repetir migraciones históricas, ni ejecutar seeders, `migrate:fresh`, rollback, reset o wipe. Una futura instalación vacía requiere autorización y comprobación propias; este manual no autoriza crear o modificar otra base.

## Portabilidad de Backup

La selección usa, por orden, el directorio explícito `SGA_MARIADB_BIN` del proceso o, si no se indicó, XAMPP en la unidad del ejecutable PHP, XAMPP en la unidad del sistema y directorios del PATH. En otros sistemas busca `/usr/bin` y PATH. Se aceptan los nombres `mariadb-dump`/`mysqldump` y `mariadb`/`mysql`, con `.exe` en Windows.

Un directorio explícito inexistente o un cliente incompatible provocan rechazo, sin elegir silenciosamente otra instalación. Los argumentos se pasan como lista, sin construir una orden de shell con credenciales. Se ejecuta `--version` con límite de 10 segundos; la salida debe identificar MariaDB y la serie configurada. La serie predeterminada es 10.4 y admite distintas revisiones de parche; no certifica otras series de servidor.

```powershell
# Opcionales, solo en esta terminal. No escribirlos en .env durante este trabajo.
$env:SGA_MARIADB_BIN = "D:\XAMPP\mysql\bin"
$env:SGA_MARIADB_VERSION = "10.4"
# Si se requiere otro destino, elegir una ruta privada absoluta fuera del repositorio.
# $env:SGA_BACKUP_DIRECTORY = "D:\SGA-private-backups"
& "D:\PHP83\php.exe" artisan sga:check-backup-client
```

El diagnóstico solo consulta versiones de ejecutables. No conecta al servidor, crea solicitudes, activa mantenimiento, genera SQL ni restaura. El destino predeterminado de respaldos permanece fuera del repositorio, en `D:\sga-backups` para esta ubicación del proyecto. La generación real valida el destino canónico y conserva el mecanismo de archivos privados de opciones y retirada de credenciales temporales.

Las restauraciones reales siguen requiriendo consola, permisos, solicitud y aprobación por otra persona, vigencia y confirmación exacta de una base nueva `sga_rio_grande_rehearsal_<16 hex>`. Se rechaza un destino existente; también el cliente de bajo nivel rechaza principal, testing y nombres que no sean de ensayo. El clon Laravel de restauración utiliza su propio nombre interno `sga_rehearsal`.

No ejecutar generación ni restauración reales para instalar, diagnosticar o probar este cambio. El auxiliar histórico `verify-sprint5-backup.php` requiere ahora además `--authorized-real-backup`; esa opción no sustituye la autorización humana específica. Los ensayos operativos S3/S4/S5 no forman parte de la regresión normal.

## Pruebas aisladas

Solo usar `sga_rio_grande_testing`, existente y separada por nombre de la principal. En este PC comparten servidor y directorio de datos; no son instancias físicas distintas. Antes de escribir, el bootstrap comprueba el destino local de la configuración privada mediante una conexión PDO usada solo para leer su identidad; compara el destino de testing contra ese servidor, puerto y directorio.

Se fija en memoria `SGA_TEST_IDENTITY`, sin contraseñas. Laravel elimina las conexiones configuradas y las conexiones resueltas previamente, y conserva únicamente `sga_testing`, con `name=sga_testing`. El `DB_CONNECTION=mysql` del XML selecciona la configuración inicial, no un alias disponible para factories o Artisan. Una factory que intente reutilizar `mysql` falla antes de abrir el PDO de ese alias.

El guardián comprueba nombre lógico e interno, driver, host, puerto, ausencia de URL/socket/división lectura-escritura y la identidad SQL real (`SELECT DATABASE()`, `@@hostname`, `@@port`, `@@datadir`). También verifica la identidad antes de ejecutar consultas Laravel y bloquea cambios de esquema por `USE`, DDL de bases y referencias cualificadas a la principal. Los PDO auxiliares se obtienen mediante el mismo guardián, verificado antes de entregarlos a los casos concurrentes.

Los siete procesos secundarios heredan explícitamente host, puerto, credenciales de testing e identidad fijada. Si cambia un valor, abortan antes de cargar los trabajos o crear fixtures. No recalculan una identidad distinta para aprobarla. Cada proceso carga el mismo bootstrap, usa clave efímera y solo dispone del alias aislado. Una configuración cacheada provoca rechazo.

```powershell
& "D:\PHP83\php.exe" vendor/bin/phpunit --colors=never --stop-on-failure
```

No ejecutar en paralelo contra la misma base. TestCase valida la conexión antes de migraciones normales de testing y antes de transacciones; las 43 migraciones actuales ya están aplicadas. No se amplían tiempos máximos, se mantienen las barreras y la observación de los bloqueos reales. Los negativos peligrosos se clasifican como cadenas o usan conexiones sin PDO real; no se ensaya una escritura destructiva contra la principal.

Esta protección cubre Laravel y los auxiliares revisados; no es una frontera de seguridad frente a código arbitrario que cree sus propios PDO o clientes SQL con credenciales amplias. No publicar ni reutilizar esas credenciales. El usuario SQL local actual no fue sustituido ni se cambiaron sus concesiones; separar permisos SQL por esquema queda como decisión operativa posterior autorizada.

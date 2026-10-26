# Continuidad en otro PC — 23 de octubre de 2026

Fecha: 23 de octubre de 2026, America/La_Paz. Punto de partida: `main`, commit `55c3a95`, árbol limpio. El usuario indicó que continuemos desde el repositorio en este equipo.

## Instalación local nueva

El repositorio conserva el código, las 43 migraciones y la configuración inicial de roles y permisos. No contiene un respaldo SQL de los datos operativos ni la configuración privada del PC anterior. Esta instalación no restaura sus registros ni sustituye las evidencias históricas del [despliegue S5](sprint-5-deployment.md).

- PHP válido: `D:\PHP83\php.exe`, versión 8.3.36. El comando `php` del PATH usa PHP 8.2.12 de XAMPP, incompatible con este proyecto; el PATH no se modificó.
- Laravel 13.35.0; dependencias existentes y 116 rutas registradas. No se instalaron ni actualizaron paquetes.
- MariaDB 10.4.32 disponible localmente. Antes de comenzar no existían `sga_rio_grande` ni `sga_rio_grande_testing`.
- Se crearon únicamente esas dos bases, con `utf8mb4_unicode_ci`, tras comprobar el destino local y la ausencia de ambos esquemas.
- Se ejecutó `D:\PHP83\php.exe artisan migrate --database=mysql --force --no-interaction` sobre la principal nueva. Las 43 migraciones se aplicaron en lote 1: 53 tablas, tres roles, 123 permisos y 260 relaciones rol–permiso. No quedan migraciones pendientes.
- La principal permanece sin usuarios, trabajadores ni productos. No se ejecutaron seeders, no se crearon cuentas reales y no se importaron datos de demostración.
- No se ejecutaron `migrate:fresh`, rollback ni eliminaciones de bases o tablas.

## Validación

La ejecución completa posterior a la corrección se realizó mediante `D:\PHP83\php.exe vendor/bin/phpunit --colors=never --stop-on-failure`, exclusivamente sobre `sga_rio_grande_testing`: **429 pruebas y 3.414 aserciones aprobadas**, sin fallos, en 1 minuto y 38,398 segundos. El bootstrap valida el aislamiento y las pruebas preparan el esquema mediante migraciones normales y transacciones. Este resultado pertenece al equipo actual y no se suma a las tandas históricas ni al caso aislado.

Durante la tanda inicial se capturó una huella de las filas y el DDL completo de las 53 tablas de la principal para compararla al finalizar. No constituye una captura anterior al inicio de PHPUnit. La comparación final coincide exactamente: SHA-256 `9a4e5dc4239184bedd66ea968fccdb42b28b02258252f1b7fc492767c9eaeaaf`. La principal conserva cero usuarios, trabajadores y productos. `.env` conserva el hash comprobado antes de ejecutar las pruebas.

Testing quedó con 53 tablas y 43 migraciones. Solo contienen filas las tablas iniciales: tres roles, 123 permisos, 260 relaciones rol–permiso, 27 auditorías de migraciones y 43 registros de migración. Las demás tablas están vacías, sin fixtures residuales; no quedaron procesos PHP de las pruebas. `git diff --check` aprobó.

### Corrección de portabilidad de las pruebas concurrentes

La primera tanda se interrumpió tras fallos repetidos en las carreras de unidades individualizadas. Se retiraron transaccionalmente solo los fixtures confirmados del caso interrumpido, identificados por el nonce `f1ac2de6d8402c`, sus claves y sus relaciones, exclusivamente en testing. No quedaron procesos PHP de esa ejecución ni conexiones de testing abiertas.

El caso aislado `IndividualUnitConcurrencyTest.*identifier collision` reprodujo el error SQL 1205. Mientras el monitor InnoDB mostraba una espera real sobre el permiso de exclusión mutua, las tablas `INNODB_LOCK_WAITS`/`INNODB_TRX` seguían mostrando una captura anterior. Las pruebas consultaban esa información cada 10 ms. El [código de MariaDB 10.4](https://github.com/MariaDB/server/blob/10.4/storage/innobase/trx/trx0i_s.cc) exige más de 100 ms desde la última lectura para refrescar la caché compartida; cada lectura vuelve a registrar su hora.

Se agregó una pausa de 150 ms antes de consultar esa caché en las cinco pruebas que la usan: unidades individualizadas, productos, inventario, operaciones con trabajadores y correcciones. Se conservan la consulta del bloqueo real, las barreras entre procesos, los resultados esperados y las aserciones sobre la espera medida. No se modificaron acciones de negocio, migraciones ni configuración del servidor.

El mismo caso aislado pasó después del cambio: una prueba y 11 aserciones, 0,922 segundos. Pint aprobó los cinco archivos modificados. La regresión completa posterior de 429 pruebas también pasó, según el resultado anterior.

## Pendientes del entorno

1. Clave local resuelta el 24 de octubre con autorización expresa; véase la actualización siguiente. La ausencia de `APP_KEY` y el servidor detenido descritos anteriormente corresponden a la preparación del 23 de octubre.
2. El administrador local se creó posteriormente, el 24 de octubre, a petición del usuario, usando las validaciones y la operación transaccional del comando de creación. Se verificaron la contraseña cifrada y el acceso administrativo activo. Las credenciales se entregaron por el chat y no se guardan en el repositorio. La asignación de cuentas a responsables reales se gestiona desde el sistema.
3. `config/sga.php` conserva la ruta de binarios del PC anterior, `C:/xampp/mysql/bin`; en este equipo se encontró `D:\XAMPP\mysql\bin\mysqldump.exe`. No se modificó esta configuración ni se ensayaron respaldos o restauraciones reales. Las pruebas del módulo usan un cliente SQL simulado y no acreditan esa operación.
4. Se mantienen pendientes las evidencias de revisión visual y el contraste académico de los sprints entregados. No se inició un sprint nuevo ni se añadieron funcionalidades.

## Actualización del 24 de octubre: clave y acceso local

El usuario autorizó generar únicamente `APP_KEY` con «autorizo+», como excepción puntual a la regla de `AGENTS.md` que prohíbe modificar `.env`. Al comprobar el archivo actual, la línea `APP_KEY` estaba ausente; el comando normal de Laravel no pudo reemplazarla. Se añadió esa variable con una clave aleatoria de 32 bytes generada mediante `Illuminate\Encryption\Encrypter`, codificada en base64.

Se verificó en memoria que los bytes guardados coincidieran con el cambio previsto, que las demás variables conservaran sus valores y que el cifrado y descifrado funcionaran. La clave no se mostró ni se incorporó a la documentación o al repositorio. No se regenerará una clave existente sin autorización.

MariaDB estaba apagado. Se inició la instancia existente de `D:\XAMPP\mysql\bin\mysqld.exe` con su configuración local y autorización de ejecución fuera del proyecto; no se cambiaron sus archivos de configuración ni se crearon otras bases. Se inició el servidor de Laravel con PHP 8.3 en `http://127.0.0.1:8000`.

La comprobación HTTP de `/login` devolvió **200**, con formulario de contraseña y campo CSRF presentes. Se consultó el estado de las migraciones sin aplicar nuevas. La petición web puede registrar una sesión en la principal; no constituye un recorrido funcional ni aceptación visual. No se crearon usuarios ni se repitió la suite completa para este cambio de configuración: se conserva el resultado de 429 pruebas/3.414 aserciones de la preparación anterior.

## Actualización del 25 de octubre: comando de arranque

Ante el fallo de `php artisan serve`, se comprobó que `php` sigue resolviendo a `D:\XAMPP\php\php.exe`, versión 8.2.12. Además, su `php.ini` apunta `extension_dir` a `D:\PHP83\ext`, lo que produce avisos al intentar cargar extensiones de otra versión. No se modificaron PHP, su configuración ni el PATH del sistema.

La ejecución explícita con PHP 8.3.36 sí funciona:

```powershell
& "D:\PHP83\php.exe" artisan serve --host=127.0.0.1 --port=8000
```

Se inició el servidor local con ese ejecutable y se verificó nuevamente `/login`: HTTP 200 y formulario de contraseña presente. MariaDB ya estaba en ejecución. No se modificó `.env` ni se aplicaron migraciones.

Para utilizar el comando corto en una terminal PowerShell nueva, se puede priorizar PHP 8.3 solo durante esa sesión:

```powershell
$env:Path = "D:\PHP83;" + $env:Path
php artisan serve
```

Si el servidor ya está iniciado en el puerto 8000, abrir su URL o detener primero esa ejecución con Ctrl+C antes de iniciar otra en el mismo puerto.

## Estabilización posterior del 25 de octubre

La revisión posterior corrigió la selección de binarios Backup, el nombre interno de la conexión de ensayo y el aislamiento de pruebas con el alias exclusivo `sga_testing`. El diagnóstico encontró los clientes MariaDB 10.4.32 de `D:\XAMPP\mysql\bin` sin generar ni restaurar respaldos reales. La regresión completa final aprobó **458 pruebas/3.485 aserciones**. La comparación de las 53 tablas principales no mostró cambios; `.env`, dependencias y las 43 migraciones se conservaron. Véanse el [informe de estabilización](environment-stabilization-2026-10-25.md), el [manual actualizado](installation.md) y el [guion de aceptación pendiente](final-functional-acceptance.md). Los resultados anteriores de este registro conservan sus fechas y no se sustituyen.

# Sprint 5 — Respaldos y recuperación controlada

Aplicación local en desarrollo; no hay autorización para usar estas operaciones contra la principal durante la implementación. No se crean cuentas reales ni se ejecuta recuperación principal automática.

## Generación

El usuario con backups.generate solicita una copia en Respaldos. Un operador autorizado ejecuta desde consola:

```text
php artisan sga:generate-backup ID_SOLICITUD ID_OPERADOR
```

Se revalidan cuenta, rol y capacidad vigentes; las solicitudes se reclaman una sola vez. La consola activa mantenimiento temporal solo fuera de testing y solo si no había mantenimiento ajeno. El mutex compartido bloquea escrituras de la aplicación durante captura/volcado/comparación. Detener además procesos SQL/DDL externos: el mutex no controla administradores fuera de Laravel.

Directorio configurable en config/sga.php, por defecto fuera del repositorio: C:\laragon\sga-backups en este entorno. Nunca public, enlaces públicos o rutas suministradas por el navegador. Directorios privados, ACL del operador y SYSTEM en Windows y permisos 0700/0600 en otros sistemas. Las credenciales del cliente se leen de la conexión y se escriben temporalmente en un archivo protegido, retirado en finally; no aparecen como contraseña en argumentos.

SqlBackupClient exige el cliente MariaDB 10.4.32. Snapshot InnoDB, quick, opciones de rutinas/eventos/triggers y salida directa a archivo, sin sobrescribir. El proyecto actual no tiene objetos almacenados: si aparecen, se detiene para revisar expresamente el procedimiento. No se supone cobertura de objetos nuevos sin probarla.

Se comparan estructuras lógicas, cantidades y hashes de filas; se valida footer, lista de tablas y registros, ausencia de instrucciones destructivas o de selección de otra base y ausencia de referencias calificadas a la principal/testing. Se comprueba integridad SQL. SHA-256 y tamaño quedan registrados. El manifiesto conserva el estado capturado, anterior al registro final de generación; no se pretende que el respaldo incluya su propia finalización.

Generado y verificado indican etapas diferentes: verificar el archivo no significa haberlo restaurado. Un proceso interrumpido conserva estado running o failed y archivos para inspección; no reintenta ni sobrescribe automáticamente. El evento download_authorized registra la autorización de descarga, no garantiza que el navegador recibiera todos los bytes.

## Restauración aislada

Un usuario autorizado solicita ensayo con motivo. Otra persona autoriza por 24 horas. La aprobación conserva versiones de acceso: revocar/desactivar o cambiar el acceso del autorizador la invalida; regrant no revive la aprobación anterior. Antes de iniciarse puede revocarse; una ejecución iniciada se conserva y no se borra para reintentar.

El operador consulta el destino exacto generado por el sistema y ejecuta:

```text
php artisan sga:restore-rehearsal ID_ENSAYO ID_OPERADOR --confirm-target=NOMBRE_AISLADO_EXACTO
```

Se exige consola, usuario vigente, autorización sin usar y destino nuevo con patrón cerrado sga_rio_grande_rehearsal_ + 16 hex. Se rechazan destinos existentes, principal, testing y aceptación. No hay endpoint HTTP para ejecutar la restauración ni selección arbitraria de archivos SQL. PHPUnit rechaza ejecutar ensayos reales fuera de testing.

Se vuelve a comprobar hash/contenido. El DDL se ejecuta fuera de la transacción de autorización porque MariaDB realiza commits implícitos. Se restaura exclusivamente en el esquema nuevo, se comparan todos los hashes/estructuras y se comprueba integridad. Se conserva la base y se audita el resultado. Un fallo conserva el destino parcial y nunca lo borra/recrea automáticamente.

La cuenta SQL de mantenimiento necesita privilegios separados suficientes para crear ese esquema; no se crean usuarios SQL automáticamente. Las credenciales pueden suministrarse solo al proceso de consola, sin editar .env ni exponerlas al navegador. El SQL utiliza los privilegios de la conexión operativa, no los del usuario de Laravel.

## Recuperación de la principal

No existe restauración destructiva automática. Procedimiento: documentar incidente, detener escrituras, preservar estado fallido, seleccionar respaldo íntegro, restaurar y verificar en un esquema nuevo, preparar el cambio controlado de conexión o reparación específica y obtener autorización expresa. No ejecutar DROP, fresh/reset/wipe, rollback de documentos ni importación sobre tablas parciales.

Custodiar por separado APP_KEY, configuración, código desplegado y archivos privados. Un SHA-256 comprueba alteración del archivo respecto del manifiesto; no protege contra un administrador que pueda modificar simultáneamente ambos. Mantener acceso restringido y copia externa protegida. Retención, eliminación, cifrado externo, frecuencia y objetivos de recuperación requieren política operativa; no se borran respaldos automáticamente ni se inventan RPO/RTO aceptados.

La generación real se probó sobre testing. Los ensayos de restauración del módulo y la recuperación principal no se ejecutaron durante desarrollo; requieren autorización y un entorno operativo aislado. La restauración anterior del despliegue S4 no se presenta como prueba de este código nuevo.

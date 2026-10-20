# Sprint 5 — Ensayos finales e incidencia de aislamiento

**Actualización posterior:** la retirada de los 12 registros fue autorizada y ejecutada; se verificó el estado original y se completó el despliegue principal en lote 15. Véase [despliegue verificado](sprint-5-deployment.md). El texto y la evidencia de este informe conservan el diagnóstico previo a esa resolución.

Fecha: 20 de octubre de 2026. Base publicada: `0382ea2dec7088747b1c5898a2f04dcf5b7e368e`. El usuario autorizó los ensayos y las ocho migraciones principales; no autorizó commit ni push.

## Corrección documental

Se recuperaron exclusivamente los finales de línea de `real-backup-final.json`, `real-backup-initial.json` y `upgrade.json`. Los tres vuelven a coincidir con los SHA-256 históricos. No se cambiaron contenidos JSON, resultados ni el manifiesto. `.gitattributes` mantiene `-text` para estos artefactos. No se modificó código de la aplicación, dependencias, `.env` o las 35 migraciones anteriores.

## Instancia aislada y copia histórica

Se inicializó MariaDB 10.4.32 con los binarios existentes, puerto 3315 y directorio privado independiente. No se registró servicio ni se cambió Laragon. Identidad verificada: servidor `DESKTOP-U7NQKIM`, base de ensayo `sga_rio_grande_testing`, almacenamiento bajo `C:/Users/jcsem/SGA-isolated/sprint-5/run-20261020-144814-b08338ec/data`.

Se generó un respaldo nuevo de la principal, externo al repositorio, y se restauró en esa instancia: coincidieron las 42 tablas, datos y estructuras, con 35 migraciones. El SQL y credenciales permanecen privados.

## Incidencia de aislamiento — despliegue bloqueado

El ejecutor operativo externo preparado por Codex copió la configuración de conexión conservando por error su nombre interno `mysql`. La conexión SQL comprobada apuntaba al puerto aislado, pero las factories reutilizaron aquel nombre al guardar y escribieron registros ficticios en la principal. La primera operación documental falló al buscar allí el usuario en la conexión aislada; no se registraron documentos ni movimientos.

La auditoría identificó exactamente 12 filas nuevas: usuarios 1–3, trabajadores 1–2, ubicaciones 1–2, categoría 1, unidad de medida 1, producto 1 y asociaciones producto–ubicación 1–2. Las siete tablas estaban vacías antes. No se modificaron filas anteriores, permisos ni migraciones. La integridad SQL permanece válida. Se preservó además un respaldo del incidente.

Las migraciones principales se detuvieron. Se activó mantenimiento preventivo para impedir acceso mediante las cuentas ficticias. Se solicitó autorización específica para retirar exclusivamente esas filas, dentro de una transacción, comprobando sus valores y los hashes originales; esa retirada no se ha ejecutado al redactar este informe. No se reiniciarán identificadores ni se restaurará destructivamente la principal.

El ejecutor externo se corrigió asignando el nombre interno aislado y eliminando la configuración/conexión principal del proceso de ensayo. Los siguientes ensayos verificaron puerto, datadir, base y alias. Las incidencias del ejecutor no se presentan como fallos del código publicado de inventario.

## Ensayo íntegro de ocho migraciones — ejecutado

Se prepararon tres cuentas ficticias, dos trabajadores, cantidades decimales, apertura, ingreso, egreso, entregas por cantidad e individuales, préstamo parcial y cerrado, trabajador inactivo y devolución excepcional. Las ocho migraciones se ejecutaron en una sola tanda sobre la copia S4.

Resultado: **53 tablas, 43 migraciones, ninguna pendiente**. Se conservaron **14 movimientos** y los datos históricos de 37 tablas comparadas; las cinco tablas de permisos, roles, auditoría y migraciones se excluyeron de la comparación literal por sus cambios previstos. Se derivaron **cuatro ciclos, tres cierres y una custodia pendiente**. Se conservó una revocación y un rol desconocido sin concesiones. Integridad: **166 FK, 103 CHECK, 143 índices únicos; cero incumplimientos**. Los fixtures se conservan para inspección.

Una primera preparación aislada rechazó correctamente la confirmación de apertura por el Encargado; se continuó confirmándola con el Administrador ficticio. No se modificó el permiso del producto.

## Generación y restauración real del módulo Backup — ejecutadas

Se configuró únicamente el rol Encargado aislado con consulta/generación y solicitud/ejecución de ensayos. Autorización, verificación, inspección y descarga permanecieron administrativas. Encargado 1 solicitó, Administrador autorizó y Encargado 2 ejecutó.

La preparación descubrió dos requisitos operativos: el proceso aislado debe tener `storage` propio para no compartir mantenimiento con la aplicación principal; el usuario SQL requiere EVENT, SHOW VIEW, TRIGGER y lectura de `mysql.proc` para las opciones de mysqldump. Ambos se resolvieron exclusivamente en el entorno aislado. Los dos intentos de generación fallidos quedan registrados; no se sobrescribieron archivos.

Respaldo final real: **280.599 bytes, 53 tablas**, SHA-256 `848381910a60b45f99814587602c36a19dfd089ff27aedcc59f8b5f6a6baaac5`. Restauración verificada en `sga_rio_grande_rehearsal_72c54660797df012`, puerto 3315: coincidencia completa de tablas y datos e integridad válida. Credenciales temporales retiradas.

Ocho casos negativos rechazados: descarga por Encargado, autoautorización, vencimiento, revocación, autorización por Encargado, confirmación incorrecta, repetición y destino existente. El vencimiento se comprobó adelantando el reloj del proceso, no el del sistema. El destino vacío utilizado para rechazo también se conserva.

## Estado histórico anterior a la autorización de retirada

No se repitió la regresión automatizada completa: se conserva su evidencia anterior de 429 pruebas/3.406 aserciones y la comprobación de presentación 3/75. Los ensayos operativos anteriores son evidencia nueva, separada de PHPUnit. No se ejecutó aceptación visual ni contraste académico adicional.

La principal continúa con **42 tablas y 35 migraciones**; hay ocho pendientes y los 12 registros ficticios del incidente aún requieren retirada autorizada. No se han configurado permisos de Backup ni creado cuentas operativas intencionalmente en la principal. El mantenimiento preventivo se conserva mientras se resuelve el incidente.

Antes de aplicar las migraciones ya autorizadas: resolver el incidente con autorización específica, verificar nuevamente los hashes de las 42 tablas contra la captura previa y la integridad del respaldo restaurado, comprobar `.env` y las 35 migraciones anteriores, y confirmar las ocho rutas exactas. Si hubiera cualquier cambio externo, detenerse y renovar la prevalidación/respaldo. Ante DDL parcial conservar mantenimiento, esquema y registros; no ejecutar rollback destructivo ni repetir automáticamente.

La [evidencia agregada](evidence/sprint-5-final-validation.json) registra estos resultados y el bloqueo pendiente. La instancia aislada se apagó mediante SHUTDOWN tras verificar puerto y directorio de datos; los esquemas y archivos se conservan.

Evidencias y ejecutores privados: `C:/Users/jcsem/SGA-isolated/sprint-5/run-20261020-144814-b08338ec/`. El informe no declara desplegado el Sprint 5.

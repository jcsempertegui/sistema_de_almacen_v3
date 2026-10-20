# Sprint 5 — Despliegue verificado e incidente resuelto

Fecha: 20 de octubre de 2026, America/La_Paz. Código base publicado: `0382ea2dec7088747b1c5898a2f04dcf5b7e368e`. El usuario autorizó los ensayos y las migraciones principales con «autorizado y autorizo las migraciones», y autorizó después retirar exclusivamente los 12 registros ficticios del incidente. Tras finalizar el despliegue autorizó también publicar esta entrega con «hazlo esta autorizado».

## Resolución del incidente

El ejecutor externo de ensayo preparado por Codex conservó por error el alias interno `mysql` y las factories insertaron 12 registros ficticios en la principal. El [informe previo](sprint-5-final-validation.md) y su [captura de evidencia](evidence/sprint-5-final-validation.json) conservan el diagnóstico anterior a la resolución; no representan el estado final.

Con autorización específica se comprobaron los valores exactos contra la captura del incidente y se retiraron, en una transacción con FK/CHECK activas, usuarios 1–3, trabajadores 1–2, ubicaciones 1–2, categoría 1, unidad de medida 1, producto 1 y asociaciones producto–ubicación 1–2. No existían documentos ni movimientos del incidente. Los datos y estructuras lógicas de las 42 tablas recuperaron sus hashes anteriores. No se reiniciaron AUTO_INCREMENT; se conservan los huecos de identificadores. Los respaldos anterior y del incidente se conservan privados.

El ejecutor aislado se corrigió fijando su nombre interno y eliminando del proceso la conexión principal. La aplicación publicada no se modificó. La incidencia no se oculta ni se presenta como un despliegue sin incidentes.

## Ensayos previos completados

- MariaDB 10.4.32 independiente, puerto 3315, almacenamiento y configuración privados; sin servicio nuevo ni cambios permanentes de Laragon.
- Copia histórica S4: 42 tablas y 35 migraciones, datos y estructuras coincidentes con el respaldo nuevo.
- Operaciones representativas únicamente en la copia: cantidades decimales, apertura, ingreso, egreso, entregas, préstamos, devolución parcial, trabajador inactivo y recepción excepcional.
- Las ocho migraciones se aplicaron juntas sobre esa copia. Se preservaron 14 movimientos y las columnas históricas comparadas; se derivaron cuatro ciclos, tres cierres y una custodia pendiente. Se conservaron la revocación y el rol desconocido preparados para el ensayo.
- Backup real del módulo: 280.599 bytes, 53 tablas, SHA-256 `848381910a60b45f99814587602c36a19dfd089ff27aedcc59f8b5f6a6baaac5`. Encargado 1 solicitó, Administrador autorizó y Encargado 2 ejecutó. Restauración completa verificada en `sga_rio_grande_rehearsal_72c54660797df012` de la instancia aislada.
- Ocho casos negativos rechazados: descarga no autorizada, autoautorización, vencimiento con reloj simulado del proceso, revocación, autorización por Encargado, confirmación incorrecta, repetición y destino existente. Los intentos fallidos y destinos aislados se conservan.

La preparación detectó que el proceso de Backup necesita storage Laravel separado y privilegios SQL para EVENT, SHOW VIEW, TRIGGER y lectura de mysql.proc, además de los de datos/esquema. Se resolvieron exclusivamente en la instancia aislada. No se ampliaron permisos SQL de la principal.

## Respaldo definitivo del estado limpio

Tras retirar los fixtures autorizados se generó otro respaldo nuevo, sin sobrescribir los anteriores:

- Archivo privado: `C:/Users/jcsem/SGA-isolated/sprint-5/run-20261020-144814-b08338ec/backups/principal-clean-predeploy.sql`.
- Tamaño: **165.288 bytes**.
- SHA-256: `793eb9bdade623110bdc8254e8a3b2481b96a14b2de2ba89b7f6847967242f5c`.
- Restauración nueva en `sga_rio_grande_rehearsal_4d142bb8691dae22`, puerto 3315: coincidencia de las 42 tablas, filas, DDL completo y contadores AUTO_INCREMENT; integridad válida.

Se comprobó nuevamente ese hash antes de migrar. SQL, credenciales, capturas de filas y ejecutores permanecen fuera del repositorio con acceso privado.

## Migraciones principales y resultado

Se confirmaron `SELECT DATABASE() = sga_rio_grande`, servidor `DESKTOP-U7NQKIM`, puerto 3306 y directorio `C:/xampp/mysql/data/`. Se ejecutó migrate con `--database=mysql --force` y ocho opciones `--path` explícitas, exclusivamente para los archivos `2026_10_20_000001` a `2026_10_20_000008` enumerados en el [diccionario](data-dictionary-sprint-5.md). No se ejecutaron seeders ni migraciones anteriores.

| Comprobación | Antes limpio | Después verificado |
|---|---:|---:|
| Tablas | 42 | 53 |
| Migraciones aplicadas | 35 | 43 |
| Migraciones pendientes | 8 | 0 |
| Permisos | 81 | 123 |
| Relaciones rol–permiso | 166 | 260 |
| Usuarios / trabajadores | 0 / 0 | 0 / 0 |

Las ocho migraciones finalizaron en el **lote 15**. Capacidades activas: Administrador 123, Encargado 96 y Consulta/Auditor 41. Se preservaron las filas previas de permisos, vínculos, migraciones y auditorías. Solo cambiaron las versiones de permisos y fechas previstas en los tres roles, con auditoría de las nuevas concesiones. Las once tablas nuevas quedaron vacías.

El esquema de las 53 tablas coincide con el ensayado. Integridad: **166 FK, 103 CHECK y 143 índices únicos**, cero huérfanos, incumplimientos o duplicados. No se reescribieron movimientos ni se crearon documentos operativos. `.env`, dependencias, los 390 archivos del manifiesto de código y las 35 migraciones anteriores permanecieron intactos.

El mantenimiento preventivo se retiró después de las verificaciones. `migrate:status` finalizó correctamente con 43 migraciones aplicadas. La instancia aislada se apagó de forma controlada y conserva sus esquemas y evidencias.

## Alcance de la validación y pendientes

No se ejecutaron pruebas automatizadas ni casos de navegador en la principal. No se repitió la regresión completa: se conserva la evidencia anterior de 429 pruebas/3.406 aserciones y presentación 3/75; los ensayos operativos nuevos se documentan por separado.

La matriz de permisos de Backup para un Administrador y dos Encargados se probó solo en la copia aislada. La principal conserva las concesiones iniciales de la migración, con Backup reservado al Administrador. La creación del primer administrador real y la configuración operativa requieren autorización y datos del responsable; no se crearon cuentas reales. Continúan pendientes las capturas por caso de navegador y el contraste académico externo.

Evidencia del despliegue: [sprint-5-deployment.json](evidence/sprint-5-deployment.json). Su captura precede al commit y push: conserva sus indicadores históricos en falso. La publicación posterior está autorizada y se registra en el [seguimiento](sprint-5-publication.md). La documentación histórica de implementación/publicación conserva sus estados anteriores y queda complementada por este informe. No repetir las ocho migraciones ni utilizar rollback destructivo.

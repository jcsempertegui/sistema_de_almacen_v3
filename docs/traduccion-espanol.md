# Traducción del SGA al español

## Actualización posterior — 2 de noviembre de 2026

La traducción ya fue autorizada y aplicada a la principal, que tiene 53 tablas y 44 migraciones. El reemplazo autorizado por el nuevo escenario de 116 productos se documenta en [el informe de renovación](renovacion-espanol-2026-11-02.md). Los apartados siguientes conservan la descripción y los resultados de la fase previa; sus referencias a una aplicación principal pendiente corresponden a ese momento. No repetir la migración ya aplicada.

## Alcance y situación antes del despliegue

El trabajo se preparó por separado en `refactor/sga-en-espanol`, en `C:/laragon/www/sga_espanol_revision`. Durante esa fase, la aplicación operativa de `sistema_de_almacen_v3` y su base principal conservaron el esquema anterior, sin publicación ni despliegue de la traducción.

Se conservan las mejoras locales de navegación y paginación. La traducción comprende nombres físicos del esquema, modelos, acciones, servicios, controladores, solicitudes, políticas, relaciones, rutas, formularios y sus pruebas. Las equivalencias están en [el diccionario de datos](diccionario-datos-espanol.md) y [la correspondencia de clases](correspondencia-clases-espanol.md).

El catálogo versionado `database/esquema_espanol.json` relaciona las 53 tablas y sus columnas: cambia 50 nombres de tabla y 414 nombres de columna. La correspondencia de clases enumera 183 clases propias existentes. Se utilizan identificadores sin tildes, por ejemplo `ubicaciones_producto`, `numero_serie` y `contrasena`.

## Contratos conservados

- Los contratos de Laravel y PHP conservan sus nombres: `Model`, `Controller`, `FormRequest`, `Factory`, `rules`, `authorize`, `casts`, `attributes`, `getAttributes`, las operaciones CRUD y las claves de configuración del framework.
- Las tablas técnicas pueden tener nombre español mediante configuración, pero sus columnas exigidas por los controladores de sesiones, caché, colas y recuperación de contraseñas mantienen el contrato de Laravel. `migrations` conserva su contrato completo.
- Los 43 archivos de migraciones anteriores no se editan. Sus nombres y el historial de aplicación permanecen disponibles para reconstruir la evolución de los cinco sprints.
- Los códigos de permisos y los valores históricos de estados, tipos y orígenes se conservan, por ejemplo `products.view`, `quantity`, `confirmed` e `inventory-documents`. Las etiquetas visibles son españolas. No se conceden ni restablecen permisos como consecuencia del cambio de nombres.
- Las referencias de índices y restricciones históricas se conservan. Sus columnas y tablas referenciadas se adaptan al nuevo esquema.
- Los nombres de pruebas, identificadores académicos y evidencias históricas no se sustituyen retroactivamente. Esta refactorización no constituye una aceptación humana de los cinco sprints.

## Migración nueva y controles

La única migración añadida es `2026_11_01_000001_traducir_esquema_del_sga.php`, implementada por `App\Support\EsquemaEspanol`.

1. Exige la conexión de testing verificada por `TestingDatabaseGuard` y comprueba la identidad del servidor y `SELECT DATABASE()`.
2. Verifica las tablas, el orden de columnas y el DDL del esquema de origen contra `database/estructura_anterior.json`. Una discrepancia detiene el proceso antes del primer DDL.
3. Calcula huellas de las filas con nombres canónicos anteriores en las 52 tablas de datos; `migrations` se trata aparte porque debe registrar una nueva aplicación.
4. Registra evidencia privada previa. Retira y reconstruye restricciones, cambia nombres de columnas conservando sus definiciones y renombra las tablas. No elimina tablas ni filas ni deshabilita globalmente las verificaciones de integridad.
5. Reconstruye las FK y los CHECK con los nuevos identificadores, conservando los literales históricos, y compara las huellas posteriores con las previas.

El ensayo realizado generó `storage/app/private/traduccion/ensayo.json`. Las huellas previas y posteriores coincidieron en las 52 tablas comparadas. Esto acredita los datos presentes en ese ensayo de testing; no sustituye un ensayo futuro con una copia completa y actualizada de los datos operativos.

**La migración permanece bloqueada expresamente fuera de testing.** No se entrega como un comando habilitado para migrar la principal. No tiene reversión automática: su `down()` rechaza el intento de revertir nombres sin un procedimiento de recuperación previamente autorizado.

## Historial y autenticación

`Usuario` declara la columna `contrasena` y el token de recuerdo del nuevo esquema mediante los métodos que Laravel permite sobrescribir. El proveedor de autenticación mantiene la clave técnica `password`. Se evita recortar o registrar las contraseñas.

`DatosHistoricosEnEspanol` permite consultar instantáneas JSON anteriores con sus equivalencias españolas sin reescribir el JSON histórico almacenado. Las claves de los manifiestos de respaldo y las opciones del cliente SQL mantienen sus contratos de interoperabilidad.

`FirmaHistorica` conserva los nombres, valores, tipos y orden del contrato JSON usado para calcular las firmas de ajustes y regularizaciones. Los nombres SQL nuevos no cambian una huella existente ni autorizan de nuevo una operación. Esto incluye los campos opcionales del contrato anterior que podían no existir físicamente, como `lock_version` de una ubicación.

La lectura final de la principal encontró un ajuste y su autorización en estado `requested`, y un respaldo también `requested`. Se conservaron sin aprobarlos ni ejecutarlos. Se incorporaron pruebas de compatibilidad independientes de la proyección de producción: reconstruyen el contrato anterior desde proyecciones SQL y comprueban la autorización y ejecución en testing para cantidades, identidades y devoluciones excepcionales. El ensayo futuro con una copia operativa completa sigue siendo obligatorio antes del despliegue.

## Pruebas y evidencias

Las pruebas se ejecutan exclusivamente en `sga_rio_grande_testing`, con instancia, puerto, servidor y directorio de datos comprobados antes de escribir. El guardián de aislamiento conserva sus bytes originales.

Las pruebas que vuelven a ensayar algoritmos de antiguas migraciones de permisos utilizan `Tests\Support\CargadorMigracionArchivada`. Verifica la ruta, la huella SHA-256 del archivo histórico y la conexión de testing; adapta únicamente identificadores SQL en memoria. Los archivos anteriores y las comprobaciones de revocaciones, roles desconocidos y sesiones permanecen conservados. Este adaptador es exclusivo de pruebas y no se utiliza para el despliegue.

Los resultados definitivos se registrarán en `docs/evidence/traduccion-espanol.json`. Los intentos fallidos también se conservan fuera del repositorio; no equivalen a pruebas aprobadas. Las comprobaciones HTTP de PHPUnit no se presentan como UAT ni como un recorrido humano de navegador.

### Resultado técnico consolidado — 2 de noviembre de 2026

El corpus actual contiene **506 casos**. La consolidación del resultado más reciente de cada caso reúne **506 aprobados y 3.867 aserciones**, sin errores ni fallos finales. Es una regresión por lotes, no una ejecución única de 506 casos. El inventario de casos coincide exactamente con `phpunit --list-tests-xml`: no faltan casos ni se añaden resultados externos al corpus. Las tres pruebas nuevas de compatibilidad de firmas forman parte de esos 506 casos.

| Lote principal | Casos | Errores | Fallos | Resultado del lote |
| --- | ---: | ---: | ---: | --- |
| Regresión inicial completa de la traducción | 503 | 28 | 106 | Fallido; conservado como diagnóstico |
| Regresión sin el grupo de concurrencia | 452 | 2 | 5 | Fallido; casos corregidos y repetidos |
| Verificación de concurrencia y correcciones | 93 | 1 | 7 | Interrumpido por desconexión; repetido |
| Recuperación de casos afectados y aislamiento | 16 | 0 | 0 | Aprobado, 239 aserciones |
| Primera comprobación de contratos históricos | 3 | 0 | 1 | Detectó un campo opcional de firma; corregido |
| Firmas históricas y regresión de operaciones críticas | 35 | 0 | 0 | Aprobado, 409 aserciones |
| Devoluciones, Backup y esquema | 15 | 0 | 0 | Aprobado, 126 aserciones |

El lote inicial también informó tres casos riesgosos, que no se presentan como aprobados en ese intento. Los casos se solapan entre lotes: los conteos de esta tabla no deben sumarse como casos distintos aprobados. Las trazas originales conservan sus hashes y se mantienen fuera del repositorio. La evidencia consolidada documenta la procedencia de cada caso.

La verificación SQL posterior en testing comprobó **53 tablas, 44 migraciones, 123 permisos y 260 relaciones rol–permiso**, con las 52 huellas de datos iguales a la línea base y sin fixtures pendientes. Revisó **166 claves foráneas, 103 CHECK y 143 índices únicos**, sin huérfanos, violaciones de CHECK ni grupos duplicados.

La principal continúa con **53 tablas y 43 migraciones**, 123 permisos y 260 relaciones rol–permiso. Sus datos y estructuras mantienen las mismas huellas que antes del trabajo. La versión operativa no tiene migraciones pendientes; la rama de traducción prepara solamente la migración nueva. `.env`, dependencias, las 43 migraciones anteriores, el guardián de aislamiento y el bootstrap de testing conservan sus bytes anteriores.

Durante la depuración se identificaron datos ficticios parciales dejados por pruebas con conexiones autocommit. Se retiraron únicamente sus identificadores de testing, con las FK y CHECK activas y contra una línea base vacía comprobada. La creación del contexto de inventario de pruebas quedó transaccional para evitar ese fallo. No se reiniciaron identificadores.

Un lote posterior de 93 casos sufrió una desconexión de MariaDB (`MySQL server has gone away`) tras una interrupción prolongada de la ejecución. El ejecutor registró una duración de 19 horas; no se atribuye una causa de infraestructura no demostrada. La limpieza de ese caso tampoco pudo terminar con la conexión cerrada. Se recuperaron exclusivamente sus fixtures en una transacción, exigiendo que las 52 huellas canónicas coincidieran con la línea base antes del commit. Después se repitieron los 16 casos afectados y de aislamiento con una conexión nueva: 16 aprobados, 239 aserciones. No se ampliaron timeouts ni se retiraron comprobaciones.

## Despliegue principal pendiente

No ejecutar `php artisan migrate` desde esta rama contra la base operativa. La habilitación y la aplicación principal requieren una revisión separada del procedimiento y autorización específica después de revisar las pruebas.

Procedimiento propuesto para esa fase:

1. Revisar y publicar conjuntamente el código y la migración; acordar una ventana de mantenimiento. Detener sesiones de escritura y procesos de colas. Comprobar que no hay trabajos pendientes serializados con clases anteriores; la lectura actual encontró vacías las tablas `jobs` y `failed_jobs`.
2. Capturar versiones, identidad completa, 53 tablas, 43 migraciones, índices, FK, CHECK, conteos y huellas de todas las tablas; comprobar de nuevo que no hay cambios locales ajenos.
3. Crear un respaldo SQL completo nuevo fuera del repositorio, verificar cierre, tamaño y SHA-256 y comprobar su restauración en una instancia o destino aislado autorizado.
4. Ensayar allí la transformación con los datos históricos completos. Revisar movimientos, saldos, custodias, autorizaciones, cuentas, permisos y archivos de respaldo. La habilitación fuera de testing necesitará controles equivalentes y una revisión de código específica.
5. Con autorización posterior, aplicar exclusivamente la migración nueva y sustituir el código durante la misma ventana de mantenimiento. El esquema esperado conserva 53 tablas y pasa a 44 migraciones aplicadas; los datos, permisos y relaciones rol–permiso deben conservar sus conteos y huellas canónicas.
6. Verificar todas las restricciones, ausencia de huérfanos y duplicados, inicio de sesión, permisos, documentos, reportes e integridad. Abrir el servicio solamente tras completar esas comprobaciones.

MariaDB realiza commits implícitos de DDL: una excepción no revierte automáticamente los cambios de nombres y restricciones. Ante un fallo parcial, detener toda escritura, preservar la evidencia y preparar una recuperación autorizada usando la copia anterior y el código correspondiente. No repetir a ciegas la migración, no ejecutar `migrate:rollback` ni restaurar automáticamente sobre la principal.

La aplicación anterior se mantiene disponible hasta que esa fase esté autorizada y verificada. No cambiar `.env`, dependencias ni migraciones históricas para alternar entre ambas versiones.

## Material para revisión

- [Diccionario completo de tablas y campos](diccionario-datos-espanol.md).
- [Equivalencia de clases renombradas](correspondencia-clases-espanol.md).
- [Diagramas simplificados en español](modelo-datos-espanol.md).
- [Informe de evidencia](evidence/traduccion-espanol.json).
- [Cobertura y procedencia por caso](evidence/traduccion-espanol/cobertura.json).
- [JUnit consolidado por lotes](evidence/traduccion-espanol/regresion-consolidada.xml).
- [Archivos modificados, creados y sustituidos](evidence/traduccion-espanol/archivos.json).

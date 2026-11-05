# Renovación autorizada y activación de la versión en español

## Alcance

El propietario autorizó aplicar la traducción y reemplazar los datos anteriores por un nuevo escenario ficticio de un año, ampliando el catálogo. Esta autorización comprende retirar los registros operativos y las cuatro cuentas simuladas anteriores. Se conserva íntegramente el administrador existente, su contraseña cifrada, los roles, los permisos, sus asignaciones y el historial de migraciones. No se crean cuentas administrativas desconocidas ni se restablecen privilegios revocados.

El escenario continúa identificado como simulación; no acredita operaciones reales, certificaciones de EPP ni resultados académicos de aceptación humana. El repositorio no contiene volcados SQL, contraseñas o archivos de configuración privados.

## Procedimiento y garantías

1. Mantenimiento de la aplicación operativa para impedir escrituras concurrentes.
2. Respaldo SQL nuevo fuera del repositorio: 871.239 bytes, 53 definiciones de tablas, cierre de `mysqldump` y SHA-256 `c20853f28fe8a002101dae9fd1b3edbe0ecd8a6a37ea50c4d00bf4d5fc1f2ede`.
3. Restauración en `sga_rg_ensayo_espanol_20261102`, un esquema nuevo de la instancia local verificada. Sus 53 huellas de datos y estructuras coinciden con el origen.
4. Traducción de la copia histórica mediante la migración nueva, comparando las 52 huellas canónicas de datos antes y después. La tabla de migraciones registra la aplicación adicional.
5. Ensayo completo del reemplazo: eliminación con FK y CHECK activas, conservación del administrador y la configuración, generación mediante las acciones existentes, conciliación final y commit único. Ante un error, toda la sustitución de datos se revierte.
6. Activación del código y migración principal durante la misma ventana de mantenimiento. El DDL de MariaDB tiene commits implícitos y requiere una recuperación administrativa desde el respaldo si falla parcialmente; no se intenta invertir automáticamente ni repetir un esquema incompleto.
7. Verificación SQL, acceso HTTP y apertura del servicio.

La capacidad `OperacionLocalAutorizada` existe solamente durante una llamada de consola: exige identidad exacta, respaldo externo y SHA-256, mantenimiento para la principal y exclusión mediante un bloqueo SQL. No modifica `TestingDatabaseGuard`; PHPUnit sigue limitado a `sga_rio_grande_testing`. La renovación no tiene endpoint web ni se registra como seeder general.

Las FK se mantienen activas durante el reemplazo. Se limpia exclusivamente la referencia nullable del ítem devuelto al ciclo de custodia y se retiran primero los registros dependientes. Si hay dependencias no resueltas, se cancela la transacción completa. No se reinician identificadores ni se borran tablas. Los respaldos físicos anteriores permanecen conservados, aunque se retiran sus solicitudes del escenario anterior junto con los demás datos operativos.

## Catálogo y período

Período nuevo: **3 de noviembre de 2025 a 2 de noviembre de 2026**. Participan 20 trabajadores operativos y 4 personas de staff. Se conserva la separación entre trabajadores y cuentas de acceso.

El catálogo comprende 116 productos: 42 combinaciones de prendas y tallas, 11 tallas de botines, 39 artículos de protección y consumo, y 24 tipos de herramientas e instrumentos. Hay 192 unidades individualizadas. Se incluyen ropa, guantes, protección respiratoria/auditiva/ocular, elementos de señalización y bloqueo, consumibles de mantenimiento, instrumentos y herramientas manuales o eléctricas. Las denominaciones son ejemplos y no certifican equivalencia o aptitud técnica.

Las 48 dotaciones semestrales, entregas diarias, reposiciones, préstamos y devoluciones se confirman mediante las acciones transaccionales del sistema. Los borradores no alteran el saldo. La ubicación inicial histórica de las unidades no se reinterpreta como existencia física.

## Resultados del ensayo ejecutado

| Elemento | Resultado |
| --- | ---: |
| Tablas / migraciones | 53 / 44 |
| Permisos / asignaciones | 123 / 260 |
| Trabajadores / usuarios | 24 / 5 |
| Categorías / productos | 6 / 116 |
| Unidades individualizadas | 192 |
| Apertura y remitos, incluido un borrador | 14 |
| Entregas, incluido un borrador | 778 |
| Préstamos, incluido un borrador | 33 |
| Devoluciones | 25 |
| Movimientos / posiciones de existencias | 2.206 / 116 |
| Herramientas en custodia | 39 |
| Diferencias de saldo / conflictos de custodia | 0 / 0 |
| FK / CHECK / índices únicos revisados | 166 / 103 / 143 |
| Huérfanos / violaciones / duplicados | 0 / 0 / 0 |

Estos resultados del ensayo se reprodujeron en la principal. La migración de traducción terminó en 1 minuto y 57 segundos, manteniendo idénticas las 52 huellas de datos antes de ejecutar el reemplazo autorizado. La segunda carga principal terminó correctamente, conservando el registro de auditoría final. La comprobación posterior verificó las 166 FK, 103 CHECK y 143 índices únicos, sin huérfanos, violaciones ni duplicados; también confirmó que los identificadores operativos anteriores ya no están presentes. La huella del administrador coincide con la anterior al despliegue.

## Pruebas ejecutadas para esta etapa

El lote seleccionado terminó con **85 pruebas aprobadas y 541 aserciones**: carga anual ampliada, rechazo de identidad, rollback, capacidad administrativa de consola, autenticación, permisos, aislamiento, panel, reportes detallados, navegación y paginación. Se ejecutó exclusivamente en `sga_rio_grande_testing`. Una prueba adicional del registro final de auditoría aprobó **1 caso y 5 aserciones** sobre el esquema existente. Total de esta etapa: **86 casos, 546 aserciones**, en dos lotes; no es una regresión completa nueva de todo el sistema.

La comprobación HTTP de la copia de ensayo aprobó 43 verificaciones con Encargado y Consulta/Auditor, incluidos listados, segunda página de productos, reportes, rechazo de creación no autorizada y error 419 en español al omitir CSRF. Son comprobaciones automatizadas y no una aceptación humana de navegador.

El servicio principal reabrió en `http://127.0.0.1:8000` y aprobó las mismas **43 comprobaciones HTTP** con los dos perfiles. Solo se utilizaron el acceso normal y consultas de las pantallas; no se crearon documentos adicionales ni se ejecutó PHPUnit contra la principal. Se reinició su servidor local y se limpiaron las cachés de vistas, rutas y configuración, conservando `.env`. El servidor temporal del ensayo se detuvo; sus datos y evidencias se conservan.

La cobertura actual de 510 casos coincide exactamente con el inventario de PHPUnit. La [consolidación](evidence/renovacion-espanol-cobertura.json) conserva el resultado más reciente de cada caso entre la fase anterior y los dos lotes nuevos: 510 aprobados, 3.890 aserciones. No se presenta como una ejecución única de los 510 casos. La evidencia previa y los intentos fallidos permanecen conservados.

## Incidencias de preparación

- La comparación inicial de nombres de tablas se detenía por el orden de la colación SQL frente al orden binario de PHP. Se ordenan ambas listas con el mismo criterio; el intento se detuvo sin borrar registros.
- El primer ensayo automatizado anual recibió HTTP 503 al revisar el panel porque su aplicación había arrancado en mantenimiento. Se conserva el resultado fallido y se repite con el mantenimiento separado de la aplicación operativa. No se elimina la comprobación de respuesta 200.
- La primera comprobación HTTP utilizó `/productos/crear`, pero la ruta real generada por el recurso Laravel es `/productos/create`. Se corrigió el guion, conservando la exigencia de HTTP 403 para Consulta/Auditor.
- El primer reemplazo principal se revirtió al guardar la auditoría: `consola_autorizada` excedía los 16 caracteres admitidos en `origen`. Las 52 huellas de datos canónicas volvieron a coincidir exactamente con el estado anterior al reemplazo. Se corrigió al código existente `console`, sin alterar el esquema, y se comprobó el registro completo en la copia aislada antes de repetir la operación. La repetición no vuelve a ejecutar la migración de traducción.

## Recuperación y operación

El respaldo SQL y una copia del código anterior están en la carpeta privada externa `C:\Users\jcsem\SGA-evidence\traduccion-20261101\renovacion-20261102`. Las credenciales nuevas se generan aleatoriamente y permanecen exclusivamente en la evidencia privada. `.env`, dependencias, migraciones históricas y controles de aislamiento deben conservar sus huellas.

La migración de traducción requiere el comando explícito `php artisan sga:traducir-esquema --help`, con los parámetros verificados de identidad y respaldo. No se debe aplicar mediante un `migrate` indiscriminado sobre una base operativa anterior. El comando no elimina ni carga datos; el reemplazo autorizado es una operación independiente y no debe repetirse automáticamente.

La versión previa con modelos y columnas en inglés debe usarse únicamente con su respaldo compatible. Una vez traducida la principal, no corresponde arrancar esa versión contra el esquema nuevo.

El [informe verificable de cierre](evidence/renovacion-espanol-2026-11-02.json) registra conteos, integridad, conservación del acceso y hashes de las evidencias privadas. No hubo commit, merge ni push. La aceptación humana del sistema y cualquier publicación en GitHub son actuaciones separadas.

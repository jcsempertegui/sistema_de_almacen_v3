# Sprint 5 — Informe de implementación local

Base: `3d368275383ee472e1e58cdb57ed94b0a6e9cd96`. Rama `feature/sprint-5-control-and-close`. Autorización del usuario limitada a implementar el diseño aprobado y ejecutar pruebas aisladas. No se publicó, migró la principal ni crearon cuentas reales.

## Funcionalidades implementadas

- HU-24: ajustes por conteo con diferencia DECIMAL calculada en SQL, selección manual de ubicación/identidades, versión de saldo observada y rechazo de conteos obsoletos. Guardar y solicitar no afectan stock.
- HU-25: compensación total de remitos/aperturas, entregas, préstamos, devoluciones y ajustes ejecutados. Se generan líneas inversas desde movimientos originales, sin campos de cantidades editables ni cascadas. Los originales y sus fechas permanecen intactos, con vínculo visible a la compensación.
- Autorización: otra persona, motivo, 24 horas, huella de contenido/referencias/saldos, revocación y uso único. Prueba histórica de usuario/rol/versiones del aprobador; pérdida y posterior regrant no revive aprobaciones. Lecturas actuales bloqueantes evitan autorizar desde una vista REPEATABLE READ anterior.
- Custodia: ciclos y cierres con referencia física. Compensar una devolución reabre un ciclo del mismo ítem; una recepción posterior no borra ni sustituye su primer cierre. Compensar un préstamo pendiente exige ingreso físico. Nuevos préstamos concurrentes y dependencias bloquean correcciones incompatibles.
- HU-26: reportes paginados de existencias, movimientos, remitos, entregas, préstamos, devoluciones, responsabilidades, correcciones y disponibilidad, además de auditoría crítica. Impresión y CSV UTF-8 con neutralización de fórmulas. Corte de existencias desde ledger; sin valoración monetaria ni conversiones.
- HU-27: solicitudes de respaldo, generación en consola con cliente MariaDB, ACL privada, manifiesto y SHA-256, verificación y descarga autorizada. Solicitud/aprobación/revocación de ensayos aislados; ejecución exclusivamente en consola con confirmación exacta del destino nuevo. No hay restauración destructiva principal ni eliminación automática de copias.
- HU-28: eventos críticos y secuencias de autorización con autoría/motivos; vistas de consulta y exportación. Los eventos de descarga describen autorización de entrega del archivo, no recepción de todos los bytes por el navegador.
- HU-29: matriz, diccionario, guion personal y procedimiento de recuperación preparados. La aceptación humana y el contraste con el catálogo definitivo de la tesis siguen pendientes de evidencias externas.

Blade/CSS existente y JavaScript nativo. Sin instalar paquetes, actualizar Node/PHP/Laravel/MariaDB o editar .env. Los 42 permisos nuevos conservan revocaciones y roles desconocidos. Configuración inicial en testing: Administrador 123, Encargado 96 y Consulta/Auditor 41 capacidades; 260 vínculos. Los permisos de Backup se reservan inicialmente al Administrador.

## Esquema y pruebas de migración

Once tablas nuevas, ocho migraciones aditivas enumeradas en el [diccionario](data-dictionary-sprint-5.md). La quinta fuente del ledger y las proyecciones no crean otro inventario. Las migraciones anteriores permanecen intactas; las nuevas rechazan rollback destructivo.

Se ejecutó un ensayo único desde testing con 35 migraciones: dos documentos S3, cinco movimientos y documentos S4 con dos préstamos y una devolución. Las primeras cinco migraciones conservaron todas sus columnas históricas y derivaron dos ciclos y un cierre, incluida custodia pendiente. Los fixtures propios se retiraron por sus IDs sin deshabilitar FK/CHECK ni borrar/recrear esquemas.

Las migraciones 6–8 añadieron después FK compuestas de pertenencia, regularización, reapertura y posición/dirección, verificadas en el esquema actualizado. No se presenta el primer ensayo como ejecución histórica de las ocho juntas. El ensayo completo desde una copia antigua queda reservado para la futura prevalidación autorizada; no se reseteó testing para repetirlo.

## Resultados reales y reservas

La tanda final focalizada aprobó **28 pruebas y 262 aserciones**, con nueve carreras entre procesos que observaron espera real en INNODB_LOCK_WAITS. Cubre contadores competidores, doble compensación, idempotencia, revocación antes/después, pérdida de permisos del ejecutor, desactivación del rol del aprobador y compensación de devolución frente a préstamo nuevo en ambos órdenes. No se ampliaron timeouts ni se debilitaron comprobaciones anteriores.

Regresión completa: **429 pruebas y 3.406 aserciones aprobadas**, cero errores, fallos u omisiones. Tiempo de consola 19:53,372; agregado JUnit 1.192,864878 segundos. Los **389 archivos** incluidos en el manifiesto congelado permanecieron idénticos hasta terminar esa ejecución. Después se ajustaron exclusivamente etiquetas en español, filtros de fecha visibles y sus aserciones HTTP: **3 pruebas/75 aserciones aprobadas**, sin cambios de lógica de inventario, autorizaciones, permisos o esquema. No se suman estas tandas superpuestas como pruebas distintas.

El directorio lang no estaba incluido en el manifiesto inicial de 389; el manifiesto final incorpora su archivo español y registra **390 archivos**. La [evidencia agregada](evidence/sprint-5-implementation.json) identifica los cuatro archivos de presentación/aserciones posteriores y conserva ambos mapas, el JUnit completo y la repetición HTTP. Pint --dirty --test y git diff --check aprobaron también la revisión final.

Los resultados históricos de desarrollo, incluidos fallos corregidos, permanecen en la [matriz](sprint-5-test-matrix.md). La generación real de SQL se ejecutó exclusivamente en testing. Las pruebas de orquestación de backup utilizan cliente simulado y se distinguen del cliente real. No se ejecutó restauración real del módulo, recuperación principal ni recorrido de navegador.

Las tandas de carrera y regresión usan temporalmente SetThreadExecutionState en el proceso que espera PHPUnit, restaurando su estado anterior en finally. No cambian el plan de energía, la política de ejecución ni los límites de las pruebas. No se debe suspender manualmente el equipo durante mediciones.

## Condiciones operativas y revisión

- La comparación final de solo lectura confirmó la principal intacta: **42 tablas, 35 migraciones, 81 permisos y 166 vínculos**. Ninguna diferencia de datos o estructuras respecto de la captura inicial; .env y las 35 migraciones anteriores conservan sus hashes. Hay exactamente ocho pendientes del Sprint 5; mantenimiento desactivado y cero usuarios/trabajadores reales creados.
- El mutex compartido serializa escrituras; no se presenta esta suite como prueba de carga. La generación de backups exige ventana controlada y detener escritores externos al mutex.
- Correcciones ordinarias requieren catálogo activo y compatible; no reactivan referencias. La excepción específica de devolución S4 permanece operativa. Una corrección no permite dar de baja ficticiamente herramientas prestadas.
- La migración de ciclos conserva columnas y cierres anteriores. Las consultas de pendientes dejan de inferir el estado actual únicamente desde loan_item_settlements.
- La recuperación principal requiere intervención administrativa y autorización propia, considerando commits implícitos de DDL. No hay botón de restauración sobre datos reales.
- Frecuencia/retención/copia externa de respaldos y objetivos de recuperación deben concretarse operativamente; no se borran datos ni se asume una política no acordada.
- No existe todavía administrador real en la principal. Su creación y el entorno de aceptación requieren autorización específica y datos del responsable.

## Verificación final de testing y respaldo real

Testing quedó con **53 tablas, 43 migraciones, ninguna pendiente, 123 permisos y 260 vínculos**. Capacidades activas 123/96/41. Integridad de 166 FK, 103 CHECK y 143 índices únicos incluyendo PK: cero huérfanos, incumplimientos o duplicados. Los fixtures de usuarios, trabajadores, catálogo, documentos, movimientos, saldos, custodias, correcciones, autorizaciones y respaldos temporales quedaron en cero; no se reiniciaron AUTO_INCREMENT.

La generación real se repitió sobre el esquema final de testing con el cliente MariaDB: **238.775 bytes y 53 tablas**, SHA-256 `c24c438669857b787697c77139a297fbe3b0c22aabc1992f9cfa2fe46c26e842`. Manifiesto, tablas/filas, estructuras, integridad, cierre y hash comprobados; credenciales temporales retiradas y fixtures propios limpiados. Archivo y logs privados fuera del repositorio. No fue un respaldo de la principal ni un ensayo real de restauración.

## Archivos y futuras operaciones

El [listado exacto](sprint-5-changed-files.md) distingue archivos nuevos y modificados. El estado al terminar la implementación era local, sin commit, merge ni push. El usuario autorizó posteriormente actualizar el repositorio; véase [seguimiento de publicación](sprint-5-publication.md). Las ocho migraciones de la principal permanecen pendientes; no ejecutar migrate general ni repetir S3/S4.

Para una futura aplicación autorizada: comprobar nuevamente Git, destino y pendientes; generar respaldo actual con restauración comprobada; ensayar las ocho migraciones sobre copia histórica aislada autorizada; limitar la ejecución a las ocho rutas enumeradas en el diccionario con `php artisan migrate --database=mysql --force --path=...`; verificar conservación, restricciones y permisos antes de retirar mantenimiento. Esta descripción no autoriza ejecutar esos comandos ahora.

## Retrospectiva técnica

Reutilizar el mutex y el ledger permitió mantener una sola contabilización. Los ciclos conservaron cierres antiguos y resolvieron la reapertura sin borrar obligaciones. El ensayo temprano detectó las necesidades de pertenencia que se reforzaron mediante migraciones adicionales, sin editar las anteriores. Las pruebas HTTP detectaron errores de compilación/traducción y una selección vacía normalizada por Laravel; los fallos originales se conservaron y las repeticiones se registraron por separado.

Mejoras para próximas revisiones: incluir lang desde el primer manifiesto congelado, preparar un destino autorizado para ensayar todo el upgrade histórico y completar aceptación personal antes del cierre académico. La conformidad del diseño no se presenta como aprobación funcional del Sprint 5.

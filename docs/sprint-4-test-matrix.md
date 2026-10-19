# Sprint 4 — Matriz de pruebas

Los casos y resultados deben distinguirse. Evidencia original externa: `C:\Users\jcsem\SGA-evidence\sprint-4`. Todas las escrituras de pruebas se limitan a `sga_rio_grande_testing`; verificar APP_ENV, configuración y SELECT DATABASE(). No ejecutar suites simultáneas sobre esa base.

| Requisito | Historia | Código | Prueba / evidencia |
|---|---|---|---|
| Borrador sin stock; edición; trabajador activo | HU-18 | SaveDelivery | DeliveryManagementTest; WorkerOperationHttpTest |
| Confirmación única, precisión, rollback, saldo suficiente | HU-19 | ConfirmDelivery, WorkerStockPosting | DeliveryManagementTest; WorkerOperationConcurrencyTest |
| Producto individual prestable y custodia exclusiva | HU-20 | SaveLoan, ConfirmLoan | LoanManagementTest; carreras exclusive custody/loan retry |
| Devolución parcial/total, trabajador inactivo, otro destino | HU-21 | SaveReturn, ConfirmReturn | ReturnManagementTest |
| Cierre único y FK al movimiento | HU-21 | loan_item_settlements | ReturnManagementTest; competing returns/return retry |
| Excepción específica sin reactivación | HU-21 | ReturnEligibility, ReturnRegularization | ReturnManagementTest; flujo HTTP de Encargado/Administrador |
| Caducidad, revocación, cambios e historial | HU-21 | ReturnRegularization | ReturnManagementTest; carreras de autorización en ambos órdenes |
| Responsables e historial de cuatro fuentes | HU-22 | WorkerOperationController, InventoryMovement | WorkerOperationHttpTest; consultas HTTP |
| Saldo físico frente a disponibilidad operativa | HU-23 | availability | WorkerOperationHttpTest; unidad inactiva recibida |
| SQL: fuente exclusiva, UNIQUE, dirección, pertenencia | HU-19/20/21 | migraciones 000003–000007 | WorkerOperationSchemaTest; ReturnManagementTest |
| Permisos, revocaciones, roles desconocidos, CSRF | Todas | Policies, permisos, SecurityMutation | WorkerOperationPermissionTest; WorkerOperationHttpTest |
| Espera SQL real y vista REPEATABLE READ antigua | Todas | confirmaciones | WorkerOperationConcurrencyTest; INNODB_LOCK_WAITS y comparación saldo/ledger |
| Preservación de historial S3 | Todas | seis migraciones nuevas | tests/Support/verify-sprint4-upgrade.php, ejecución única desde testing con 29 migraciones |
| Límites CSV y rechazo NUL previo a trim | HU-17 heredada | InitialStockCsv | InitialStockCsvBoundaryTest |
| TEST-S3-01 | Regresión heredada | ProductConcurrencyTest | diagnóstico de fase, terminación prematura y espera SQL sin ampliar timeout |

## Resultados focalizados ya ejecutados

| Ejecución | Pruebas / aserciones | Resultado |
|---|---|---|
| Primera etapa inicial | 14 / 4.011 | 1 fallo: NUL final aceptado antes de corregir el parser |
| Primera etapa repetida, stage-1.xml | 14 / 168 | Aprobada |
| schema.xml | 1 / 5 | Aprobada |
| deliveries.xml | 5 / 16 | Aprobada |
| loans.xml, con regresión de entregas | 7 / 24 | Aprobada |
| returns.xml inicial | 5 / 25 | Aprobada |
| http.xml inicial | 4 / 41 | Aprobada |
| concurrency.xml inicial | 9 / 36 | 9 errores de fixture: Role no dispone de factory; no llegaron a iniciar carreras |
| concurrency-recheck.xml | 9 / 135 | Aprobada después de crear explícitamente el rol de prueba |
| targeted-expanded.xml | 14 / 114 | Aprobada |
| regression.xml, primera suite completa | 399 / 3.121 | 1 error y 3 fallos; no aprobada. Ubicación equivocada en test, conteos RBAC anteriores, auditoría paginada y timeout durante suspensión del equipo |
| stability-targeted.xml | 47 / 400 | Aprobada con las correcciones, once carreras S4 y siete de productos; sin ampliar timeouts |
| regression-final.xml | **401 / 3.143** | **Aprobada: cero errores, fallos y omisiones; 323 fuentes congeladas sin cambios** |

Los conteos de ejecuciones se superponen: no sumarlos como pruebas distintas. Los casos adicionales de carrera frente a desactivación de identidad se incorporan a la regresión posterior. El resultado de la suite completa se registra en el informe de implementación y su JSON; esta tabla focalizada no lo sustituye.

La suite final añade además cobertura de recuperación de formularios tras entradas anidadas inválidas y del CHECK de fuente exclusiva de posición. La correlación de los timeouts con suspensión del equipo se conserva en [sprint-4-stability.json](evidence/sprint-4-stability.json). No se omiten carreras ni se amplían límites para obtener un resultado aprobado.

Los JUnit públicos están en [evidence/sprint-4](evidence/sprint-4/regression-final.xml); el [JSON agregado](evidence/sprint-4-implementation.json) registra resultados, hashes, preservación e integridad. La verificación posterior dejó testing sin fixtures de negocio. La aceptación humana siguiente continúa pendiente.

## Aceptación humana pendiente

Preparar un entorno separado con autorización antes de crear datos. No usar la principal ni testing para recorridos mientras ejecuta PHPUnit. Revisar personalmente: entregas por cantidad/individual, préstamo de dos herramientas, devolución parcial a otro destino, devolución restante, trabajador desactivado, excepción aprobada/revocada, historial, perfiles, móvil/escritorio, teclado y zoom. Confirmación significa operación física. No declarar estas pruebas realizadas a partir de los tests HTTP.

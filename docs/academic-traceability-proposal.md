# Propuesta de matriz académica — pendiente de contraste documental

Fecha: 25 de octubre de 2026, America/La_Paz. Base de código: `a6e10b7` más la estabilización local sin publicar. No modifica la tesis, las historias ni el alcance de los cinco sprints.

## Fuentes y estado

No se localizaron en el repositorio la tesis anterior, la versión académica 2.0 ni el catálogo histórico HU1–HU13. El usuario indicó que enviará esas fuentes. Hasta recibirlas no se asignan correspondencias oficiales ni se reconstruyen sus enunciados a partir de los números.

| Catálogo por contrastar | Cantidad declarada por el usuario | Fuente/texto disponible | Correspondencia oficial |
|---|---:|---|---|
| Objetivos específicos de la tesis anterior | 6 | pendiente | pendiente |
| Requisitos funcionales académicos 2.0 | 13 | pendiente | pendiente |
| Requisitos no funcionales académicos 2.0 | 10 | pendiente | pendiente |
| Historias históricas HU1–HU13 | 13 | identificadores declarados, sin enunciados | pendiente para cada una |
| Historias del desarrollo actual HU-01 a HU-29 | 29 | planes S1–S5 y código | trazabilidad técnica propuesta abajo |

Los identificadores y el texto de los requisitos externos se copiarán literalmente de sus fuentes, indicando versión y página. La posición de una fila no constituye un identificador oficial. HU1 histórica no se declara equivalente a HU-01 actual por compartir el número.

## Estructura propuesta para la matriz definitiva

Una fila por criterio verificable, permitiendo relaciones de muchos a muchos:

| Objetivo literal y página | Requisito oficial literal y página | HU histórica literal | HU actual / sprint | Criterio observable | Código | Prueba / evidencia fechada | Cobertura y diferencia | Decisión del responsable |
|---|---|---|---|---|---|---|---|---|
| pendiente de fuente | pendiente de fuente | pendiente de fuente | referencia de la tabla técnica | descomponer sin cambiar el requisito | archivo/ruta | automática, operativa o humana, identificadas por separado | total, parcial, sin evidencia o fuera de alcance, después del contraste | aprobar / observar / aclarar |

No usar el total de pruebas como prueba de cumplimiento de cada objetivo. Cada vínculo deberá sostenerse por un criterio y una evidencia concreta. Un requisito no cubierto se registra como brecha para decidir; no autoriza una función nueva.

## Trazabilidad técnica disponible de las historias actuales

Los archivos de código y pruebas se indican desde la raíz del repositorio. Son referencias de implementación y evidencia automatizada, no aceptación académica o visual. Los planes originales conservan la numeración aprobada para el desarrollo.

| Historia actual | Sprint y propósito documentado | Código principal | Prueba o evidencia técnica |
|---|---|---|---|
| HU-01 | S1, autenticación | `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | `tests/Feature/AuthenticationTest.php` |
| HU-02 | S1, usuarios | `app/Actions/Users/CreateUser.php`, `UpdateUser.php` | `tests/Feature/UserManagementTest.php` |
| HU-03 | S1, roles/permisos | `app/Actions/Roles/SaveRole.php`, `app/Services/SecurityMutation.php` | `tests/Feature/RbacManagementTest.php`, `PermissionAuthorizationTest.php`; `docs/sprint-1-hu03-corrective.md` |
| HU-04 | S1, trabajadores | `app/Actions/Workers/SaveWorker.php` | `tests/Feature/WorkerManagementTest.php` |
| HU-05 | S2, categorías | `app/Actions/Catalog/SaveCategory.php` | `tests/Feature/Catalog/CategoryManagementTest.php` |
| HU-06 | S2, unidades de medida | `app/Actions/Catalog/SaveMeasurementUnit.php` | `tests/Feature/Catalog/MeasurementUnitManagementTest.php` |
| HU-07 | S2, atributos por categoría | `app/Actions/Catalog/SaveCatalogAttribute.php`, `SaveCategoryAttribute.php` | `tests/Feature/Catalog/TechnicalAttributeManagementTest.php`, `CategoryAttributeManagementTest.php` |
| HU-08 | S2, productos | `app/Actions/Catalog/SaveProduct.php`, `app/Services/ProductAttributeValidator.php` | `tests/Feature/Catalog/ProductManagementTest.php`, `ProductAttributeValueTest.php` |
| HU-09 | S2, ubicaciones | `app/Actions/Catalog/SaveLocation.php`, `app/Services/LocationHierarchy.php` | `tests/Feature/Catalog/LocationManagementTest.php`, `LocationConcurrencyTest.php` |
| HU-10 | S2, unidades individualizadas | `app/Actions/Catalog/SaveIndividualUnit.php` | `tests/Feature/Catalog/IndividualUnitManagementTest.php`, `IndividualUnitConcurrencyTest.php` |
| HU-11 | S3, recibir materiales | `app/Actions/Inventory/ConfirmInventoryDocument.php` | `tests/Feature/Inventory/InventoryManagementTest.php` |
| HU-12 | S3, gestionar remitos | `app/Actions/Inventory/SaveInventoryDocument.php`, `CancelInventoryDocument.php` | `tests/Feature/Inventory/InventoryManagementTest.php` |
| HU-13 | S3, confirmación única | `app/Actions/Inventory/ConfirmInventoryDocument.php` | `tests/Feature/Inventory/InventoryConcurrencyTest.php`, `InventoryIntegrityTest.php` |
| HU-14 | S3, existencias | `app/Http/Controllers/Inventory/InventoryQueryController.php` | `tests/Feature/Inventory/InventoryManagementTest.php`, `InventoryIntegrityTest.php` |
| HU-15 | S3, trazabilidad | `app/Http/Controllers/Inventory/InventoryQueryController.php` | `tests/Feature/Inventory/InventoryManagementTest.php`; `docs/sprint-3-test-matrix.md` |
| HU-16 | S3, apertura manual | `app/Actions/Inventory/ConfirmInventoryDocument.php` | `tests/Feature/Inventory/InventoryManagementTest.php` |
| HU-17 | S3, apertura CSV | `app/Services/InitialStockCsv.php` | `tests/Feature/Inventory/InitialStockImportTest.php`, `InitialStockCsvBoundaryTest.php` |
| HU-18 | S4, borradores de entrega | `app/Actions/Deliveries/SaveDelivery.php` | `tests/Feature/Inventory/DeliveryManagementTest.php` |
| HU-19 | S4, confirmar entregas | `app/Actions/Deliveries/ConfirmDelivery.php` | `tests/Feature/Inventory/DeliveryManagementTest.php`, `WorkerOperationConcurrencyTest.php` |
| HU-20 | S4, préstamos individuales | `app/Actions/Loans/SaveLoan.php`, `ConfirmLoan.php` | `tests/Feature/Inventory/LoanManagementTest.php` |
| HU-21 | S4, devoluciones | `app/Actions/Returns/ConfirmReturn.php`, `app/Services/ReturnRegularization.php` | `tests/Feature/Inventory/ReturnManagementTest.php` |
| HU-22 | S4, responsabilidades | `app/Http/Controllers/WorkerOperations/WorkerOperationController.php` | `tests/Feature/Inventory/WorkerOperationHttpTest.php`; `docs/sprint-4-test-matrix.md` |
| HU-23 | S4, disponibilidad | `app/Services/WorkerStockPosting.php`, `app/Http/Controllers/WorkerOperations/WorkerOperationController.php` | `tests/Feature/Inventory/WorkerOperationHttpTest.php`; `docs/sprint-4-test-matrix.md` |
| HU-24 | S5, ajustes autorizados | `app/Services/InventoryCorrections.php` | `tests/Feature/Inventory/InventoryCorrectionsTest.php`, `CorrectionConcurrencyTest.php` |
| HU-25 | S5, compensaciones | `app/Services/InventoryCorrections.php`, `LoanCycles.php` | `tests/Feature/Inventory/InventoryCorrectionsTest.php`, `CorrectionConcurrencyTest.php` |
| HU-26 | S5, reportes | `app/Services/ControlReports.php` | `tests/Feature/Inventory/ControlHttpTest.php` |
| HU-27 | S5, Backup | `app/Services/DatabaseBackups.php`, `SqlBackupClient.php` | `tests/Feature/Inventory/DatabaseBackupsTest.php`, `SqlBackupClientConfigurationTest.php`; los ensayos reales del PC anterior están en `docs/sprint-5-deployment.md` |
| HU-28 | S5, auditoría | `app/Services/CriticalAudit.php`, `AdministrativeAudit.php` | `tests/Feature/AdministrativeAuditTest.php`, `tests/Feature/Inventory/ControlHttpTest.php` |
| HU-29 | S5, validación/cierre | documentación y matrices S1–S5 | regresión actual 458/3.485; guion final preparado; revisión humana y correspondencia académica pendientes |

Referencias de alcance: [S1](sprint-1.md), [S2](sprint-2.md), [S3](sprint-3.md), [S4](sprint-4.md), [S5](sprint-5.md). Para cada fila de la tesis futura se añadirán métodos de prueba y capturas concretos, evitando presentar el archivo completo como evidencia indiscriminada.

## Evidencia no funcional que puede contrastarse al recibir el catálogo

| Aspecto técnico observado, sin código académico asignado | Evidencia disponible | Límite de lo demostrado |
|---|---|---|
| Autenticación/autorización | `AuthenticationTest`, `PermissionAuthorizationTest`, `RbacManagementTest` | no equivale a certificación de seguridad ni prueba de penetración |
| Integridad y concurrencia | suites de integridad y concurrencia de catálogo/inventario | datos y escenarios controlados; no constituye benchmark ni disponibilidad en producción |
| Trazabilidad | auditoría administrativa/crítica y matrices S3–S5 | falta contraste humano de documentos y recorridos finales |
| Portabilidad de Backup | comando de versiones y pruebas de configuración | no se ejecutó un respaldo/restauración real en este PC durante la estabilización |
| Aislamiento de pruebas | `TestingDatabaseIsolationTest` y comparación de principal | cubre los puntos de entrada revisados; no limita por sí solo credenciales SQL fuera de la aplicación |
| Usabilidad/accesibilidad | guion de teclado, foco, móvil y zoom | no ejecutado; no declarar conformidad |
| Rendimiento, operación continua y recuperación | informes históricos limitados | requieren métricas/criterios oficiales y ensayos específicamente autorizados |

## Diferencias que requieren decisión

1. Recibir las versiones exactas, páginas y texto de los seis objetivos, 13 requisitos funcionales, diez no funcionales y HU1–HU13 históricas; completar los 42 elementos externos sin inventar enunciados.
2. Aprobar la relación muchos-a-muchos entre catálogo académico y HU-01 a HU-29, registrando divisiones, agrupaciones y requisitos sin correspondencia. No reemplazar la numeración histórica por la actual.
3. Contrastar explícitamente las reglas actuales: almacén único; Excel solo apertura sobre catálogo existente; remitos/entregas independientes; herramientas individualizadas; trabajadores sin cuenta; ajustes/anulaciones con autorización. Cualquier diferencia con la tesis se somete al responsable antes de cambiar texto o código.
4. Aceptar la separación entre evidencia histórica (PC anterior), instalación nueva (lote 1) y resultados actuales. No atribuir restauración de datos ni una prueba real de Backup a un diagnóstico de binarios.
5. Completar [aceptación funcional](final-functional-acceptance.md) y evidencias humanas. Obtener aprobación del usuario antes de actualizar el documento original de tesis o publicar esta propuesta.

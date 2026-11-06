# Correspondencia académica para el cierre — propuesta revisable

Revisión: 5 de noviembre de 2026. Referencia declarada por el autor: `jcst examen de grado VER 2.0.docx`, apartado 1.4.2 y Tablas 8–21, 65 y 66. Los textos e identificadores históricos siguientes fueron confirmados por el usuario en la conversación; no se dispone del archivo original dentro del repositorio para comprobar sus páginas y criterios literales. Esta propuesta no modifica la tesis ni acredita aprobación académica externa.

Se conservan dos catálogos diferentes: **HU1–HU13** de la tesis anterior y **HU-01–HU-29** del desarrollo actual. Los números de los requisitos de las Tablas 65 y 66 se conservan sin inventar prefijos oficiales RF/RNF.

## Objetivos específicos del apartado 1.4.2

1. Recabar los requerimientos del proceso de gestión de almacenes en CONFIPETROL.
2. Identificar las historias de usuario a partir de los requerimientos definidos.
3. Priorizar el Product Backlog según la importancia de las historias de usuario.
4. Determinar los Sprint necesarios para organizar el desarrollo del sistema.
5. Definir los entregables correspondientes a cada Sprint.
6. Efectuar pruebas funcionales a los entregables desarrollados.

| Objetivo | Evidencia preparada | Límite que debe resolver el documento final |
|---|---|---|
| 1 | Reglas de negocio de AGENTS y planes S1–S5; requisitos históricos de esta matriz | Adjuntar instrumentos y resultados reales de levantamiento; el código no demuestra entrevistas |
| 2 | Equivalencia de historias histórica/actual y planes de cada sprint | Aprobar las reformulaciones; no atribuir las HU actuales al documento anterior |
| 3 | Orden implementado y dependencias de los planes | Incorporar el backlog realmente utilizado y justificar prioridades; no inventar estimaciones o velocidad |
| 4 | `global-plan.md`, cinco sprints del desarrollo actual | Explicar el cambio de seis sprints históricos a cinco actuales |
| 5 | Planes, diccionarios, implementación y evidencias S1–S5 | Seleccionar entregables y fechas documentadas, conservando su contexto histórico |
| 6 | Suites técnicas, matrices de casos y guion de aceptación | Añadir resultados humanos con responsable, fecha y evidencia; no confundir HTTP automatizado con UAT |

## Requisitos funcionales — Tabla 65

Los criterios de esta tabla son puntos de contraste, no transcripciones inventadas. Salvo las dimensiones de HU6 indicadas por el usuario, falta contrastar los criterios literales del documento original. «Ampliada» describe el alcance técnico actual, sin declarar cumplidos todos los criterios históricos.

| Nº y requisito académico textual | Historia original confirmada | HU actuales / sprint | Módulo | Criterio histórico por contrastar | Implementación actual verificable | Cobertura propuesta | Prueba técnica | Evidencia | Diferencia / decisión pendiente |
|---|---|---|---|---|---|---|---|---|
| 1. Iniciar sesión. | HU1: Iniciar sesión. | HU-01 / S1 | Acceso | Texto literal por cotejar | `ControladorSesionAutenticada`, `VerificarUsuarioActivo`: acceso, cierre, bloqueo e invalidación de sesión | Ampliada; aceptación histórica pendiente | `AuthenticationTest`, `ExpiredRequestPageTest` | Matriz S1 e informe de cierre | Documentar reglas actuales de sesión y roles; revisión humana |
| 2. Gestionar usuarios. | HU2: Gestionar usuarios. | HU-02 / S1 | Usuarios | Texto literal por cotejar | `CrearUsuario`, `ActualizarUsuario`: alta, edición y desactivación sin eliminación | Ampliada; aceptación histórica pendiente | `UserManagementTest` | Matriz S1 | Usuarios independientes de trabajadores; no registro público |
| 3. Gestionar roles y permisos. | HU3: Gestionar roles. | HU-03 / S1 | Roles y permisos | Texto literal por cotejar | `GuardarRol`, `ModificacionSegura`: permisos configurables, un rol por usuario y protección del último administrador | Ampliada | `RbacManagementTest`, `PermissionAuthorizationTest`, `AdministrativeConcurrencyTest` | Correctivo HU-03 y cierre | Distinguir historia «Gestionar roles» del requisito «roles y permisos» |
| 4. Gestionar respaldos. | HU4: Gestionar respaldos. | HU-27 / S5 | Respaldos | Texto literal por cotejar | `RespaldosBaseDatos`, `ClienteRespaldoSql`: archivos privados, manifiesto, integridad y ensayo autorizado | Ampliada; ensayo actual por autorizar | `DatabaseBackupsTest`, `SqlBackupClientConfigurationTest` | Recuperación S5 y renovación del 2 de noviembre | Descargar SQL no demuestra restauración; no restauración automática principal |
| 5. Gestionar productos. | HU5: Gestionar productos. | HU-08; soporte HU-05/06/09/10 / S2 | Catálogo | Texto literal por cotejar | `GuardarProducto`: código único permanente, categoría, unidad, modo de control, duplicados y estado | Ampliada | `ProductManagementTest`, `ProductDuplicateTest`, `ProductHistoryTest` | Etapas S2 y diccionario español | Categorías, ubicaciones e identificación individual son descomposición/ampliación actual |
| 6. Gestionar variantes. | HU6: Gestión de variantes o atributos de productos. | HU-07/08 / S2 | Atributos técnicos | Registro, asociación, modificación, consulta y visualización en remitos/entregas | `GuardarAtributoCatalogo`, `GuardarAtributoCategoria`, `ValidadorAtributosProducto`, revisiones | Parcial | `TechnicalAttributeManagementTest`, `CategoryAttributeManagementTest`, `ProductAttributeValueTest`, `ProductHistoryTest` | Etapas S2.2/S2.4; detalle abajo | No se equiparan atributos con variantes independientes; detalle técnico no aparece en documentos |
| 7. Gestionar trabajadores. | HU7: Gestionar trabajadores. | HU-04 / S1 | Trabajadores | Texto literal por cotejar | `GuardarTrabajador`: identificación, búsqueda, estado e historial de responsabilidades | Ampliada | `WorkerManagementTest`, `LoanManagementTest`, `ReturnManagementTest` | Matriz S1 y S4 | Trabajadores sin obligación de cuenta; obligaciones no se borran al desactivar |
| 8. Gestionar remitos. | HU8: Gestionar remitos de ingreso y egreso. | HU-11/12/13 / S3 | Remitos | Texto literal por cotejar | `GuardarDocumentoInventario`, `ConfirmarDocumentoInventario`: ingreso/egreso, borrador y confirmación única | Ampliada | `InventoryManagementTest`, `InventoryIntegrityTest`, `InventoryConcurrencyTest` | Implementación y despliegue S3 | Referencia externa opcional y advertencias; apertura se documenta por separado |
| 9. Gestionar entregas. | HU9: Gestionar entregas. | HU-18/19 / S4 | Entregas | Texto literal por cotejar | `GuardarEntrega`, `ConfirmarEntrega`: trabajador, retiro manual, saldo y movimiento único | Ampliada | `DeliveryManagementTest`, `WorkerOperationConcurrencyTest` | Implementación S4 | Préstamos/devoluciones no se presentan como requisitos históricos implícitos |
| 10. Visualizar stock. | HU10: Visualizar stock. | HU-14/23 / S3–S4 | Existencias y disponibilidad | Texto literal por cotejar | `ControladorConsultaInventario`, `ConsultaReporteDetallado`: saldo por producto/ubicación, presencia y custodia | Ampliada | `InventoryManagementTest`, `WorkerOperationHttpTest`, `DetailedReportsTest` | Matrices S3–S5 | Distinguir saldo físico de disponibilidad operativa; no sumar unidades incompatibles |
| 11. Visualizar kardex. | HU11: Visualizar kardex. | HU-15/26 / S3–S5 | Historial de movimientos | Fechas, entradas, salidas, cantidades, responsables, filtros y trazabilidad; cotejo literal pendiente | `ConsultaReporteDetallado::movements`, `CatalogoReportes`: registro único y saldo posterior | Pendiente de comprobación histórica; cobertura técnica de campos | `DetailedReportsTest::test_kardex_preserves_opening_balance_before_local_date_filter`, `InventoryIntegrityTest` | Reportes y cierre; detalle abajo | No introducir valoración económica como requisito histórico |
| 12. Reporte de remitos. | HU12: Generar reporte de remitos. | HU-26 / S5 | Reportes → Remitos | Campos/filtros literales por cotejar | `CatalogoReportes`, `ConsultaReporteDetallado`: documento, tipo, contraparte, referencia, líneas, cantidad/unidad, responsables, notas | Pendiente de comprobación histórica | `DetailedReportsTest`, `ControlHttpTest` | Reportes del 31 de octubre y cierre | Exportación CSV e impresión actuales no se atribuyen retroactivamente a HU12 |
| 13. Reporte de entregas. | HU13: Generar reporte de entregas. | HU-26 / S5 | Reportes → Entregas y EPP | Campos/filtros literales por cotejar | Documento, fecha, trabajador/cargo/área documentales, artículo, ubicación, cantidad/unidad, identidades, creadores/confirmadores y observaciones | Pendiente de comprobación histórica | `DetailedReportsTest::test_delivery_details_keep_historical_worker_and_line_units_with_named_filters`, `test_two_document_lines_keep_their_own_quantities_and_count_the_document_once` | Reportes y cierre; detalle abajo | No acreditar firmas de recepción, motivo/clase de entrega o campos no demostrados |

Los nombres de clases principales de esta tabla están en `app/Actions`, `app/Services` o `app/Http/Controllers`; las pruebas están en `tests/Feature` y, cuando corresponde, `Catalog` o `Inventory`. Los resultados realmente ejecutados están en el informe de cierre, sin asignar aprobación humana por el nombre de una prueba.

## Contrastes especiales

### HU6 histórica

| Dimensión indicada por el usuario | Resultado técnico | Evidencia y límite |
|---|---|---|
| Registro | Cubierto en catálogo actual | Definiciones de texto, decimal, booleano y fecha; no acredita un catálogo histórico idéntico de variantes |
| Asociación | Cubierto en catálogo actual | Configuración por categoría y valores por producto, FK compuestas y validación de pertenencia |
| Modificación | Cubierto en catálogo actual | Restricciones de tipo/unidad y revisiones de valores; no se reinterpreta historial |
| Consulta | Cubierto en catálogo actual | Ficha del producto y su historial técnico |
| Visualización en remitos | Parcial | Se muestra código/nombre, cantidad/unidad e identificadores. `inventario/documents/show.blade.php` no despliega los valores técnicos detallados ni una instantánea completa de ellos |
| Visualización en entregas | Parcial | `operaciones-trabajadores/show.blade.php` muestra producto e identidades, no valores técnicos detallados ni una instantánea completa |

La tesis debe describir la reformulación hacia catálogo técnico y reconocer esta diferencia. Si los criterios originales exigen características detalladas e históricas en cada línea, es una brecha funcional que requiere diseño y autorización; no se resuelve cambiando una celda de cobertura.

Decisión del autor, 5 de noviembre de 2026: **mantener el alcance actual y documentar la diferencia**, respuesta expresa a la revisión de esta brecha. No se amplían remitos ni entregas. Esta decisión resuelve el alcance de desarrollo, pero no convierte la cobertura parcial en completa ni sustituye el cotejo y aprobación del documento académico final.

### HU11 histórica

El kardex muestra fecha de registro físico, fuente documental, línea, artículo, ubicación, dirección, cantidad/unidad, saldo posterior y operador. El saldo de apertura del intervalo se calcula antes del filtro de fechas. Se distinguen la fecha comercial y la contabilización, y se conserva el enlace a movimientos originales compensados. La consulta admite fechas, artículo, categoría, ubicación y texto según el catálogo de filtros. No hay una afirmación de valoración monetaria ni de costo promedio. La conformidad literal y los filtros exigidos por la tesis deben cotejarse con el documento original.

### HU12 y HU13 históricas

| Elemento comprobable en código/pruebas | Remitos actuales | Entregas actuales |
|---|---|---|
| Documento y detalle | Código, fecha, tipo, estado, línea, compensación | Código, fecha, estado, línea, compensación |
| Persona o contraparte | Nombre/código de contraparte y referencia externa | Trabajador/código/cargo/área históricos |
| Artículo | Código, nombre, categoría, identificadores | Código, nombre, categoría, identificadores |
| Cantidad | Cantidad propia de la línea y unidad documentada | Cantidad propia de la línea y unidad documentada |
| Ubicación | Código y nombre | Código y nombre |
| Responsables | Preparador, confirmador y fecha de confirmación | Preparador, confirmador y fecha de confirmación |
| Observaciones | Sí | Sí; búsqueda por texto y acceso a dotación semestral |
| Filtros actuales | Fechas, texto, artículo, categoría, ubicación, estado y tipo | Fechas, texto, artículo, categoría, ubicación, trabajador y estado |
| Salida | Pantalla paginada, CSV completo e impresión con límite documentado | Pantalla paginada, CSV completo e impresión con límite documentado |
| No demostrado como criterio histórico | Conformidad literal de todos los campos y filtros | Conformidad literal, firma del receptor y clasificación estructurada de EPP/dotación |

No se confunde un reporte de préstamos con un reporte de entregas. La denominación «dotación semestral» es una búsqueda de observaciones del escenario simulado, no una obligación normativa parametrizada ni una entrega automática.

## Requisitos no funcionales — Tabla 66

| Nº y texto académico | Implementación y prueba técnica | Cobertura propuesta | Evidencia / decisión pendiente |
|---|---|---|---|
| 1. Seguridad. | Sesiones, CSRF, permisos configurables, revocación y autorización separada. `AuthenticationTest`, `PermissionAuthorizationTest`, `RbacManagementTest` | Ampliada técnicamente | No certificación de seguridad; revisión de cuentas y concesiones SQL operativas pendiente |
| 2. Integridad de datos. | FK, CHECK, UNIQUE, transacciones y concurrencia. Suites `*IntegrityTest`, `*ConcurrencyTest` | Ampliada técnicamente | Alcance de escenarios ejecutados y comprobación de conservación; no garantías fuera de esos controles |
| 3. Usabilidad. | Blade/CSS en español, búsqueda parcial y teclado; `SimplifiedInterfaceTest`, `SearchableSelectorsTest`, navegador automático | Parcial | Validación de usuarios reales, móvil, zoom y accesibilidad sin aprobación humana aún |
| 4. Rendimiento. | Medición de consultas y agregación; `ReadQueryPerformanceTest` | Parcial | Faltan umbrales académicos de tiempo y ensayo de carga; menor SQL no garantiza una latencia específica |
| 5. Disponibilidad. | Arranque local documentado, salud y conservación de respaldos | Parcial | No alta disponibilidad ni SLA medido; depende de PC, MariaDB y alimentación |
| 6. Mantenibilidad. | Laravel, acciones/servicios/Policies, migraciones aditivas y pruebas; diccionario y correspondencia de clases | Ampliada técnicamente | Evaluación del mantenedor y límites de personalización del framework |
| 7. Escalabilidad. | Paginación, índices de integridad y filtrado servidor | Parcial | No se ha acreditado escalado distribuido ni concurrencia de gran volumen |
| 8. Compatibilidad. | PHP 8.3.10, Laravel 13.35.0, MariaDB 10.4.32; Blade sin compilación Node | Parcial | Compatibilidad acreditada en este entorno; navegadores/versiones de la defensa deben registrarse |
| 9. Respaldo de información. | SQL externo, SHA-256, manifiesto y ensayo aislado, `DatabaseBackupsTest` | Ampliada técnicamente | Diferenciar pruebas con cliente simulado y restauraciones reales fechadas; ensayo actual requiere autorización específica |
| 10. Trazabilidad. | Documentos inmutables, autoría, revisiones, movimientos únicos y auditoría; suites S3–S5 | Ampliada técnicamente | Completar evidencias humanas y anexos; no atribuir al catálogo técnico una instantánea documental inexistente |

Los criterios numéricos no facilitados no se inventan. Esta matriz no sustituye las definiciones originales completas de los diez requisitos.

## Evolución del backlog y cinco sprints actuales

| Sprint actual | Historias actuales | Entregable verificable |
|---|---|---|
| 1 | HU-01 a HU-04 | Acceso, usuarios, roles/permisos y trabajadores; incluye correctivo HU-03 |
| 2 | HU-05 a HU-10 | Catálogo, atributos, ubicaciones y unidades individualizadas |
| 3 | HU-11 a HU-17 | Recepción/remitos, confirmaciones, existencias, historial y apertura manual/CSV |
| 4 | HU-18 a HU-23 | Entregas, préstamos, devoluciones, responsabilidades y disponibilidad |
| 5 | HU-24 a HU-29 | Ajustes, compensaciones, reportes, respaldos, auditoría y cierre |

La tesis anterior tenía seis sprints. La actualización debe explicar descomposición y reorganización por dependencias del desarrollo actual, sin asignar retroactivamente estas HU a fechas o sprints históricos. El correctivo de roles, la traducción y las mejoras de interfaz son mantenimiento del mismo desarrollo, no un sexto sprint actual.

Ampliaciones actuales que deben identificarse expresamente: préstamos individualizados, devoluciones parciales, regularización excepcional, custodia exclusiva, compensaciones con ciclos, auditoría crítica y restauración ensayada con doble autorización. Tampoco se atribuyen a los requisitos históricos sin contraste la importación de aperturas CSV ni las optimizaciones del panel.

## Decisiones para cerrar la tesis

1. Aprobar esta correspondencia y cotejar los criterios literales originales, especialmente HU6, HU11, HU12 y HU13.
2. Incorporar en la tesis la cobertura parcial de variantes, conforme a la decisión expresa del autor de mantener el alcance actual. No ampliar código ni declarar cobertura completa.
3. Incorporar evidencias reales de levantamiento, priorización y aceptación humana, sin inventar actas o respuestas de trabajadores.
4. Actualizar el documento de tesis y sus anexos con los cinco sprints, las 53 tablas, las clases actuales y las limitaciones declaradas. Esta propuesta no modifica automáticamente el DOCX.

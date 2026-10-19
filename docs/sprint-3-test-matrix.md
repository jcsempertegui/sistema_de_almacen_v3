# Sprint 3 — Matriz de pruebas

**La tabla siguiente define el plan; los resultados ejecutados se registran por separado al final.** Usar exclusivamente `sga_rio_grande_testing`. No deducir aprobación de navegador a partir de pruebas HTTP.

| Caso | Historia/requisito | Evidencia prevista |
|---|---|---|
| INV-01 | HU-11/12 borrador sin efectos físicos, alta/edición/cancelación | Prueba HTTP y SQL |
| INV-02 | HU-13 ingreso, egreso y reintento sin duplicación | Servicio, SQL, movimiento único |
| INV-03 | HU-13 concurrencia mismo documento y retiros competidores | Procesos independientes MariaDB |
| INV-04 | HU-14 rechazo de saldo insuficiente y cantidades inválidas | Validación, CHECK SQL, rollback |
| INV-05 | HU-13 fallo intermedio revierte encabezado, líneas físicas, saldos y movimientos | Fallo inyectado en prueba |
| INV-06 | HU-11/13 pertenencia, conteo y presencia de individuales | Validación y FK compuestas |
| INV-07 | HU-16 apertura única, previa a cualquier movimiento | Reserva y transacción |
| INV-08 | HU-17 CSV existente, duplicados, límites, errores sin escrituras | Parser, revisión, importación |
| INV-09 | Todos: permisos, CSRF y revocación de sesiones | HTTP y acciones directas |
| INV-10 | HU-15 historial y filtros, documentos inmutables | HTTP y consulta SQL |
| INV-11 | Catálogo frente a stock, desactivaciones concurrentes | Acciones y procesos independientes |
| INV-12 | Regresión Sprints 1/2 y aislamiento | Suite completa, JUnit |

Las pruebas automatizadas no constituyen aceptación humana ni verificación visual en navegador. No migrar el entorno de aceptación del Sprint 2 para ejecutar esta matriz.

## Trazabilidad de verificaciones ejecutadas

| Caso | Prueba automatizada concreta | Resultado focalizado real |
|---|---|---|
| INV-01 | `InventoryManagementTest`: `http_draft_edit_confirm_retry_and_queries`, `cancelled_draft_has_no_stock_and_cannot_confirm` | Aprobadas |
| INV-02 | `http_draft_edit_confirm_retry_and_queries`, `issue_insufficient_stock_and_exact_decimal_arithmetic` | Aprobadas |
| INV-03 | `InventoryConcurrencyTest`: reintento, retiros competidores, apertura, unidad repetida | Trece escenarios aprobados en la suite completa con fuentes congeladas |
| INV-04 | Proveedor `invalidQuantities`, `required_header_fields_duplicate_pairs_and_truncated_forms_are_rejected`, `decimal_capacity_overflow_does_not_add_a_movement` | Aprobadas |
| INV-05 | `failure_after_posting_a_line_rolls_back_every_effect_including_opening_marker` | Aprobada, incluye presencia y reserva de apertura |
| INV-06 | `individual_catalog_is_not_stock_and_units_can_exit_and_reenter`, `wrong_unit_product_duplicate_selection_and_count_are_rejected`, integridad SQL | Aprobadas |
| INV-07 | `opening_is_once_per_product_even_after_full_issue_and_prior_movements_block_it`, `opening_spans_multiple_locations_with_one_product_marker` | Aprobadas |
| INV-08 | `InitialStockImportTest`: revisión/borrador, catálogo desconocido, fórmulas, duplicados, estado cambiado, token inválido/caducado/otro operador, identidad individual | Aprobadas; límites máximos de tamaño/filas y variantes BOM/UTF-8 requieren casos de borde específicos adicionales |
| INV-09 | `direct_actions_and_http_respect_permissions_and_revocation`, `csrf_rejects_confirmation_without_a_token`, `new_permissions_preserve_revocations_unknown_roles_and_initial_configuration` | Aprobadas |
| INV-10 | Consultas HTTP con filtros y códigos internos; `confirmed_document_and_movement_models_are_immutable` | Aprobadas |
| INV-11 | `stock_protects_catalog_product_pair_and_individual_identity` y carreras de desactivación en ambos órdenes | Aprobadas en ejecuciones focalizadas |
| INV-12 | Regresión completa de Sprints 1/2 e inventario | 363 pruebas/2.783 aserciones, un timeout en carrera anterior de productos; escenario aislado y grupo de siete repetidos aprobados. Reserva TEST-S3-01; no se declara una ejecución completa única sin errores |

Ejecución focalizada congelada: **33 pruebas/218 aserciones, sin fallos**. Carreras iniciales: **11 pruebas/143 aserciones aprobadas**. La primera regresión completa de desarrollo tuvo dos fallos por modificar vistas/clases mientras el proceso PHP seguía ejecutándose; se repite con código congelado y no se presenta aquella ejecución como aprobada.

Guion humano: [sprint-3-acceptance.md](sprint-3-acceptance.md), íntegramente pendiente. No se crearon datos de aceptación ni se ejecutó navegador para el Sprint 3.

Resultado final: los **44 casos nuevos de inventario pasaron** en la suite completa. El escenario `ProductConcurrencyTest/stale edit` superó su timeout de 120 segundos. Pasó al repetirlo (1 prueba/15 aserciones) y al repetir el grupo completo (7 pruebas/99 aserciones), sin cambios de código o límites. Causa no determinada; conservar la reserva de estabilidad. [JSON y JUnit](evidence/sprint-3-implementation.json) registran el error y los resultados reales por separado.

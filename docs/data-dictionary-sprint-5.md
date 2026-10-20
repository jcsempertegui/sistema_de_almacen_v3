# Diccionario de datos — Sprint 5

Implementación publicada y migraciones aplicadas a la principal con autorización, lote 15. Véase [despliegue](sprint-5-deployment.md). Se conservan los diccionarios S3/S4 y las 35 migraciones publicadas. Once tablas nuevas; ocho migraciones aditivas, incluidas las restricciones de pertenencia reforzadas durante la revisión. IDs BIGINT UNSIGNED, InnoDB, FK RESTRICT, cantidades DECIMAL(18,6).

| Entidad | Campos principales | Integridad |
|---|---|---|
| inventory_corrections | id, uuid, kind, status, document_date, reason, source_kind/source_id, lock_version, autores y executed_at | UUID UNIQUE; adjustment/compensation; estados validados; fuente requerida solo para compensar; metadatos de ejecución inseparables |
| inventory_correction_lines | corrección/clase, producto, ubicación, dirección/cantidad, snapshot de unidad/modo, saldo y versión observados, cantidad contada, movimiento/dirección original | FK cabecera/clase, asociación producto–ubicación; diferencia exacta en ajustes; FK original/producto/ubicación/dirección/cantidad y dirección inversa en compensaciones |
| inventory_correction_line_units | línea, identidad y producto | PK línea/identidad; FK compuestas a línea/producto e identidad/producto |
| document_compensations | corrección ejecutada y una fuente original entre remito, entrega, préstamo, devolución o ajuste | CHECK exactamente una fuente; UNIQUE por documento original y corrección; no autorreferencia. Solo se registra al ejecutar |
| correction_authorizations | corrección, estado, huella, versión, solicitante/autorizador, motivo, solicitud/aprobación/vencimiento/utilización | solicitante distinto de autorizador; metadatos obligatorios para aprobación/uso; uso único por transición controlada |
| correction_authorization_events | autorización, actor, acción, payload y fecha | historial de requested/approved/rejected/revoked/used; prueba de usuario/rol/versiones en aprobación |
| loan_custody_cycles | ítem/identidad/producto, movimiento inicial de préstamo o línea de corrección, ciclo anterior, devolución y movimiento invertidos | fuente de apertura exclusiva; FK compuestas de identidad; un sucesor por ciclo; reapertura ligada al cierre y devolución originales |
| loan_cycle_closures | ciclo PK, ítem/identidad/producto, devolución o corrección, autorización excepcional y fecha | un cierre por ciclo; una sola fuente física; FK a movimientos, pertenencia al ítem y autorización de la devolución exacta |
| backup_runs | uuid, estado, solicitante, nombre privado, tamaño, SHA-256, manifiesto, fallo y fechas | nombre/UUID UNIQUE; generado/verificado exige archivo, hash y metadatos; sin SQL ni secretos dentro del manifiesto |
| backup_restore_rehearsals | respaldo, destino nuevo, estado, solicitante/autorizador/ejecutor, motivo, vigencia y resultado | destino UNIQUE y patrón cerrado sga_rio_grande_rehearsal_ + 16 hex; separación de personas; sin destinos principales |
| critical_operation_events | actor/nombre histórico, acción, corrección o respaldo o ensayo, payload y fecha | exactamente un objetivo mediante CHECK y FK; historial sin rutas de edición/borrado |

## Ledger y posiciones existentes

inventory_movements añade correction_line_id UNIQUE, correction_kind y compensated_movement_id UNIQUE. La fuente física sigue siendo exactamente una de cinco: document_line_id, delivery_line_id, loan_item_id, return_item_id o correction_line_id. La referencia al movimiento compensado es trazabilidad, no una segunda fuente ni un segundo movimiento físico.

FK compuestas enlazan corrección, clase, producto, ubicación, dirección y cantidad. Otra FK enlaza el movimiento original especificado por la misma línea. El original conserva todas sus columnas; una inversión conserva cantidad/producto/ubicación y usa dirección contraria. La unicidad del original compensado también protege frente a solicitudes competidoras.

individual_unit_positions añade last_correction_line_id y last_correction_direction. La última fuente sigue siendo exclusiva. FK verifican identidad/producto, dirección y ubicación de ingreso; CHECK exige presencia para ingreso y ausencia para egreso. Todas las acciones S3/S4 limpian ambas referencias nuevas. individual_units.location_id continúa siendo la ubicación inicial histórica.

## Ciclos de custodia

El backfill inicial deriva ciclos de movimientos reales de préstamo y cierres originales; no reescribe loan_item_settlements. tool_return_items incorpora custody_cycle_id y las devoluciones quedan fijadas al ciclo preparado. individual_unit_custodies incorpora current_cycle_id UNIQUE y conserva el ítem original para compatibilidad. PK por herramienta, FK, mutex y actualización condicional mantienen una sola custodia vigente.

Una devolución compensada conserva su cierre antiguo; la salida compensatoria abre un ciclo sucesor del mismo ítem. Una devolución posterior cierra ese ciclo sin sustituir el cierre original. Los estados pendientes se consultan desde la custodia actual. No se cierra una obligación mediante un ajuste sin recepción física.

Las relaciones entre filas y las condiciones de permisos/estado se revalidan transaccionalmente. Los CHECK no reemplazan esas comprobaciones ni impiden cambios hechos por un administrador SQL fuera de la aplicación.

## Migraciones aplicadas en la principal, lote 15

1. 2026_10_20_000001_create_inventory_corrections.php
2. 2026_10_20_000002_extend_correction_movement_sources.php
3. 2026_10_20_000003_create_loan_custody_cycles.php
4. 2026_10_20_000004_create_backup_and_critical_audit_tables.php
5. 2026_10_20_000005_add_control_permissions.php
6. 2026_10_20_000006_harden_correction_and_cycle_membership.php
7. 2026_10_20_000007_bind_reopened_cycles_to_return_movements.php
8. 2026_10_20_000008_bind_correction_position_direction.php

Los métodos down rechazan retiradas destructivas. La aplicación se completó con respaldo nuevo, restauración de ensayo y autorización específica; no repetir las migraciones ni usar rollback, fresh, reset o wipe.

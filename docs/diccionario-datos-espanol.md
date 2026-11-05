# Diccionario de datos del SGA en español

Esquema implementado y aplicado con autorización a la principal el 2 de noviembre de 2026, después de las pruebas en `sga_rio_grande_testing` y del ensayo sobre una copia histórica aislada. Los nombres anteriores se conservan como equivalencias históricas de los cinco sprints. Consulta el [informe de renovación y despliegue](renovacion-espanol-2026-11-02.md).

## Tablas y columnas

### registros_auditoria_administrativa

Nombre anterior: `administrative_audit_logs`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `actor_id` | `actor_id` |
| `nombre_actor` | `actor_name` |
| `rol_id` | `role_id` |
| `usuario_id` | `user_id` |
| `accion` | `action` |
| `origen` | `origin` |
| `direccion_ip` | `ip_address` |
| `datos_anteriores` | `before_data` |
| `datos_posteriores` | `after_data` |
| `fecha_creacion` | `created_at` |

### atributos

Nombre anterior: `attributes`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `codigo` | `code` |
| `nombre` | `name` |
| `tipo_dato` | `data_type` |
| `unidad_medida_id` | `measurement_unit_id` |
| `texto_ayuda` | `help_text` |
| `activo` | `is_active` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### ensayos_restauracion_respaldo

Nombre anterior: `backup_restore_rehearsals`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `ejecucion_respaldo_id` | `backup_run_id` |
| `estado` | `status` |
| `base_destino` | `target_database` |
| `solicitado_por` | `requested_by` |
| `autorizado_por` | `approved_by` |
| `ejecutado_por` | `executed_by` |
| `motivo` | `reason` |
| `fecha_autorizacion` | `approved_at` |
| `fecha_vencimiento` | `expires_at` |
| `resultado` | `result` |
| `fecha_finalizacion` | `completed_at` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### ejecuciones_respaldo

Nombre anterior: `backup_runs`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `uuid` | `uuid` |
| `estado` | `status` |
| `solicitado_por` | `requested_by` |
| `nombre_archivo` | `file_name` |
| `tamano_bytes` | `size_bytes` |
| `sha256` | `sha256` |
| `manifiesto` | `manifest` |
| `fallo` | `failure` |
| `fecha_inicio` | `started_at` |
| `fecha_finalizacion` | `completed_at` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### cache

Nombre anterior: `cache`.

| Columna en español | Nombre anterior |
| --- | --- |
| `key` | `key` |
| `value` | `value` |
| `expiration` | `expiration` |

### bloqueos_cache

Nombre anterior: `cache_locks`.

| Columna en español | Nombre anterior |
| --- | --- |
| `key` | `key` |
| `owner` | `owner` |
| `expiration` | `expiration` |

### categorias

Nombre anterior: `categories`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `codigo` | `code` |
| `nombre` | `name` |
| `descripcion` | `description` |
| `activo` | `is_active` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### atributos_categoria

Nombre anterior: `category_attributes`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `categoria_id` | `category_id` |
| `atributo_id` | `attribute_id` |
| `obligatorio` | `is_required` |
| `identificacion_tecnica` | `is_identity` |
| `orden_presentacion` | `display_order` |
| `valor_minimo` | `min_value` |
| `valor_maximo` | `max_value` |
| `activo` | `is_active` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### autorizaciones_correccion

Nombre anterior: `correction_authorizations`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `correccion_id` | `correction_id` |
| `estado` | `status` |
| `huella` | `fingerprint` |
| `version_documento` | `document_version` |
| `solicitado_por` | `requested_by` |
| `autorizado_por` | `approved_by` |
| `motivo` | `reason` |
| `fecha_solicitud` | `requested_at` |
| `fecha_autorizacion` | `approved_at` |
| `fecha_vencimiento` | `expires_at` |
| `fecha_utilizacion` | `used_at` |

### eventos_autorizacion_correccion

Nombre anterior: `correction_authorization_events`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `autorizacion_id` | `authorization_id` |
| `actor_id` | `actor_id` |
| `accion` | `action` |
| `detalle` | `payload` |
| `fecha_creacion` | `created_at` |

### eventos_operacion_critica

Nombre anterior: `critical_operation_events`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `actor_id` | `actor_id` |
| `nombre_actor` | `actor_name` |
| `accion` | `action` |
| `correccion_id` | `correction_id` |
| `ejecucion_respaldo_id` | `backup_run_id` |
| `ensayo_id` | `rehearsal_id` |
| `detalle` | `payload` |
| `fecha_creacion` | `created_at` |

### compensaciones_documento

Nombre anterior: `document_compensations`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `correccion_id` | `correction_id` |
| `documento_inventario_id` | `inventory_document_id` |
| `entrega_id` | `delivery_id` |
| `prestamo_id` | `loan_id` |
| `devolucion_id` | `return_id` |
| `correccion_destino_id` | `target_correction_id` |
| `fecha_creacion` | `created_at` |

### trabajos_fallidos

Nombre anterior: `failed_jobs`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `uuid` | `uuid` |
| `connection` | `connection` |
| `queue` | `queue` |
| `payload` | `payload` |
| `exception` | `exception` |
| `failed_at` | `failed_at` |

### unidades_individuales

Nombre anterior: `individual_units`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `identificador` | `identifier` |
| `producto_id` | `product_id` |
| `numero_serie` | `serial_number` |
| `ubicacion_id` | `location_id` |
| `observaciones` | `notes` |
| `activo` | `is_active` |
| `version_bloqueo` | `lock_version` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### custodias_unidad_individual

Nombre anterior: `individual_unit_custodies`.

| Columna en español | Nombre anterior |
| --- | --- |
| `unidad_individual_id` | `individual_unit_id` |
| `producto_id` | `product_id` |
| `item_prestamo_actual_id` | `current_loan_item_id` |
| `version_bloqueo` | `lock_version` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |
| `ciclo_actual_id` | `current_cycle_id` |

### posiciones_unidad_individual

Nombre anterior: `individual_unit_positions`.

| Columna en español | Nombre anterior |
| --- | --- |
| `unidad_individual_id` | `individual_unit_id` |
| `producto_id` | `product_id` |
| `presente` | `is_present` |
| `ubicacion_id` | `location_id` |
| `ultima_linea_documento_id` | `last_document_line_id` |
| `version_bloqueo` | `lock_version` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |
| `ultima_linea_entrega_id` | `last_delivery_line_id` |
| `ultimo_item_prestamo_id` | `last_loan_item_id` |
| `ultimo_item_devolucion_id` | `last_return_item_id` |
| `ultima_linea_correccion_id` | `last_correction_line_id` |
| `direccion_ultima_correccion` | `last_correction_direction` |

### productos_existencia_inicial

Nombre anterior: `initial_stock_products`.

| Columna en español | Nombre anterior |
| --- | --- |
| `producto_id` | `product_id` |
| `documento_inventario_id` | `inventory_document_id` |
| `tipo` | `kind` |

### correcciones_inventario

Nombre anterior: `inventory_corrections`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `uuid` | `uuid` |
| `tipo` | `kind` |
| `estado` | `status` |
| `fecha_documento` | `document_date` |
| `motivo` | `reason` |
| `tipo_origen` | `source_kind` |
| `origen_id` | `source_id` |
| `version_bloqueo` | `lock_version` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `ejecutado_por` | `executed_by` |
| `fecha_ejecucion` | `executed_at` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### lineas_correccion_inventario

Nombre anterior: `inventory_correction_lines`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `correccion_id` | `correction_id` |
| `tipo` | `kind` |
| `producto_id` | `product_id` |
| `ubicacion_id` | `location_id` |
| `direccion` | `direction` |
| `cantidad` | `quantity` |
| `unidad_medida_id` | `measurement_unit_id` |
| `simbolo_unidad` | `unit_symbol` |
| `decimales` | `decimal_places` |
| `modo_control` | `control_mode` |
| `cantidad_observada` | `observed_quantity` |
| `version_observada` | `observed_version` |
| `cantidad_contada` | `counted_quantity` |
| `movimiento_original_id` | `original_movement_id` |
| `direccion_original` | `original_direction` |

### unidades_linea_correccion

Nombre anterior: `inventory_correction_line_units`.

| Columna en español | Nombre anterior |
| --- | --- |
| `linea_correccion_id` | `correction_line_id` |
| `unidad_individual_id` | `individual_unit_id` |
| `producto_id` | `product_id` |

### documentos_inventario

Nombre anterior: `inventory_documents`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `uuid` | `uuid` |
| `tipo` | `kind` |
| `estado` | `status` |
| `fecha_documento` | `document_date` |
| `fecha_corte` | `cutoff_date` |
| `identificacion_contraparte` | `counterparty_id` |
| `nombre_contraparte` | `counterparty_name` |
| `referencia_externa` | `external_reference` |
| `observaciones` | `notes` |
| `origen` | `source` |
| `sha256_origen` | `source_sha256` |
| `version_bloqueo` | `lock_version` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `confirmado_por` | `confirmed_by` |
| `cancelado_por` | `cancelled_by` |
| `fecha_confirmacion` | `confirmed_at` |
| `fecha_cancelacion` | `cancelled_at` |
| `motivo_cancelacion` | `cancellation_reason` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### lineas_documento_inventario

Nombre anterior: `inventory_document_lines`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `documento_id` | `document_id` |
| `numero_linea` | `line_number` |
| `producto_id` | `product_id` |
| `ubicacion_id` | `location_id` |
| `cantidad` | `quantity` |
| `unidad_medida_id` | `measurement_unit_id` |
| `simbolo_unidad` | `unit_symbol` |
| `decimales` | `decimal_places` |
| `modo_control` | `control_mode` |

### unidades_linea_documento

Nombre anterior: `inventory_document_line_units`.

| Columna en español | Nombre anterior |
| --- | --- |
| `linea_documento_id` | `document_line_id` |
| `documento_id` | `document_id` |
| `producto_id` | `product_id` |
| `unidad_individual_id` | `individual_unit_id` |

### movimientos_inventario

Nombre anterior: `inventory_movements`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `linea_documento_id` | `document_line_id` |
| `producto_id` | `product_id` |
| `ubicacion_id` | `location_id` |
| `direccion` | `direction` |
| `cantidad` | `quantity` |
| `creado_por` | `created_by` |
| `fecha_registro` | `posted_at` |
| `linea_entrega_id` | `delivery_line_id` |
| `item_prestamo_id` | `loan_item_id` |
| `item_devolucion_id` | `return_item_id` |
| `linea_correccion_id` | `correction_line_id` |
| `tipo_correccion` | `correction_kind` |
| `movimiento_compensado_id` | `compensated_movement_id` |

### trabajos

Nombre anterior: `jobs`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `queue` | `queue` |
| `payload` | `payload` |
| `attempts` | `attempts` |
| `reserved_at` | `reserved_at` |
| `available_at` | `available_at` |
| `created_at` | `created_at` |

### lotes_trabajos

Nombre anterior: `job_batches`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `name` | `name` |
| `total_jobs` | `total_jobs` |
| `pending_jobs` | `pending_jobs` |
| `failed_jobs` | `failed_jobs` |
| `failed_job_ids` | `failed_job_ids` |
| `options` | `options` |
| `cancelled_at` | `cancelled_at` |
| `created_at` | `created_at` |
| `finished_at` | `finished_at` |

### ciclos_custodia_prestamo

Nombre anterior: `loan_custody_cycles`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `item_prestamo_id` | `loan_item_id` |
| `unidad_individual_id` | `individual_unit_id` |
| `producto_id` | `product_id` |
| `item_prestamo_apertura_id` | `opening_loan_item_id` |
| `linea_correccion_apertura_id` | `opening_correction_line_id` |
| `ciclo_anterior_id` | `previous_cycle_id` |
| `fecha_creacion` | `created_at` |
| `movimiento_devolucion_revertido_id` | `reversed_return_movement_id` |
| `item_devolucion_origen_id` | `source_return_item_id` |

### cierres_ciclo_prestamo

Nombre anterior: `loan_cycle_closures`.

| Columna en español | Nombre anterior |
| --- | --- |
| `ciclo_id` | `cycle_id` |
| `item_devolucion_id` | `return_item_id` |
| `linea_correccion_id` | `correction_line_id` |
| `autorizacion_regularizacion_id` | `regularization_authorization_id` |
| `fecha_creacion` | `created_at` |
| `item_prestamo_id` | `loan_item_id` |
| `unidad_individual_id` | `individual_unit_id` |
| `producto_id` | `product_id` |

### cierres_item_prestamo

Nombre anterior: `loan_item_settlements`.

| Columna en español | Nombre anterior |
| --- | --- |
| `item_prestamo_id` | `loan_item_id` |
| `item_devolucion_id` | `return_item_id` |
| `unidad_individual_id` | `individual_unit_id` |
| `autorizacion_regularizacion_id` | `regularization_authorization_id` |
| `fecha_creacion` | `created_at` |

### ubicaciones

Nombre anterior: `locations`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `codigo` | `code` |
| `nombre` | `name` |
| `tipo` | `type` |
| `padre_id` | `parent_id` |
| `permite_almacenar` | `is_storable` |
| `descripcion` | `description` |
| `activo` | `is_active` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### entregas_materiales

Nombre anterior: `material_deliveries`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `uuid` | `uuid` |
| `trabajador_id` | `worker_id` |
| `fecha_documento` | `document_date` |
| `estado` | `status` |
| `datos_historicos_trabajador` | `worker_snapshot` |
| `observaciones` | `notes` |
| `version_bloqueo` | `lock_version` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `confirmado_por` | `confirmed_by` |
| `cancelado_por` | `cancelled_by` |
| `fecha_confirmacion` | `confirmed_at` |
| `fecha_cancelacion` | `cancelled_at` |
| `motivo_cancelacion` | `cancellation_reason` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### lineas_entrega_material

Nombre anterior: `material_delivery_lines`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `entrega_id` | `delivery_id` |
| `numero_linea` | `line_number` |
| `producto_id` | `product_id` |
| `ubicacion_id` | `location_id` |
| `cantidad` | `quantity` |
| `unidad_medida_id` | `measurement_unit_id` |
| `simbolo_unidad` | `unit_symbol` |
| `decimales` | `decimal_places` |
| `modo_control` | `control_mode` |

### unidades_linea_entrega

Nombre anterior: `material_delivery_line_units`.

| Columna en español | Nombre anterior |
| --- | --- |
| `linea_entrega_id` | `delivery_line_id` |
| `entrega_id` | `delivery_id` |
| `producto_id` | `product_id` |
| `unidad_individual_id` | `individual_unit_id` |

### unidades_medida

Nombre anterior: `measurement_units`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `codigo` | `code` |
| `nombre` | `name` |
| `simbolo` | `symbol` |
| `dimension` | `dimension` |
| `decimales` | `decimal_places` |
| `activo` | `is_active` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### migrations

Nombre anterior: `migrations`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `migration` | `migration` |
| `batch` | `batch` |

### tokens_restauracion_contrasena

Nombre anterior: `password_reset_tokens`.

| Columna en español | Nombre anterior |
| --- | --- |
| `email` | `email` |
| `token` | `token` |
| `created_at` | `created_at` |

### permisos

Nombre anterior: `permissions`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `codigo` | `code` |
| `nombre` | `name` |
| `descripcion` | `description` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### permiso_rol

Nombre anterior: `permission_role`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `rol_id` | `role_id` |
| `permiso_id` | `permission_id` |
| `activo` | `is_active` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### productos

Nombre anterior: `products`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `codigo_interno` | `internal_code` |
| `nombre` | `name` |
| `descripcion` | `description` |
| `categoria_id` | `category_id` |
| `unidad_medida_id` | `measurement_unit_id` |
| `marca` | `brand` |
| `referencia_fabricante` | `manufacturer_reference` |
| `modo_control` | `control_mode` |
| `prestable` | `is_loanable` |
| `activo` | `is_active` |
| `version_bloqueo` | `lock_version` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### valores_atributo_producto

Nombre anterior: `product_attribute_values`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `producto_id` | `product_id` |
| `categoria_id` | `category_id` |
| `atributo_categoria_id` | `category_attribute_id` |
| `valor_texto` | `value_text` |
| `valor_decimal` | `value_decimal` |
| `valor_booleano` | `value_boolean` |
| `valor_fecha` | `value_date` |
| `activo` | `is_active` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### revisiones_valor_atributo

Nombre anterior: `product_attribute_value_revisions`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `valor_atributo_producto_id` | `product_attribute_value_id` |
| `revision` | `revision` |
| `valor_texto` | `value_text` |
| `valor_decimal` | `value_decimal` |
| `valor_booleano` | `value_boolean` |
| `valor_fecha` | `value_date` |
| `activo` | `is_active` |
| `creado_por` | `created_by` |
| `fecha_creacion` | `created_at` |

### ubicaciones_producto

Nombre anterior: `product_locations`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `producto_id` | `product_id` |
| `ubicacion_id` | `location_id` |
| `activo` | `is_active` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### autorizaciones_regularizacion_devolucion

Nombre anterior: `return_regularization_authorizations`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `item_devolucion_id` | `return_item_id` |
| `estado` | `status` |
| `motivo` | `reason` |
| `huella` | `fingerprint` |
| `datos_historicos_referencia` | `reference_snapshot` |
| `solicitado_por` | `requested_by` |
| `fecha_solicitud` | `requested_at` |
| `autorizado_por` | `approved_by` |
| `fecha_autorizacion` | `approved_at` |
| `fecha_vencimiento` | `expires_at` |
| `fecha_utilizacion` | `used_at` |

### eventos_regularizacion_devolucion

Nombre anterior: `return_regularization_events`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `autorizacion_id` | `authorization_id` |
| `actor_id` | `actor_id` |
| `accion` | `action` |
| `detalle` | `payload` |
| `fecha_creacion` | `created_at` |

### roles

Nombre anterior: `roles`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `codigo` | `code` |
| `nombre` | `name` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |
| `descripcion` | `description` |
| `activo` | `is_active` |
| `version_permisos` | `permissions_version` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |

### sesiones

Nombre anterior: `sessions`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `user_id` | `user_id` |
| `ip_address` | `ip_address` |
| `user_agent` | `user_agent` |
| `payload` | `payload` |
| `last_activity` | `last_activity` |

### existencias_ubicacion

Nombre anterior: `stock_positions`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `producto_id` | `product_id` |
| `ubicacion_id` | `location_id` |
| `cantidad` | `quantity` |
| `version_bloqueo` | `lock_version` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### prestamos_herramientas

Nombre anterior: `tool_loans`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `uuid` | `uuid` |
| `trabajador_id` | `worker_id` |
| `fecha_limite` | `due_date` |
| `fecha_documento` | `document_date` |
| `estado` | `status` |
| `datos_historicos_trabajador` | `worker_snapshot` |
| `observaciones` | `notes` |
| `version_bloqueo` | `lock_version` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `confirmado_por` | `confirmed_by` |
| `cancelado_por` | `cancelled_by` |
| `fecha_confirmacion` | `confirmed_at` |
| `fecha_cancelacion` | `cancelled_at` |
| `motivo_cancelacion` | `cancellation_reason` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### items_prestamo_herramienta

Nombre anterior: `tool_loan_items`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `prestamo_id` | `loan_id` |
| `unidad_individual_id` | `individual_unit_id` |
| `producto_id` | `product_id` |
| `ubicacion_id` | `location_id` |
| `unidad_medida_id` | `measurement_unit_id` |
| `simbolo_unidad` | `unit_symbol` |

### devoluciones_herramientas

Nombre anterior: `tool_returns`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `uuid` | `uuid` |
| `trabajador_id` | `worker_id` |
| `prestamo_id` | `loan_id` |
| `fecha_documento` | `document_date` |
| `estado` | `status` |
| `datos_historicos_trabajador` | `worker_snapshot` |
| `observaciones` | `notes` |
| `version_bloqueo` | `lock_version` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `confirmado_por` | `confirmed_by` |
| `cancelado_por` | `cancelled_by` |
| `fecha_confirmacion` | `confirmed_at` |
| `fecha_cancelacion` | `cancelled_at` |
| `motivo_cancelacion` | `cancellation_reason` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

### items_devolucion_herramienta

Nombre anterior: `tool_return_items`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `devolucion_id` | `return_id` |
| `prestamo_id` | `loan_id` |
| `item_prestamo_id` | `loan_item_id` |
| `unidad_individual_id` | `individual_unit_id` |
| `producto_id` | `product_id` |
| `ubicacion_id` | `location_id` |
| `ciclo_custodia_id` | `custody_cycle_id` |

### usuarios

Nombre anterior: `users`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `nombre` | `name` |
| `correo` | `email` |
| `fecha_verificacion_correo` | `email_verified_at` |
| `contrasena` | `password` |
| `token_recuerdo` | `remember_token` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |
| `activo` | `is_active` |
| `version_sesion` | `session_version` |
| `rol_id` | `role_id` |

### trabajadores

Nombre anterior: `workers`.

| Columna en español | Nombre anterior |
| --- | --- |
| `id` | `id` |
| `codigo` | `codigo` |
| `nombres` | `nombres` |
| `apellidos` | `apellidos` |
| `documento_identidad` | `documento_identidad` |
| `cargo` | `cargo` |
| `area` | `area` |
| `activo` | `is_active` |
| `creado_por` | `created_by` |
| `modificado_por` | `updated_by` |
| `fecha_creacion` | `created_at` |
| `fecha_actualizacion` | `updated_at` |

## Contratos técnicos conservados

- `migrations` y sus columnas son el registro de ejecución de Laravel. Las 43 migraciones anteriores y sus nombres permanecen intactos.
- Las columnas internas de sesiones, caché, colas y tokens de restablecimiento se conservan porque las leen directamente los controladores de almacenamiento de Laravel. Sus tablas sí se configuran con los nombres traducidos, excepto `cache` y `migrations`.
- `id`, `uuid`, `sha256`, los nombres de tipos SQL y los métodos obligatorios de PHP/Laravel conservan su forma técnica.
- Los nombres de restricciones históricas se conservan como identificadores de compatibilidad; sus referencias apuntan a las tablas y columnas nuevas.
- Los códigos persistidos de permisos y estados son contratos históricos estables. Se conservan para no conceder permisos ni reinterpretar operaciones ya registradas. Sus etiquetas están en español.

## Códigos históricos y significado

| Código conservado | Significado |
| --- | --- |
| `draft` | Borrador |
| `confirmed` | Confirmado |
| `cancelled` | Cancelado |
| `quantity` | Control por cantidad |
| `individual` | Control individual |
| `receipt` / `issue` / `opening` | Ingreso / egreso / existencias iniciales |
| `in` / `out` | Entrada / salida física |
| `adjustment` / `compensation` | Ajuste / compensación |

No se añade un sexto sprint ni se atribuyen retrospectivamente estos nombres a las evidencias publicadas.

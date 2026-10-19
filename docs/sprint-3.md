# Sprint 3 — Recepción, remitos y existencias

## Alcance autorizado

Continuación autorizada por el usuario el 19 de octubre de 2026 después de revisar el diseño. Rama `feature/sprint-3-inventory`. La autorización permite implementar y probar; no permite aplicar migraciones principales, publicar cambios ni iniciar entregas, préstamos o devoluciones.

Posteriormente el usuario autorizó actualizar el repositorio. Esa autorización de commit, integración y push se registra en [sprint-3-publication.md](sprint-3-publication.md); no autoriza migraciones principales ni equivale a aceptación funcional del Sprint 3.

| Historia | Objetivo | Criterio principal |
|---|---|---|
| HU-11 | Recibir materiales | Remito de ingreso confirmado registra recepción física |
| HU-12 | Gestionar remitos | Borradores de ingreso/egreso editables; confirmados inmutables |
| HU-13 | Confirmar documentos | Un movimiento por línea; confirmación atómica e idempotente |
| HU-14 | Consultar existencias | Saldo no negativo por producto y ubicación |
| HU-15 | Consultar trazabilidad | Documento, ubicación, operador y unidades identificables |
| HU-16 | Registrar apertura manual | Una apertura por producto; sin movimientos anteriores |
| HU-17 | Importar apertura | CSV exportado desde Excel, exclusivamente catálogo existente |

La numeración HU-11 a HU-17 corresponde a esta planificación; no sustituye historias históricas. Un solo almacén: Río Grande. Las recepciones utilizan remitos de ingreso, sin tabla de recepciones redundante. Las entregas del Sprint 4 son independientes.

## Implementación por etapas

1. Cierre documental del Sprint 2, esquema aditivo y permisos.
2. Borradores y validación de líneas, cantidades y unidades.
3. Confirmación, saldos, trazabilidad y protecciones del catálogo.
4. Apertura manual e importación CSV con revisión previa.
5. Pruebas aisladas, regresión y evidencia real.

## Invariantes

- Crear productos, identidades individuales o borradores no genera saldos.
- Cantidades `DECIMAL(18,6)`, nunca `FLOAT`; precisión de la unidad. Individuales: dimensión de conteo y precisión cero.
- El retiro selecciona explícitamente la ubicación. No hay transferencias implícitas.
- Confirmación mediante `SecurityMutation`: permiso vigente, mutex compartido con administración/catálogo, documento bloqueado y versión optimista. Un reintento de documento confirmado devuelve el mismo documento sin volver a afectar saldos.
- Cada línea genera un movimiento único. Movimientos y documentos confirmados no tienen rutas de edición o borrado. La protección no pretende impedir cambios mediante una cuenta SQL con privilegios de escritura.
- Presencia física individual separada de `individual_units.location_id`, que sigue siendo la ubicación inicial histórica del catálogo.
- Cancelación solo de borradores con motivo y autoría. Anulaciones de confirmados y compensaciones requieren un diseño posterior con autorización administrativa.
- Apertura reservada permanentemente por producto al confirmar; bloquear si ya hubo cualquier movimiento, incluso con saldo cero.
- CSV con separador `;`, decimal con punto, límites de tamaño/filas, sin fórmulas ni creación o modificación de productos. No se requiere un paquete XLSX.

## Seguridad y despliegue

Permisos configurables; no comprobar nombres de roles en operaciones. Los permisos nuevos se conceden inicialmente a roles reconocidos sin restaurar vínculos revocados. Apertura confirmable inicialmente por Administrador; preparación también por Encargado; Consulta/Auditor solo lectura.

Todas las pruebas de escritura se ejecutarán únicamente en `sga_rio_grande_testing`, tras verificar configuración y `SELECT DATABASE()`. No modificar `.env`, dependencias o migraciones antiguas. La principal permanece sin nuevas migraciones hasta autorización específica. No migrar ni recrear el entorno de aceptación del Sprint 2.

## Decisiones funcionales

Respuestas del usuario incorporadas: referencia externa opcional, coincidencias advertidas sin bloqueo; reingreso externo permitido para unidades que egresaron; fecha de corte obligatoria por lote y editable en borrador. La contraparte sigue siendo obligatoria para remitos. Los resultados ejecutados se registran separadamente de los casos planificados.

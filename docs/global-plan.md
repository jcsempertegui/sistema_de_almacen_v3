# Planificación global de cinco sprints

Fecha de revisión: 12 de octubre de 2026, America/La_Paz.

Estado: propuesta para aprobación. No se encontró un backlog global anterior documentado en el repositorio; la distribución debe contrastarse con la planificación académica existente, si la hubiera. Publicar este documento no autoriza implementar las funcionalidades pendientes.

## Distribución propuesta

| Sprint | Objetivo | Alcance | Estado |
|---|---|---|---|
| 1 | Acceso y personas | Autenticación, usuarios, trabajadores y cierre del correctivo de roles/permisos configurables. | Implementación disponible; HU-03 parcialmente cumplida y aceptación visual pendiente. |
| 2 | Catálogo y organización física | Categorías, unidades de medida, atributos por categoría, productos, ubicaciones y unidades individualizadas. | Especificación propuesta; sin implementar. |
| 3 | Recepción y existencias | Recepción, remitos y existencias por ubicación, con movimientos únicos por documento confirmado. | Planificación general; sin especificación detallada ni implementación. |
| 4 | Entregas y préstamos | Entregas, préstamos y devoluciones; retiro por ubicaciones seleccionadas manualmente. | Planificación general; sin implementar. |
| 5 | Control y cierre | Ajustes/anulaciones autorizadas, reportes, auditoría, regresión y documentación académica final. | Planificación general; sin implementar. |

El correctivo de HU-03 se incluye en el cierre del Sprint 1, sin crear un sexto sprint. No se asignan fechas o estimaciones de esfuerzo sin revisar disponibilidad y velocidad del equipo.

## Dependencias

```mermaid
flowchart LR
    S1["Sprint 1: acceso y permisos"] --> S2["Sprint 2: catálogo y ubicaciones"]
    S2 --> S3["Sprint 3: recepción y existencias"]
    S3 --> S4["Sprint 4: entregas y préstamos"]
    S4 --> S5["Sprint 5: control y cierre"]
```

Las pruebas y la documentación se completan dentro de cada sprint. El Sprint 5 integra la regresión y el cierre; no posterga la validación de los anteriores.

## Reglas transversales

- Un solo almacén en Río Grande; no crear otra tabla de almacenes.
- Productos registrados manualmente; Excel no crea ni modifica productos.
- Remitos y entregas independientes.
- Movimientos únicos por documento confirmado e idempotencia en futuras confirmaciones.
- Existencias nunca negativas; comprobarlas en el flujo futuro de movimientos y retiros.
- Encargado selecciona las ubicaciones de retiro.
- Herramientas prestables con identificación individual única.
- Trabajadores independientes de las cuentas de usuario.
- Registros con historial conservados; no reutilizar identificadores de registros desactivados.
- Ajustes y anulaciones críticas requieren autorización.
- Validación y autorización siempre en el servidor.

## Documentación académica

En cada sprint se entregarán historias y criterios de aceptación, tareas y dependencias, matriz de pruebas, resultados obtenidos, capturas de aceptación, decisiones técnicas y retrospectiva. El diccionario de datos y el diagrama entidad-relación se actualizarán con el diseño aprobado e implementado.

## Decisiones pendientes

1. Aprobar el correctivo de HU-03 y confirmar si se conserva un único rol por usuario.
2. Aprobar el Sprint 2 y la asociación `product_locations`.
3. Acordar criterios técnicos para comparar productos equivalentes y tratamiento de coincidencias.
4. Confirmar la numeración provisional HU-05 a HU-10 con el backlog académico.
5. Refinar los sprints 3 a 5 antes de programarlos.

## Referencias internas

- [Validación del Sprint 1](sprint-1-validation.md).
- [Plan del Sprint 2](sprint-2.md).
- [Diccionario de datos propuesto](data-dictionary-sprint-2.md).
- [Matriz de pruebas propuesta](sprint-2-test-matrix.md).

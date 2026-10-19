# Planificación global de cinco sprints

Fecha de revisión: 19 de octubre de 2026, America/La_Paz.

Estado: planificación general con avances aprobados y verificados por etapas. La distribución global debe contrastarse con la planificación académica existente, si la hubiera. Publicar este documento no autoriza implementar las funcionalidades pendientes.

Validación de cierre del 19 de octubre: se ejecutó una nueva regresión de 315 pruebas y 2.409 aserciones en testing y un recorrido asistido con capturas en un entorno adicional autorizado. La principal se conserva. La [aceptación funcional](sprint-2-acceptance.md) sigue pendiente de revisión humana; UI-S2-02 (página 419 en inglés) está abierta. Esta revisión no cierra formalmente el Sprint 2 ni inicia el Sprint 3.

## Distribución propuesta

Actualización del 19 de octubre: HU-10 implementada y aplicada a la principal con autorización y respaldo nuevo, lote 12. Consulta la [Etapa 5](sprint-2-stage-5.md) para evidencia de despliegue y pruebas.

| Sprint | Objetivo | Alcance | Estado |
|---|---|---|---|
| 1 | Acceso y personas | Autenticación, usuarios, trabajadores y cierre del correctivo de roles/permisos configurables. | Correctivo HU-03 implementado, probado y migrado con autorización; primer administrador y aceptación visual pendientes. |
| 2 | Catálogo y organización física | Categorías, unidades de medida, atributos por categoría, productos, ubicaciones y unidades individualizadas. | HU-05/HU-06/HU-07/HU-09/HU-08/HU-10 aplicadas a la principal con autorización y respaldo. HU-10 se aplicó el 19 de octubre de 2026, lote 12: 27 migraciones, ninguna pendiente, 24 tablas, 48 permisos y 95 vínculos. Regresión previa en testing: 315 pruebas y 2.411 verificaciones aprobadas. Aceptación visual pendiente. Evidencia en [Etapa 5](sprint-2-stage-5.md). |
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

1. Revisar los [resultados del correctivo HU-03](sprint-1-hu03-corrective.md), cuya migración principal ya se aplicó con autorización, y configurar el primer administrador. Se conserva un único rol por usuario según el alcance aprobado.
2. Completar la aceptación visual de las etapas entregadas. HU-08 y HU-10 ya se aplicaron a la principal con autorizaciones y respaldos propios, el 18 y 19 de octubre de 2026 respectivamente. La aplicación de HU-10 conserva datos e identidades; no inicia el Sprint 3.
3. La comparación exacta por categoría/unidad/modo/identificadores fue aprobada para advertencias con revisión humana. Conversiones, tolerancias y equivalencia certificada permanecen fuera de alcance.
4. Confirmar la numeración provisional HU-05 a HU-10 con el backlog académico.
5. Refinar los sprints 3 a 5 antes de programarlos.

## Referencias internas

- [Validación del Sprint 1](sprint-1-validation.md).
- [Plan del Sprint 2](sprint-2.md).
- [Diccionario de datos propuesto](data-dictionary-sprint-2.md).
- [Matriz de pruebas propuesta](sprint-2-test-matrix.md).

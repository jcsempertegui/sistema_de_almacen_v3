# Planificación global de cinco sprints

Fecha de revisión: 5 de noviembre de 2026, America/La_Paz.

Estado de cierre: los cinco sprints están implementados. La instalación actual se comprueba en el [informe de cierre](cierre-proyecto.md); la documentación de operación vigente está en los manuales [de usuario](manual-usuario.md) y [técnico](manual-tecnico.md). Las cifras y lotes de las filas siguientes describen los despliegues históricos; no indican migraciones pendientes ni autorizan repetirlas. Ya existe acceso administrativo activo. La aceptación humana, la correspondencia académica y la publicación de la versión candidata se registran por separado.

Actualización de mantenimiento — 2 de noviembre de 2026: se autorizó y aplicó la traducción del esquema y las clases propias al español, además del reemplazo de la simulación anterior por un año nuevo con 116 productos. La principal tiene ahora 53 tablas y 44 migraciones, sin pendientes, con 123 permisos y 260 asignaciones. Se mantienen los cinco sprints; esta adaptación no crea un sexto sprint ni modifica las evidencias históricas de los lotes 13–15. Véase el [informe de renovación](renovacion-espanol-2026-11-02.md). Las reservas de aceptación humana y revisión académica siguen siendo independientes.

Estado: distribución implementada y verificada por etapas. La correspondencia con las seis iteraciones históricas de la tesis está en la [matriz académica revisable](matriz-academica-cierre.md); el desarrollo actual mantiene cinco sprints. La documentación no autoriza funcionalidades nuevas.

Validación de cierre del 19 de octubre: evidencia histórica de 315 pruebas/2.409 aserciones y correctivo UI-S2-02 con 39 pruebas/306 aserciones, más recorridos asistidos. El usuario declaró aceptación funcional para avanzar, con reservas de evidencia humana conservadas en la [aceptación](sprint-2-acceptance.md) y el [guion personal](sprint-2-human-acceptance.md). Después se publicó el Sprint 3 y el usuario autorizó y aceptó su despliegue: dos migraciones aplicadas en lote 13, con respaldo actual y restauración de ensayo. Véase [despliegue y aceptación declarada](sprint-3-deployment.md).

## Distribución implementada e historial de despliegues

Actualización del 19 de octubre: HU-10 implementada y aplicada a la principal con autorización y respaldo nuevo, lote 12. Consulta la [Etapa 5](sprint-2-stage-5.md) para evidencia de despliegue y pruebas.

| Sprint | Objetivo | Alcance | Estado |
|---|---|---|---|
| 1 | Acceso y personas | Autenticación, usuarios, trabajadores y cierre del correctivo de roles/permisos configurables. | Implementado y desplegado; existe acceso administrativo activo en este PC. La aceptación visual final se documenta por separado. |
| 2 | Catálogo y organización física | Categorías, unidades de medida, atributos por categoría, productos, ubicaciones y unidades individualizadas. | HU-05/HU-06/HU-07/HU-09/HU-08/HU-10 aplicadas a la principal con autorización y respaldo. HU-10 se aplicó el 19 de octubre de 2026, lote 12: 27 migraciones, ninguna pendiente, 24 tablas, 48 permisos y 95 vínculos. Regresión previa en testing: 315 pruebas y 2.411 verificaciones aprobadas. Aceptación declarada por el usuario; evidencia personal pendiente en los casos reservados. Evidencia en [Etapa 5](sprint-2-stage-5.md). |
| 3 | Recepción y existencias | Recepción, remitos, aperturas y existencias por ubicación, con movimientos únicos por documento confirmado. | Publicado y desplegado en lote 13. Estado histórico: 31 tablas, 29 migraciones, 62 permisos y 126 vínculos. Aceptación declarada con reservas de evidencia humana. El seguimiento posterior de CSV y TEST-S3-01 está en la [matriz S4](sprint-4-test-matrix.md). Véase [despliegue S3](sprint-3-deployment.md); el lote 14 amplió posteriormente el esquema para Sprint 4. |
| 4 | Entregas y préstamos | HU-18 a HU-23: entregas, préstamos, devoluciones, responsabilidades y disponibilidad. | Publicado y desplegado el 20 de octubre con autorización, respaldo nuevo y restauración de ensayo. Seis migraciones en lote 14: 42 tablas, 35 migraciones, ninguna pendiente, 81 permisos y 166 vínculos. Regresión previa en testing: 401 pruebas/3.143 aserciones. Aceptación declarada y reservas de evidencia humana conservadas. Véase [despliegue](sprint-4-deployment.md). |
| 5 | Control y cierre | Ajustes/anulaciones autorizadas, reportes, auditoría, regresión y documentación académica final. | Diseño aprobado el 20 de octubre, implementado en `feature/sprint-5-control-and-close` e integrado en `main`. Ajustes, compensaciones, ciclos, reportes, Backup y auditoría; regresión completa en testing 429 pruebas/3.406 aserciones aprobada; presentación posterior 3 pruebas/75 aserciones. Testing: 53 tablas/43 migraciones. Ocho migraciones aplicadas en lote 15 tras ensayos y resolución autorizada del incidente: principal con 53 tablas, 43 migraciones, ninguna pendiente, 123 permisos y 260 vínculos. [Despliegue S5](sprint-5-deployment.md); documentación final publicada con autorización del usuario. Véase [seguimiento](sprint-5-publication.md). Aceptación declarada por el usuario; [registro](sprint-5-acceptance.md). Evidencias detalladas de navegador y contraste académico pendientes. Véase [informe S5](sprint-5-implementation.md). |

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
- Movimientos únicos por documento confirmado y confirmaciones idempotentes.
- Existencias nunca negativas, comprobadas en confirmaciones y retiros.
- Encargado selecciona las ubicaciones de retiro.
- Herramientas prestables con identificación individual única.
- Trabajadores independientes de las cuentas de usuario.
- Registros con historial conservados; no reutilizar identificadores de registros desactivados.
- Ajustes y anulaciones críticas requieren autorización.
- Validación y autorización siempre en el servidor.

## Documentación académica

En cada sprint se entregarán historias y criterios de aceptación, tareas y dependencias, matriz de pruebas, resultados obtenidos, capturas de aceptación, decisiones técnicas y retrospectiva. El diccionario de datos y el diagrama entidad-relación se actualizarán con el diseño aprobado e implementado.

## Decisiones pendientes

1. Formalizar el responsable del acceso administrativo existente y revisar sus permisos; no volver a crear el primer administrador. Se conserva un único rol por usuario según el alcance aprobado.
2. Completar la aceptación visual de las etapas entregadas. HU-08 y HU-10 ya se aplicaron a la principal con autorizaciones y respaldos propios, el 18 y 19 de octubre de 2026 respectivamente. La aplicación de HU-10 conserva datos e identidades; no inicia el Sprint 3.
3. La comparación exacta por categoría/unidad/modo/identificadores fue aprobada para advertencias con revisión humana. Conversiones, tolerancias y equivalencia certificada permanecen fuera de alcance.
4. Aprobar la correspondencia de HU1–HU13 históricas con HU-01–HU-29 actuales y resolver las brechas de variantes/reportes según la matriz académica; no renumerar los sprints implementados.
5. Los cinco sprints están publicados y desplegados; la traducción y los datos simulados se renovaron posteriormente con autorización. Completar el registro de aceptación humana, el cotejo del DOCX y el cierre académico sin convertir resultados técnicos en firmas o aprobaciones externas.

## Referencias internas

- [Validación del Sprint 1](sprint-1-validation.md).
- [Plan del Sprint 2](sprint-2.md).
- [Diccionario de datos propuesto](data-dictionary-sprint-2.md).
- [Matriz de pruebas propuesta](sprint-2-test-matrix.md).
- [Alcance autorizado del Sprint 3](sprint-3.md).
- [Implementación, conservación de datos y despliegue pendiente](sprint-3-implementation.md).
- [Guion funcional pendiente del Sprint 3](sprint-3-acceptance.md).
- [Implementación del Sprint 4](sprint-4-implementation.md).
- [Despliegue verificado del Sprint 4](sprint-4-deployment.md).

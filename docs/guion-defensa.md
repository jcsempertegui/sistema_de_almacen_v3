# Guion de demostración y expediente para la defensa

Preparado el 5 de noviembre de 2026. Es una propuesta de recorrido, no un acta de evaluación ni un registro de aceptación humana ejecutada.

## Mensaje inicial

«El sistema administra un solo almacén para apoyar la provisión de herramientas, EPP y materiales al personal de la Planta Separadora de Líquidos Río Grande. Se desarrolló en cinco sprints. Los datos de esta demostración son ficticios y representan un año de operación de 20 trabajadores operativos y 4 de staff.»

No afirmar que CONFIPETROL certificó los consumos simulados, que se hicieron entrevistas no documentadas o que las pruebas automáticas equivalen a aceptación de los trabajadores.

## Preparación previa

1. Identificar el commit de la versión de defensa y la ubicación de su evidencia técnica. Comprobar PHP, Laravel, MariaDB y la dirección de ingreso sin cambiar versiones.
2. Revisar [el informe de cierre](cierre-proyecto.md), [la matriz académica](matriz-academica-cierre.md), [el modelo de datos](modelo-datos-espanol.md) y [el manual de usuario](manual-usuario.md).
3. Usar un entorno de aceptación autorizado separado de la principal y de PHPUnit, con datos ficticios y cuentas propias. No crear documentos de demostración en la principal durante la defensa ni usar testing mientras se ejecutan suites.
4. Identificar solicitante y autorizador distintos para operaciones críticas. Verificar permisos efectivos antes de la sesión. No proyectar contraseñas, `.env`, volcados SQL, sesiones ni logs privados.
5. Preparar capturas o archivos estáticos como alternativa si falla el PC. Una captura no demuestra por sí sola una confirmación o un rollback.

## Recorrido propuesto de 15–20 minutos

| Orden / duración orientativa | Acción y explicación | Evidencia que conviene mostrar |
|---|---|---|
| 1 / 2 min | Ingresar y presentar el panel: pendientes, herramientas y borradores. Mostrar diferencia de permisos entre roles sin compartir contraseñas | Pantalla y permisos efectivos; no número de pruebas como argumento de usabilidad |
| 2 / 2 min | Buscar trabajador y producto por fragmentos; explicar que trabajador no requiere usuario | Sugerencias con teclado, ficha y separación de tablas |
| 3 / 2 min | Mostrar categoría, unidad, atributos, producto e identificador individual; explicar posibles duplicados sin fusión automática | Catálogo e historial técnico; reconocer la brecha de variantes documentales |
| 4 / 2 min | Explicar un remito: borrador sin efecto, confirmación y movimiento único. Distinguir remito de entrega | Documento, ubicación, saldo y movimiento de origen |
| 5 / 2 min | Preparar una entrega de guantes al trabajador y seleccionar ubicación manualmente | Búsqueda, validación y saldo antes/después; solo confirmar en entorno autorizado |
| 6 / 3 min | Prestar dos herramientas identificadas y devolver una; mostrar que la otra sigue pendiente | Identificadores, responsable, devolución parcial y custodia |
| 7 / 2 min | Explicar ajuste: diez registrados y ocho contados; autorización de otra persona y ejecución de salida dos | Solicitud, autorización, movimiento y motivo; sin reparación SQL manual |
| 8 / 2 min | Explicar una compensación: preservar original y registrar efecto contrario con restricciones | Enlace a original y compensación; bloqueo cuando hay dependencias |
| 9 / 2 min | Filtrar reporte de entregas y kardex; mostrar unidad, responsables y saldo del intervalo | Tabla, CSV e impresión; los filtros conservan el significado del resultado |
| 10 / 1 min | Mostrar auditoría y explicar respaldos: SHA-256 no es una restauración | Metadatos/evidencias fechadas del ensayo; no ejecutar recuperación principal |

Los tiempos orientan la exposición; no son métricas de rendimiento ni estimaciones históricas de Scrum. Si solo hay autorización de consulta, recorrer documentos existentes del entorno de demostración y no simular confirmaciones.

## Preguntas previsibles

| Pregunta | Respuesta respaldada por la implementación |
|---|---|
| ¿Por qué cinco sprints si la tesis anterior tenía seis? | El backlog se reorganizó por dependencias: acceso/personas, catálogo, inventario, operaciones con trabajadores y control/cierre. La matriz conserva HU históricas y actuales sin reasignarlas retroactivamente |
| ¿Qué impide confirmar dos veces? | Transacción, bloqueo, comprobación del estado e índices únicos por fuente de movimiento; mostrar la prueba específica y su resultado |
| ¿Por qué no se borra un remito confirmado? | La trazabilidad exige conservarlo; una compensación autorizada registra el efecto contrario cuando es admisible |
| ¿Qué pasa si se desactiva un trabajador que debe herramientas? | Conserva la obligación y puede tramitarse la devolución correspondiente |
| ¿Por qué una herramienta no aparece disponible? | Puede estar prestada, fuera de almacén o vinculada a referencias inactivas; presencia física y disponibilidad operativa son conceptos distintos |
| ¿Puede Excel crear artículos? | No. El CSV de apertura referencia catálogo existente y valida el lote antes de confirmar |
| ¿El sistema es de producción industrial certificado? | Es el sistema implementado y probado en el entorno documentado. No se atribuyen certificaciones, SLA, pentest ni aceptación externa sin evidencia |
| ¿Las variantes originales están completas? | El catálogo técnico cubre registro/asociación/validación/historial; el detalle de atributos en documentos requiere resolver la correspondencia académica señalada en la matriz |
| ¿Se puede restaurar con un clic sobre la principal? | No. El ensayo se autoriza para un destino nuevo aislado; la recuperación principal sigue un procedimiento administrativo separado |

## Expediente académico por completar con evidencias reales

| Anexo | Material disponible | Revisión pendiente |
|---|---|---|
| Requerimientos y objetivos | Matriz con los seis objetivos, 13 funcionales y diez no funcionales facilitados por el autor | Cotejo literal con DOCX y criterios históricos; instrumentos de levantamiento |
| Backlog y sprints | Plan global y documentos S1–S5, HU-01 a HU-29 | Justificación académica de prioridades y reorganización |
| Diseño | Diccionario de 53 tablas, modelo lógico Mermaid, [procesos y despliegue](diagramas-procesos.md) y correspondencia de clases | Exportar figuras legibles y numerarlas en el documento final |
| Implementación | Código, migraciones históricas y registros de despliegue | Seleccionar capturas actuales y referenciar el commit de defensa |
| Pruebas | Matrices y evidencia técnica de cada etapa y cierre | Presentar resultados actuales e históricos por separado; acta humana pendiente |
| Manuales | Usuario y técnico actualizados | Revisión de comprensión por el responsable funcional |
| Datos de demostración | Escenario ficticio anual documentado | Etiquetarlo como simulación en tablas, capturas y exposición |
| Conclusiones y limitaciones | Brechas y alcance declarados en el cierre | Redactar conclusiones a partir de resultados efectivos; sin porcentajes de mejora no medidos |

El documento de tesis original no se modifica por generar este expediente. Su integración y la aceptación académica deben revisarse expresamente con el autor y los responsables correspondientes.

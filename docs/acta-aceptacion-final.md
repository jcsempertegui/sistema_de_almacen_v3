# Registro de aceptación funcional final — sin firmas ni aprobación

Formulario preparado el 5 de noviembre de 2026. **Todos los casos humanos permanecen pendientes** hasta completar este registro con resultados reales. No es un acta emitida por CONFIPETROL ni una validación del tribunal.

Responsable funcional: ____________________

Fecha/hora y zona: ____________________

Versión/commit evaluado: ____________________

Instancia/servidor/puerto/directorio/base de aceptación verificados: ____________________

Conjunto de datos ficticios y autorización de preparación: ____________________

Usuarios y permisos efectivos utilizados, sin contraseñas: ____________________

## Resultados por caso

| Caso local del guion | Criterio para aprobar | Resultado obtenido | Evidencia / incidencia | Decisión |
|---|---|---|---|---|
| Acceso y permisos | Acceso válido, rechazo inválido, cierre, 419 en español y revocación efectiva | Pendiente | Pendiente | Pendiente |
| Personas | Usuarios separados de trabajadores; búsquedas y validaciones comprensibles | Pendiente | Pendiente | Pendiente |
| Catálogo | Registro/consulta de categorías, unidades, atributos y producto con historial | Pendiente | Pendiente | Pendiente |
| Organización física | Jerarquía sin ciclos, asociaciones válidas e identificadores únicos | Pendiente | Pendiente | Pendiente |
| Remitos e inventario | Borrador sin efecto y confirmación única, saldo coherente y no negativo | Pendiente | Pendiente | Pendiente |
| Apertura CSV | Solo catálogo existente; lote inválido sin efectos parciales | Pendiente | Pendiente | Pendiente |
| Entregas | Trabajador y retiro manual; confirmación física única y detalle correcto | Pendiente | Pendiente | Pendiente |
| Préstamos | Identidad disponible y custodia exclusiva | Pendiente | Pendiente | Pendiente |
| Devoluciones | Parcial/total sin duplicación; regularización cuando corresponde | Pendiente | Pendiente | Pendiente |
| Ajustes y compensaciones | Motivo, dos personas, autorización vigente y trazabilidad sin borrar original | Pendiente | Pendiente | Pendiente |
| Reportes | Campos/filtros contrastados, cantidades sin duplicación, CSV e impresión correctos | Pendiente | Pendiente | Pendiente |
| Respaldos y auditoría | Denegación por permisos, separación solicitante/autorizador y evidencia del ensayo autorizado | Pendiente | Pendiente | Pendiente |
| Navegación y presentación | Español, menú único, sugerencias, teclado, móvil, escritorio y zoom 200 % | Pendiente | Pendiente | Pendiente |

Cada resultado debe describir pasos y valores observados; un «OK» aislado o una captura del mensaje de éxito no acredita saldo, custodia e idempotencia. Las operaciones de escritura se limitan al entorno aislado autorizado.

## Distribución propuesta de Backup, sin concesiones automáticas

| Capacidad | Administrador | Encargado 1 | Encargado 2 |
|---|---|---|---|
| Consultar/generar/verificar, según responsabilidad aprobada | Revisar | Propuesto | Propuesto |
| Descargar SQL | Solo si se autoriza expresamente | Solo si se autoriza expresamente | Solo si se autoriza expresamente |
| Solicitar ensayo aislado | No necesario para el flujo habitual | Propuesto | Propuesto |
| Autorizar ensayo solicitado por otra persona | Propuesto | No | No |
| Ejecutar ensayo autorizado en consola | Según responsabilidad | Propuesto | Propuesto |

Las celdas son una propuesta de configuración, no el estado efectivo de los roles. Revisar los permisos existentes sin restablecer los revocados. Si el mismo usuario solicita y autoriza, debe rechazarse. No se crea una segunda cuenta para aparentar separación de personas.

## Cierre

Decisión posible: aceptado / aceptado con observaciones / observado / no ejecutado.

Decisión real: ____________________

Observaciones y acciones pendientes: ____________________

Responsable y constancia de aprobación: ____________________

La firma funcional no acredita por sí sola el cumplimiento académico ni autoriza nuevas migraciones, restauraciones o publicación de información privada.

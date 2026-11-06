# Manual de usuario — SGA CONFIPETROL

Versión preparada para revisión funcional, 5 de noviembre de 2026. Un solo almacén: Planta Separadora de Líquidos Río Grande. Las opciones visibles y las operaciones permitidas dependen de los permisos configurados; el nombre del rol por sí solo no concede acceso.

## Antes de comenzar

Abre `http://127.0.0.1:8000/ingresar` en este PC. Utiliza tu cuenta autorizada. No hay registro público ni contraseña general. No confundas una cuenta de acceso con un trabajador del contrato: los trabajadores pueden recibir materiales o herramientas sin tener usuario.

La instalación actual contiene un **escenario ficticio de un año**, autorizado para demostración. Incluye 20 trabajadores operativos y 4 de staff. No constituye un registro real de consumos de CONFIPETROL. Una demostración que confirme documentos debe realizarse en un entorno aislado aprobado, no agregando operaciones ficticias a datos operativos.

El menú lateral permite cambiar de sección. En móvil se abre con «Abrir menú». Para seleccionar trabajadores, artículos o ubicaciones, escribe una parte del nombre o código. Las sugerencias admiten teclado; selecciona la opción concreta antes de guardar. Si hay un error de validación, revisa el mensaje y vuelve a comprobar todas las líneas.

## Inicio

El panel resume operaciones pendientes: artículos con o sin existencias, herramientas en custodia y vencidas, borradores, movimientos recientes y gráficas, según tus permisos. Una herramienta sin vencimiento no se presenta como vencida. Las cifras de artículos cuentan referencias, no suman metros, unidades y kilogramos.

Los accesos rápidos permiten preparar una entrega, préstamo o devolución. Abrir un formulario no confirma una operación física. Los borradores pendientes requieren revisión; no representan por sí mismos una entrada o salida.

## Existencias

Busca un artículo y revisa su ubicación y unidad. El saldo físico es lo que registran los movimientos confirmados. La disponibilidad para prestar puede ser menor si hay referencias inactivas o herramientas ya prestadas. La ubicación inicial de una unidad individualizada no sustituye su presencia física actual.

Para revisar cómo se obtuvo un saldo, consulta el kardex en Reportes. Distingue la fecha comercial del documento de la fecha en que se contabilizó el movimiento.

## Entregas

Se utilizan para EPP, consumibles y artículos individuales no prestables que se entregan a un trabajador. Las herramientas prestables se gestionan en Préstamos.

1. Selecciona el trabajador y la fecha del documento.
2. Añade artículos, cantidades e identificadores cuando corresponda.
3. Selecciona manualmente la ubicación de retiro de cada línea.
4. Guarda el borrador y comprueba el detalle.
5. Confirma únicamente cuando la entrega física sea efectiva.

Ejemplo: una entrega de dos pares de guantes descuenta dos unidades de la ubicación elegida al confirmar. Guardar el borrador no descuenta nada. Un reintento de confirmación no debe generar otra salida. No se admite saldo negativo.

Una dotación semestral puede identificarse mediante observaciones y consultarse en el reporte de entregas. El sistema no programa automáticamente la siguiente dotación ni certifica la adecuación técnica del EPP.

## Herramientas

### Herramientas prestadas

Registra un préstamo para un trabajador, indicando cada herramienta por su identificador y la ubicación de salida. El vencimiento es opcional. Al confirmar se registra la salida física y la custodia; la misma herramienta no puede prestarse simultáneamente a otra persona.

El listado permite consultar obligaciones pendientes. El detalle muestra responsable, herramientas y situación de devolución. Desactivar al trabajador no elimina sus obligaciones.

### Devoluciones

Desde el préstamo, utiliza «Registrar devolución de este préstamo». Selecciona solo las herramientas realmente recibidas y su ubicación de recepción. Puede ser distinta de la de salida, pero debe ser un destino válido y asociado al producto.

Cada devolución corresponde a un solo préstamo. Confirmar una devolución parcial cierra los ítems recibidos; los demás siguen pendientes. La última recepción pendiente completa el préstamo. Una herramienta no puede devolverse dos veces.

Si el catálogo asociado está inactivo, puede ser necesaria una regularización excepcional específica. Deben registrarse solicitud, motivo y autorización; no se reactiva automáticamente ningún registro. La autorización no sustituye la recepción física.

### Disponibilidad y unidades individualizadas

Disponibilidad distingue identidad, ubicación física y custodia. Unidades individualizadas administra el identificador interno único, serie y datos permitidos. Registrar una identidad no crea existencias. No cambies una ubicación histórica para simular una transferencia: los movimientos deben seguir su procedimiento autorizado.

## Remitos

Registra entradas o salidas con contraparte, fecha, referencia externa opcional y líneas de producto/ubicación. Una coincidencia documental es una advertencia para revisar; no demuestra duplicación certificada.

Revisa el borrador antes de confirmar. El documento confirmado conserva su historial y no se edita ni elimina. Un remito es independiente de una entrega a un trabajador: no registres ambos para representar la misma salida física.

Las existencias iniciales sirven para una apertura controlada del catálogo, no para corregir saldos posteriores. La importación CSV referencia productos ya existentes; no crea ni modifica productos. No repitas una apertura para agregar stock.

## Catálogo

Prepara primero categorías, unidades de medida y ubicaciones válidas. Los atributos técnicos se definen y asocian por categoría antes de completar los valores de un producto. Se validan tipos, obligatoriedad, límites y precisión.

Cada producto tiene código único permanente, unidad, categoría y modo de control. Un producto prestable utiliza control individual. Una advertencia de posible equivalencia se revisa por una persona; no fusiona productos ni certifica intercambiabilidad.

Un producto puede asociarse a varias ubicaciones. Esa asociación habilita destinos, pero no representa cantidades. Las ubicaciones pueden organizarse en sector, estante o gabinete, sin ciclos y con padres válidos. Las desactivaciones conservan referencias históricas y pueden bloquearse por dependencias.

## Trabajadores

Registra código, datos personales y datos del contrato que solicite el formulario. Busca por nombre o código y revisa las responsabilidades antes de desactivar un trabajador. La desactivación no elimina documentos ni préstamos pendientes. No crees un usuario para cada trabajador si no necesita operar el sistema.

## Reportes

El menú agrupa inventario/kardex, remitos, entregas/EPP, herramientas/responsables y control/auditoría. Las pestañas del grupo cambian la consulta; los filtros «Más filtros» permiten acotar el resultado.

La tabla está paginada. La descarga CSV incluye el resultado filtrado completo; la impresión tiene un límite de 5.000 líneas y puede guardarse como PDF mediante el navegador. Para imprimir intervalos extensos, acota los filtros. Ver un reporte no concede automáticamente acceso al documento de origen o a su descarga.

Revisa siempre fechas, producto, ubicación, trabajador y unidad antes de interpretar cantidades. El kardex conserva el saldo anterior al intervalo; los reportes de custodia muestran situación actual, no una reconstrucción histórica de quién tenía una herramienta en cualquier fecha.

## Administración

### Corregir diferencia de inventario: ajustes

Un ajuste registra una diferencia entre el conteo físico y el saldo del sistema. No sustituye un ingreso o entrega que todavía no se haya registrado.

Ejemplo: el sistema indica diez cajas y un conteo verificado encuentra ocho. Prepara el ajuste con cantidad contada ocho y un motivo. Guardar y solicitar no cambia el saldo. Otra persona autorizada revisa y aprueba. La ejecución registra una salida de dos, conservando el documento y la autoría. Si el saldo cambió antes de ejecutar, debe revisarse la operación; no se fuerza una corrección obsoleta.

### Compensar operación confirmada

Una compensación contrarresta los efectos de una operación confirmada sin borrarla ni reescribir sus movimientos. No es «eliminar un documento» ni cambiar su cantidad original.

Ejemplo: se confirmó por error una entrega de dos unidades que físicamente no se retiraron. Una compensación autorizada puede registrar el efecto contrario y enlazarlo al documento original. Si existen operaciones posteriores incompatibles, faltan existencias o interviene una custodia, la compensación puede bloquearse. No uses compensaciones para ocultar una pérdida real ni para duplicar la recepción de una devolución.

Los ajustes y compensaciones requieren motivo y permisos de solicitar, autorizar y ejecutar. Se exige solicitante distinto del autorizador y una autorización vigente. No se hacen correcciones directamente en las tablas SQL.

### Usuarios, roles y auditoría

Los usuarios autorizados administran cuentas y permisos. No se puede eliminar el último acceso administrativo completo. Un cambio de permisos puede cerrar sesiones existentes. La auditoría permite revisar quién actuó, cuándo y sobre qué documento; no sustituye un acta de aceptación humana.

### Respaldos

Un respaldo SQL se conserva fuera del repositorio y del acceso público. Verificar su SHA-256 acredita integridad del archivo; no acredita que se haya restaurado correctamente.

El flujo operativo propuesto es Encargado solicita ensayo, Administrador autoriza y Encargado ejecuta en consola, en un destino nuevo y aislado. Solicitante y autorizador deben ser personas distintas. Los permisos efectivos deben configurarse expresamente; no se conceden automáticamente por este manual. No hay restauración automática destructiva sobre la principal.

## Mensajes y problemas frecuentes

| Situación | Acción |
|---|---|
| Sesión vencida o error 419 | Vuelve a ingresar y revisa el documento antes de reenviar; no desactives CSRF |
| Acceso denegado | Solicita revisión de permisos; escribir otra URL no concede autorización |
| Edición obsoleta o conflicto | Recarga el documento y contrasta los cambios antes de continuar |
| Existencias insuficientes | Revisa artículo y ubicación; no registres aperturas para forzar el saldo |
| Herramienta prestada | Consulta el préstamo vigente y recibe físicamente la devolución correspondiente |
| Referencia inactiva | Revisa la dependencia o solicita regularización cuando el flujo la admita |
| Error al confirmar | Comprueba el estado y los movimientos del documento antes de repetir; conserva el mensaje para diagnóstico |

El responsable funcional debe contrastar este manual con la versión de defensa y registrar las observaciones en el guion de aceptación.

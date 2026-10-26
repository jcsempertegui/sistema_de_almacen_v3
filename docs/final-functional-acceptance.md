# Guion de aceptación funcional final — preparado, no ejecutado

Fecha: 25 de octubre de 2026, America/La_Paz. Alcance: los cinco sprints implementados. Este documento no concede aprobación visual ni autoriza datos ficticios en la principal, nuevas bases, respaldos o restauraciones reales.

## Preparación y separación

En la principal solo se permiten consultas SQL de lectura, bajo transacción de solo lectura, para contrastar identidad, migraciones, permisos y conteos existentes. No abrir sesiones, iniciar sesión, descargar desde rutas auditadas, generar reportes por HTTP ni usar factories en la principal durante esta validación: aun una ruta GET puede escribir sesiones o auditoría.

Para los recorridos, solicitar primero autorización para un entorno de aceptación distinto de la principal y de PHPUnit. Preferir una instancia separada con puerto y directorio propios, checkout/configuración propios, almacenamiento propio y clave independiente autorizada. No copiar el `.env` operativo sin revisión ni generar claves en este proyecto. El esquema y los fixtures mínimos se preparan únicamente tras esa autorización.

Antes del primer fixture, registrar y comparar: alias Laravel único y nombre interno iguales (`sga_acceptance`, propuesto solo para ese entorno), host, puerto, `SELECT DATABASE()`, `@@hostname` y `@@datadir`; retirar del proceso toda configuración/conexión principal y verificar `Model::getConnection()->getName()` para las factories utilizadas. Si se prepara una instancia aislada con nombre `sga_rio_grande_testing` por compatibilidad del módulo Backup, demostrar que puerto y datadir son distintos de los de PHPUnit y de la principal. No reutilizar una conexión guardada cuyo nombre interno siga siendo `mysql`. El entorno de aceptación no se creó en este trabajo.

Usar cuentas ficticias con roles Administrador, Encargado y Consulta/Auditor, más dos personas distintas para solicitar/autorizar operaciones críticas. Registrar permisos efectivos; no asumir que las concesiones iniciales siguen vigentes. No compartir datos con una suite en ejecución. Conservar la evidencia y prever la limpieza únicamente de fixtures propios, con autorización; no borrar bases existentes.

## Casos previstos

Todos los estados de esta tabla son **pendientes de ejecución y evidencia humana**. Los nombres de caso son etiquetas locales del guion, no requisitos académicos oficiales.

| Caso y alcance | Consulta admisible en principal | Recorrido exclusivo en aceptación y resultado esperado |
|---|---|---|
| Acceso y roles | permisos/roles y estados existentes mediante SQL de lectura | login correcto/incorrecto, cierre, sesión expirada, usuario inactivo; rutas y botones según permisos; revocar un permiso y comprobar que el servidor rechaza la acción aunque siga visible la pantalla |
| Usuarios y trabajadores | conteos, relación de rol y ausencia de cuentas obligatorias para trabajadores | crear/editar/desactivar cuentas y trabajadores; validar duplicados y contraseña; conservar al menos un administrador y auditoría; un trabajador no requiere usuario |
| Categorías y unidades | categorías, unidades y restricciones existentes | crear y editar categoría/unidad, validar código único y referencias; impedir desactivaciones incompatibles |
| Atributos y productos | catálogo y revisiones existentes | crear atributo, asociarlo por categoría, tipado/requeridos/identificador, crear producto y revisar historial; advertencia de posible duplicado requiere revisión humana y no fusiona ni convierte unidades |
| Ubicaciones e identidades | jerarquía, asociaciones e identificadores existentes | impedir ciclos y padres inactivos, asociar producto a ubicaciones, registrar herramienta individual única y bloquear referencias inválidas |
| Inventario y remitos | saldos, movimientos y documentos confirmados existentes | borrador sin movimiento; confirmar ingreso/egreso una sola vez; reintentar sin duplicación; saldo no negativo; remito confirmado inmutable y trazable; remito y entrega independientes |
| Apertura manual/CSV | existencia de aperturas y catálogo existente | apertura única por producto; CSV solo referencia catálogo previo, no crea/modifica productos; rechazar cantidades/ubicaciones/identidades inválidas sin efectos parciales |
| Entregas | documentos y movimientos existentes | elegir ubicaciones de retiro; borrador, edición y cancelación sin stock; confirmación atómica y repetición idempotente; rechazar saldo insuficiente |
| Préstamos y devoluciones | custodia y ciclos existentes | préstamo individual exclusivo; devolución parcial/total con recepción física y cierre correspondiente; consultar responsabilidades; respetar regularización y permisos cuando hay referencias inactivas |
| Ajustes | solicitudes/autorizaciones/movimientos existentes | conteo 10 a 8: guardar/solicitar no cambia saldo; otra persona autoriza; ejecutar produce salida 2 una sola vez; revocación, autorización caducada y conteo obsoleto se rechazan |
| Compensaciones | enlaces y originales existentes | compensación total autorizada conserva original y ledger, sin cascadas ni saldos negativos; compensar devolución reabre solo su ciclo; impedirla si hay dependencia posterior incompatible |
| Reportes | cálculos SQL sobre documentos y saldos existentes | contrastar filtros y cortes históricos, existencias, movimientos, remitos, entregas, préstamos, devoluciones y correcciones; CSV con decimales y texto que parece fórmula debe exportarse con protección |
| Backup | diagnóstico de binarios con `--version` y metadatos existentes por SQL | comprobar vistas y denegaciones de permisos sin generar archivos reales; generación, verificación, descarga y restauración reales quedan excluidas hasta autorización separada; distinguir integridad de archivo de restauración verificada |
| Auditoría y presentación | eventos existentes por SQL de lectura | trazabilidad de acciones y correcciones, acceso por rol; escritorio/móvil, teclado, foco, zoom 200 %, mensajes en español, errores y confirmaciones comprensibles |

## Registro y aprobación

Por caso registrar: responsable, fecha/hora y zona, identidad del destino, datos/IDs de fixtures, precondiciones, pasos, resultado esperado y obtenido, captura sin secretos, incidencia y decisión del usuario. Las acciones de inventario requieren contraste del documento, saldo, movimiento y custodia correspondientes, no solo un mensaje visual.

Resultado posible: aprobado con evidencia, observado o no ejecutado. No convertir pruebas HTTP/PHPUnit en aceptación humana. Consolidar resultados con las matrices históricas S1–S5, conservar reservas anteriores y obtener la conformidad final explícita. No modificar tesis, publicar Git ni ejecutar recuperación principal como consecuencia automática de una aceptación funcional.

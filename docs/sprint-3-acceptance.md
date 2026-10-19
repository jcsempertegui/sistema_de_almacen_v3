# Sprint 3 — Guion funcional pendiente

**No ejecutado en navegador ni aprobado por el responsable funcional.** Las pruebas HTTP/SQL/entre procesos se registran en la matriz técnica y no completan este guion. La aceptación declarada del Sprint 2 no aprueba las pantallas nuevas del Sprint 3.

## Entorno propuesto

Con autorización posterior, preparar un entorno adicional `sga_rio_grande_acceptance_s3`, con configuración y sesiones propias proporcionadas al proceso, fuera del repositorio. No se ha creado ese esquema. No migrar el entorno de aceptación S2 ni utilizar sus usuarios/datos para estas pruebas. No ejecutar este recorrido sobre la principal o mientras se utiliza testing para PHPUnit.

Datos mínimos propuestos, aún no creados: tres cuentas demostrativas de los roles iniciales; una categoría; unidad de conteo y unidad decimal; dos productos (por cantidad e individual); dos ubicaciones almacenables con asociaciones válidas; dos identidades individuales; contraparte ficticia identificada como demostrativa. Productos e identidades se crearían exclusivamente mediante sus formularios. Credenciales solo en un archivo privado externo.

## Recorrido para el responsable

| Caso | Acción y resultado esperado | Resultado humano |
|---|---|---|
| AC-S3-01 | Ingresar por cada perfil. Administrador y Encargado gestionan remitos; Consulta/Auditor solo consulta. Ocultar enlaces sin capacidad y rechazar acceso directo. | Pendiente |
| AC-S3-02 | Registrar un ingreso como borrador, con contraparte y referencia externa vacía. Consultarlo y editarlo. Los saldos no cambian. | Pendiente |
| AC-S3-03 | Confirmar el ingreso revisado. Comprobar documento cerrado, cantidad por ubicación, operador, hora de Bolivia y movimiento enlazado. | Pendiente |
| AC-S3-04 | Comprobar con apoyo técnico el reintento de confirmación del mismo documento. Una sola línea de movimiento y un único efecto físico. Refrescar solo la página GET no constituye prueba del reintento POST. | Pendiente |
| AC-S3-05 | Como Encargado, elegir explícitamente la ubicación de retiro. Confirmar egreso parcial y comprobar saldo. Intentar retirar más del saldo: error español sin cambios parciales. | Pendiente |
| AC-S3-06 | Revisar fecha, cantidad cero/negativa, precisión indebida, ubicación sin asociación y líneas repetidas. Mostrar errores comprensibles conservando el formulario. | Pendiente |
| AC-S3-07 | Registrar dos borradores con misma referencia externa, contraparte y tipo. Ver advertencia, revisar y decidir; no confundir coincidencia con duplicación certificada. | Pendiente |
| AC-S3-08 | Cancelar un borrador con motivo. Conservarlo para consulta sin movimientos; rechazar cancelación o edición de un confirmado. | Pendiente |
| AC-S3-09 | Crear identidades individuales sin recepción: sin existencias. Recibirlas seleccionándolas, egresar una y reingresarla por remito externo. Comprobar identificadores e historial; ubicación inicial de catálogo conservada. | Pendiente |
| AC-S3-10 | Intentar recibir una unidad ya presente o egresarla desde otra ubicación. Rechazo sin movimiento ni alteración del saldo. | Pendiente |
| AC-S3-11 | Preparar apertura con fecha de corte y varias ubicaciones del mismo producto. Encargado prepara; Administrador confirma según permisos iniciales. Rechazar segunda apertura o apertura después de movimientos. | Pendiente |
| AC-S3-12 | Revisar CSV exportado desde Excel con productos existentes. Mostrar códigos, ubicaciones e identificadores; guardar solo borrador. Rechazar catálogo desconocido, fórmulas y duplicados; advertir mismo archivo ya incorporado. | Pendiente |
| AC-S3-13 | Intentar desactivar producto/asociación con saldo y unidad físicamente presente. Rechazar con explicación. Comprobar consulta de historial tras egreso y desactivación permitida. | Pendiente |
| AC-S3-14 | Abrir borrador en dos sesiones; guardar en una y luego en otra. Exigir recarga al editor obsoleto. Revocar un permiso y comprobar nueva autenticación/denegación. | Pendiente |
| AC-S3-15 | Recorrer listado, formulario, revisión CSV, saldos e historial en móvil y escritorio, con teclado y zoom real al 200 %. Revisar foco, selección múltiple y mensajes. | Pendiente |

## Criterios para aceptar

Registrar personalmente resultados, incidencias y reservas por caso, con capturas sin claves ni cookies. Verificar concordancia de documentos, movimientos, saldos y presencia individual. Resolver incidencias bloqueantes y obtener conformidad explícita del responsable; no inferirla de una autorización de pruebas, despliegue o publicación. Cualquier necesidad de corregir confirmados debe derivarse al diseño de compensaciones autorizadas, sin borrar historial.

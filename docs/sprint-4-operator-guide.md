# Sprint 4 — Guion de aceptación pendiente

Estado: guía para revisión humana posterior. No se ha ejecutado en navegador ni acredita aceptación. La implementación local y sus pruebas HTTP no autorizan migrar la principal o crear datos allí.

## Preparación posterior

Solicitar autorización para un entorno de aceptación separado y sus datos mínimos. No usar testing mientras ejecuta PHPUnit. Proponer un trabajador activo, otro desactivado con obligación previa, dos ubicaciones almacenables asociadas, un producto por cantidad, un individual no prestable y dos herramientas prestables identificadas. No crear estos datos en la principal. Mantener credenciales fuera del repositorio.

## Entrega

1. Ingresar con el perfil Encargado, abrir Entregas y registrar borrador.
2. Elegir trabajador activo, producto, ubicación de retiro y cantidad. Para individuales no prestables, seleccionar las identidades correspondientes.
3. Guardar, consultar y editar. Comprobar que el saldo todavía no cambió.
4. Revisar físicamente lo entregado y confirmar. Verificar documento cerrado, movimiento único y saldo por ubicación.
5. Reintentar el POST con apoyo técnico: mismo resultado, sin segundo descuento.
6. Probar saldo insuficiente, referencia inactiva y producto prestable: rechazo sin efectos parciales. Las herramientas prestables deben utilizar Préstamos.

## Préstamo y devoluciones parciales

1. Registrar préstamo de dos herramientas prestables a un trabajador. Seleccionar manualmente sus ubicaciones de retiro. El vencimiento es opcional.
2. Confirmar después de efectuar la entrega física. Comprobar ausencia en almacén, custodia y responsable.
3. Intentar prestar la misma identidad a otro trabajador: rechazo.
4. Intentar reingresarla mediante remito externo: rechazo mientras haya préstamo vigente.
5. Registrar una devolución del préstamo con una herramienta y destino manual válido, diferente del retiro si corresponde.
6. Confirmar su recepción. Solo ese ítem queda devuelto; el préstamo queda parcialmente pendiente.
7. Desactivar el trabajador mediante una operación autorizada. La obligación restante sigue visible y puede devolverse.
8. Devolver la segunda herramienta. Comprobar préstamo completamente devuelto, ingresos únicos y ubicación inicial del catálogo intacta.

## Referencias inactivas

1. Preparar la devolución desde el préstamo pendiente. Elegir destino activo, almacenable y con asociación existente.
2. Intentar confirmar una herramienta cuyo producto, identidad, unidad de medida o asociación esté inactivo. Debe requerir autorización.
3. Solicitar regularización con motivo. El Encargado no puede aprobar ni revocar administrativamente la autorización por defecto.
4. Administrador revisa herramienta, préstamo, destino y referencias; aprueba con motivo. Puede ser la misma persona que preparó si posee los permisos.
5. Confirmar físicamente dentro de las 24 horas. Comprobar ingreso, cierre y utilización de autorización en una transacción.
6. Comprobar que no se reactivó ningún registro. El saldo físico aumenta, pero la herramienta inactiva no queda disponible para nuevas salidas.
7. Revisar rechazo de autorización revocada, caducada, destino modificado y reutilización. Editar el borrador invalida sus aprobaciones anteriores y conserva sus eventos.
8. Una ubicación inactiva no puede autorizarse como destino: debe elegirse otra válida o regularizarse expresamente el catálogo. No cerrar una obligación sin recibir físicamente la herramienta.

## Consulta y presentación

Consulta/Auditor puede leer documentos y disponibilidad, pero no escribir mediante botones ni solicitudes directas. Revisar los tres perfiles con permisos personalizados, no solo sus nombres iniciales. Comprobar historial con las cuatro fuentes, responsables, filtros, cancelaciones de borrador, errores en español, navegación por teclado, foco, móvil/escritorio y zoom al 200 %.

Registrar por caso responsable, fecha, resultado, incidencia y captura sin datos secretos. No marcar aceptación formal hasta conformidad expresa del responsable funcional.

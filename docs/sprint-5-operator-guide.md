# Sprint 5 — Recorrido personal pendiente

Guion preparado; no acredita pruebas de navegador ejecutadas ni aceptación formal. Autorizar primero un entorno de aceptación separado, con esquema S5 y datos mínimos. No usar la principal para demostraciones ni interferir con PHPUnit/testing.

1. Acceso: comprobar Administrador, Encargado y Consulta/Auditor con permisos configurables. Crear el primer administrador real únicamente con autorización específica; no hay contraseña predeterminada.
2. Conteo: recibir 10 unidades de un producto por cantidad, preparar ajuste a 8, comprobar que guardar/solicitar no cambia saldo. Otra persona autoriza y el Encargado ejecuta tras verificar físicamente el conteo. Saldo 8 y un movimiento de salida 2.
3. Protección: reintentar ejecución, preparar dos conteos competidores y revocar una autorización. Comprobar ausencia de doble efecto, rechazo de conteo obsoleto y rechazo de sesión con permisos revocados.
4. Compensación: seleccionar un documento confirmado con disponibilidad suficiente, comprobar líneas automáticas, motivo, autorización y ejecución total. Original y movimientos conservados; vínculo a compensación visible. No permitir saldo negativo ni compensación duplicada.
5. Custodia: prestar herramienta, devolverla, compensar esa devolución con recepción registrada incorrectamente y verificar que solo se reabre la obligación correspondiente. Una nueva devolución física debe cerrar el ciclo nuevo conservando el primero. Si hay un préstamo posterior, bloquear la compensación sin cascada.
6. Catálogo inactivo: no reactivar automáticamente ni reinterpretar unidades. La regularización de devolución del Sprint 4 conserva su flujo específico; una corrección ordinaria exige regularización expresa del catálogo antes de ejecutar.
7. Reportes: contrastar existencias, movimientos, remitos, entregas, préstamos, devoluciones, pendientes, correcciones y auditoría con los documentos. Probar filtros, corte de existencias, CSV con símbolos/decimales y texto con apariencia de fórmula.
8. Backup: solicitar, generar desde consola autorizada, verificar y descargar solo con permiso. Distinguir integridad de archivo de restauración comprobada. La restauración de ensayo requiere autorización propia y una base nueva separada; no ejecutarla como parte de un recorrido sin esa autorización.
9. Interfaz: escritorio/móvil, teclado, foco, zoom 200 %, mensajes en español, errores de formularios y confirmaciones. No se sustituye esta revisión con tests HTTP.

Registrar por caso: responsable, fecha/zona horaria, datos de prueba, esperado, obtenido, incidencia y captura sin secretos. Contrastar HU-24 a HU-29 y los cinco sprints con el catálogo definitivo de la tesis, todavía no aportado al repositorio. Adjuntar diagramas, diccionario, matriz requisito→historia→código→prueba→evidencia, manuales y retrospectiva antes del cierre académico.

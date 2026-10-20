# Sprint 5 — Control y cierre

Diseño aprobado el 20 de octubre de 2026. Rama `feature/sprint-5-control-and-close`, base `3d368275383ee472e1e58cdb57ed94b0a6e9cd96`. Implementación y pruebas exclusivamente en `sga_rio_grande_testing`. No autoriza migrar la principal, crear cuentas reales, restaurar bases operativas ni publicar.

HU-24 ajustes; HU-25 compensaciones totales sin cascadas; HU-26 reportes; HU-27 respaldos y ensayos aislados; HU-28 auditoría; HU-29 validación y cierre. La numeración y el catálogo académico externo siguen por contrastar: no se inventa esa evidencia.

Decisiones de implementación: autorización de 24 horas, solicitante distinto del autorizador, permisos separados, compensación total y atómica, fechas de contabilización actuales, reserva de apertura permanente. No se reactivan catálogos ni se crean identidades automáticamente. Referencias inactivas requieren regularización expresa del catálogo antes de una corrección ordinaria; las devoluciones S4 conservan su excepción específica.

Once tablas nuevas: inventory_corrections, inventory_correction_lines, inventory_correction_line_units, document_compensations, correction_authorizations, correction_authorization_events, loan_custody_cycles, loan_cycle_closures, critical_operation_events, backup_runs y backup_restore_rehearsals. Una quinta fuente del ledger, nunca un segundo saldo. Migraciones anteriores intactas.

Etapas: esquema/integridad; ajustes/autorización; ciclos y compensaciones; reportes; backup/auditoría; regresión y documentación. No avanzar una etapa dependiente con fallos relevantes. Las pruebas y resultados efectivos se registrarán separadamente de este plan.

El primer administrador y los recorridos humanos requieren autorizaciones específicas y datos del responsable. El SQL no reemplaza la custodia separada de APP_KEY/configuración/archivos. Retención automática y recuperación principal quedan sujetas a procedimiento administrativo; no se eliminan respaldos automáticamente.

## Autorización posterior de publicación

El 20 de octubre el usuario indicó: «todo esta bien de momento continuemos. y actualiza el repositorio». Autoriza publicar código y evidencias, conservando las reservas de aceptación humana y cierre académico. No autoriza aplicar migraciones principales ni ejecutar restauraciones. Véase [seguimiento de publicación](sprint-5-publication.md).

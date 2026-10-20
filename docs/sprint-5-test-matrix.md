# Sprint 5 — Trazabilidad y pruebas

Escrituras automatizadas únicamente en sga_rio_grande_testing, con controles de APP_ENV, configuración y SELECT DATABASE() previos a migrar. Nunca pruebas paralelas sobre ese esquema. Los ensayos reales de restauración del módulo quedan fuera de PHPUnit y requieren autorización operativa específica.

| Caso | Historia / requisito | Código | Prueba |
|---|---|---|---|
| AJU-01 | HU-24 conteo, precisión y delta SQL | InventoryCorrections::save/post | InventoryCorrectionsTest; ControlHttpTest |
| AJU-02 | conteo obsoleto, saldo no negativo, rollback | fingerprint/post | changed_count; insufficient_stock; carreras competing counts |
| AUT-01 | HU-24/25 solicitante distinto, vigencia, revocación | transition/execute | requires_different_authorizer; expiration; editing_invalidates |
| AUT-02 | autorización no revive tras regrant | ApprovalAccess | regranting_authorization_permission; permission_loss |
| AUT-03 | revocación con espera real y vista RR antigua | SecurityMutation/ApprovalAccess | CorrectionConcurrencyTest, nueve carreras |
| COM-01 | HU-25 compensación completa, original intacto, una vez | sourceMovements/execute | compensation_preserves_original; duplicate compensation; idempotent retry |
| COM-02 | préstamo compensado recibe y cierra físicamente | post/LoanCycles | loan_compensation_receives_tool |
| CUS-01 | devolución compensada reabre y vuelve a recibirse | LoanCycles/ConfirmReturn | return_compensation_reopens_cycle |
| CUS-02 | reapertura frente a préstamo nuevo, ambos órdenes | mutex/custodia | return compensation first; new loan first |
| SQL-01 | cinco fuentes, metadatos, pertenencia y UNIQUE | migraciones nuevas | ControlIntegrityTest; integridad global en MariaDB |
| SEG-01 | perfiles, solicitudes directas, sesiones y CSRF | Requests/Policies/Gates | ControlHttpTest; ControlIntegrityTest |
| REP-01 | HU-26 reportes, filtros, CSV y fórmulas | ControlReports/ReportController | reports_and_exports; csv_neutralizes_formulas |
| BAK-01 | HU-27 generación privada y credenciales efímeras | DatabaseBackups | DatabaseBackupsTest (cliente simulado); verify-sprint5-backup.php (cliente real) |
| BAK-02 | hash alterado, fallo, descarga y permisos | verify/download | tampering_and_failure; read_only_profile |
| BAK-03 | separación de aprobación y restauración | request/approve/restoreRehearsal | approval_are_distinct; PHPUnit rechaza crear otros esquemas |
| AUD-01 | HU-28 autoría, motivos y secuencia conservada | CriticalAudit/events | pruebas de correcciones y respaldos; reporte audit |
| MIG-01 | historial S3/S4, backfill y custodia pendiente | migraciones 1–5 | ensayo único verify-sprint5-upgrade.php desde 35 migraciones |
| MIG-02 | pertenencia reforzada sin editar lo anterior | migraciones 6–8 | regresión y SQL sobre esquema actualizado; ensayo completo desde copia antigua reservado para predespliegue |
| REG-01 | HU-29 regresión Sprints 1–5 | suite completa | resultado final registrado en informe/evidencia, sin sustituirlo por sumas de tandas |
| HUM-01 | aceptación funcional personal | Blade y guion | aceptación declarada por el usuario; ejecución detallada y capturas pendientes, no inferidas de HTTP ni PHPUnit |

## Ejecuciones históricas de desarrollo

- Regresión de ciclos S4: 9 pruebas/40 aserciones aprobadas.
- Primer núcleo de correcciones: 4 pruebas/20 aserciones aprobadas.
- Primera tanda HTTP/SQL: 9 pruebas/43 aserciones, un error y dos fallos. Se corrigieron cierre Blade adyacente, selección vacía convertida a NULL y comparación de fixture recién creado con recargado.
- Repetición HTTP/SQL: 5 pruebas/78 aserciones aprobadas.
- Tanda ampliada: 12 pruebas/117 aserciones, un fallo por locale. Se indicó es explícitamente en las traducciones nuevas, sin tocar .env.
- Carreras iniciales y respaldos: 11 pruebas/126 aserciones aprobadas (ocho carreras y tres casos de backup).
- Correcciones, HTTP, SQL y backup posteriores: 16 pruebas/133 aserciones aprobadas.
- Generación real aislada: cliente MariaDB 10.4.32, SQL de 235.199 bytes/53 tablas, SHA-256 1db1b559ab35a81626f6846a218f911720f677bee7acaec0a66e6e76ee1432d9. Captura con 41 migraciones, anterior a las dos últimas restricciones. No se ejecutó una restauración real del módulo.
- Tanda focalizada final: **28 pruebas/262 aserciones aprobadas**, incluidas nueve carreras y regrant que no revive aprobación.
- Regresión completa con manifiesto congelado: **429 pruebas/3.406 aserciones**, cero errores, fallos u omisiones. No modifica ni sustituye el resultado histórico S4 de 401 pruebas.
- Ajustes posteriores de presentación en español y filtros visibles: **3 pruebas HTTP/75 aserciones aprobadas**. Solo cambian dos vistas, el archivo de idioma y sus aserciones; la lógica de inventario y permisos permanece idéntica a la suite completa.
- Generación real sobre el esquema final de 43 migraciones: **238.775 bytes/53 tablas**, SHA-256 c24c438669857b787697c77139a297fbe3b0c22aabc1992f9cfa2fe46c26e842. Integridad 166 FK/103 CHECK/143 índices únicos; cero incumplimientos. Sin restauración real ni cambios principales.

Estas tandas se superponen y no se suman como casos distintos. JUnit/logs originales fuera del repositorio: C:\Users\jcsem\SGA-evidence\sprint-5. El informe final distinguirá fuente congelada, regresión completa y reservas.

Resultados completos, hashes y conservación: [evidencia agregada](evidence/sprint-5-implementation.json). La [aceptación declarada](sprint-5-acceptance.md) se registra por confirmación expresa del usuario. Las evidencias por caso de navegador, el ensayo real del módulo de restauración y el contraste del catálogo académico siguen pendientes; no se marcan ejecutados a partir de PHPUnit.

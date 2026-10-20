# Sprint 4 — Entregas, préstamos y devoluciones

## Autorización y límites

Diseño funcional y técnico aprobado por el usuario el 19 de octubre de 2026. Se autoriza implementar HU-18 a HU-23 y probar exclusivamente en `sga_rio_grande_testing`. Rama `feature/sprint-4-worker-operations`, base `cd7b4eabc031`. No autoriza migraciones principales, commits, merges ni push. No modificar `.env`, dependencias o migraciones anteriores. No implementar ajustes ni anulaciones del Sprint 5.

## Reglas aprobadas

- Entregas: productos por cantidad e individuales no prestables. Préstamos: herramientas individuales prestables.
- Un trabajador por documento; los trabajadores son independientes de los usuarios.
- Confirmar representa la operación física efectiva. Borradores sin movimientos ni reservas.
- Devoluciones parciales por identidad; un préstamo por devolución; destino elegido manualmente.
- Historial único en `inventory_movements`, saldo físico único en `stock_positions`.
- Alternativa A: cuatro fuentes explícitas con FK compuestas, CHECK de exclusividad y UNIQUE por origen.
- Custodia exclusiva por herramienta; cierre único del ítem con FK al movimiento físico de devolución.
- Catálogo inicial histórico intacto. Trabajadores inactivos conservan obligaciones.
- Regularización administrativa específica para referencias inactivas, sin reactivar registros automáticamente. Destino activo, almacenable, jerarquía válida y asociación existente. Autorización revocable y de uso único; no exige dos personas distintas.

## Etapas y dependencias

1. Estabilización y diagnóstico de TEST-S3-01; casos de borde CSV.
2. Seis migraciones nuevas; once tablas; ensayo con historial representativo S3 en testing.
3. Entregas y confirmación.
4. Préstamos y custodia.
5. Devoluciones y regularización.
6. Consultas, permisos, regresión y evidencia.

No avanzar a una etapa dependiente si hay fallos relevantes sin resolver. Los resultados ejecutados se documentarán por separado del plan. La aceptación humana no se deduce de pruebas HTTP o SQL.

## Historias

| Historia | Criterio verificable |
|---|---|
| HU-18 | Borradores de entrega validados, editables y cancelables sin afectar stock |
| HU-19 | Confirmación atómica e idempotente, selección manual de retiro y saldo no negativo |
| HU-20 | Préstamo individual, disponibilidad física y custodia exclusiva |
| HU-21 | Devolución por ítem original, parcial/total, cierre único e ingreso físico inseparables |
| HU-22 | Responsabilidades históricas y pendientes por trabajador |
| HU-23 | Disponibilidad por producto, ubicación e identidad; distinguir saldo físico y disponibilidad operativa |

## Estado de reservas heredadas

TEST-S3-01: error histórico de timeout en la regresión de 363 casos. Al iniciar S4 su causa no estaba determinada. Se conservaron el timeout de 120 segundos y la observación real de espera SQL, añadiendo diagnóstico de fase y terminación prematura. La revisión posterior correlacionó el JUnit original con eventos de suspensión de Windows: caso estimado 13:34:07–13:48:50 y suspensión 13:34:09–13:48:25 (Bolivia). Un timeout nuevo de reintento de devolución también coincidió con suspensión. Véase [evidencia](evidence/sprint-4-stability.json). Las ventanas de casos son aproximadas; los tiempos SleepTime/WakeTime proceden del registro de Windows. No se reescriben los resultados históricos.

Se ejecutó una repetición focalizada de 47 pruebas/400 aserciones sin errores, usando una solicitud temporal de continuidad de ejecución a Windows. No se cambió el plan de energía ni la política de ejecución. La regresión completa final se registra separadamente.

CSV: casos nuevos ejecutados para 1 MiB, 1000 filas, 500 combinaciones, BOM, UTF-8 inválido, NUL, estructura y precisión decimal. La primera tanda encontró NUL eliminado por trim antes de comprobarlo; se corrigió. La repetición conjunta con importación y carreras de productos aprobó 14 pruebas/168 aserciones. Las pruebas humanas siguen separadas.

## Despliegue

Las seis migraciones S4 se aplicaron con autorización el 20 de octubre de 2026 en lote 14, con respaldo nuevo y restauración de ensayo verificada. Principal: 42 tablas, 35 migraciones, ninguna pendiente, 81 permisos y 166 vínculos. Véase [despliegue](sprint-4-deployment.md). La autorización inicial de este documento es histórica y fue ampliada por autorizaciones posteriores. No repetir las migraciones. El DDL de MariaDB no es una transacción reversible; cualquier intervención futura requiere procedimiento y autorización propios.

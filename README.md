# SGA CONFIPETROL

Sistema Web de Gestión de Almacén para CONFIPETROL S.A.S., Planta Separadora de Líquidos Río Grande.

## Estado actual — equipo D: (25 de octubre de 2026)

Los cinco sprints están implementados. Esta instalación es nueva: **53 tablas y 43 migraciones en lote 1**, sin pendientes; no se restauraron los datos del equipo anterior. El administrador local se creó el 24 de octubre. La última regresión de estabilización aprobó **458 pruebas/3.485 aserciones**, exclusivamente en testing. Los resultados anteriores de 429/3.406 (PC anterior) y 429/3.414 (preparación de este PC) son históricos y se conservan por separado.

Consulta el [informe de estabilización](docs/environment-stabilization-2026-10-25.md), el [manual de instalación](docs/installation.md), el [guion de aceptación](docs/final-functional-acceptance.md) y la [propuesta de matriz académica](docs/academic-traceability-proposal.md). La aceptación visual final y el contraste académico siguen pendientes. No se inicia otro sprint.

## Entorno actual

El panel visual incorpora [gráficas operativas](docs/dashboard-charts-2026-10-25.md): actividad de siete días, entradas/salidas de 30 días y herramientas pendientes por trabajador, según permisos. La regresión completa posterior, que incluye apariencia y gráficas, aprobó **472 pruebas/3.562 aserciones** exclusivamente en testing. No se alteraron los datos principales ni se publicaron estos cambios locales.

- Laravel 13.35.0 y PHP 8.3 o superior.
- MariaDB 10.4.32, base de desarrollo `sga_rio_grande`.
- Blade y CSS local; el arranque actual no requiere npm ni compilación.
- Consola: `D:\PHP83\php.exe` (8.3.36). El `php` del PATH resuelve a XAMPP 8.2.12; usar el ejecutable explícito. No mezclar sus extensiones ni modificar el PATH global como parte del arranque.
- Binarios MariaDB: detección en la unidad de PHP/XAMPP y PATH, o variable de proceso `SGA_MARIADB_BIN`. Serie compatible configurada: 10.4; validación mediante `--version`. Diagnóstico sin SQL: `D:\PHP83\php.exe artisan sga:check-backup-client`.

## Historial de los sprints — equipo anterior

**Sprint 5 — publicado, aceptado y desplegado con autorización:** diseño aprobado e implementación de ajustes, compensaciones, ciclos de custodia, reportes, respaldos y auditoría. Pruebas exclusivamente en testing: **429 pruebas/3.406 aserciones aprobadas**, más 3 pruebas HTTP/75 aserciones posteriores de presentación. Testing tiene 53 tablas/43 migraciones. Las ocho migraciones S5 se aplicaron en lote 15: principal con 53 tablas, 43 migraciones, ninguna pendiente, 123 permisos y 260 vínculos. Véase [despliegue e incidente resuelto](docs/sprint-5-deployment.md). Aceptación declarada por el usuario; véase [registro de aceptación](docs/sprint-5-acceptance.md). Sin cuentas reales nuevas. Véase [seguimiento de publicación](docs/sprint-5-publication.md). Véanse [informe S5](docs/sprint-5-implementation.md), [diccionario](docs/data-dictionary-sprint-5.md), [matriz](docs/sprint-5-test-matrix.md) y [recuperación](docs/sprint-5-backup-recovery.md).

## Sprint 4 — Publicado y desplegado con aceptación declarada

**Sprint 4:** HU-18 a HU-23 implementadas, publicadas y desplegadas el 20 de octubre de 2026 con autorización, respaldo nuevo y restauración de ensayo verificados. Estado histórico al desplegar S4: **42 tablas, 35 migraciones aplicadas, ninguna pendiente, 81 permisos y 166 relaciones rol–permiso**. El estado vigente tras S5 figura arriba. Las seis migraciones se aplicaron en lote 14; no repetirlas. Véanse [despliegue y evidencia](docs/sprint-4-deployment.md), [implementación](docs/sprint-4-implementation.md), [diccionario](docs/data-dictionary-sprint-4.md) y [matriz](docs/sprint-4-test-matrix.md). Regresión previa exclusiva en testing: **401 pruebas/3.143 aserciones**; no se ejecutaron pruebas automatizadas en la principal. La aceptación declarada conserva las reservas de evidencia humana. Los conteos de Sprints 2 y 3 que aparecen más abajo son históricos.

Recepción mediante remitos, borradores, confirmación transaccional e idempotente, saldos por ubicación, trazabilidad individual y apertura manual/CSV. Véanse [alcance](docs/sprint-3.md), [implementación y migraciones pendientes](docs/sprint-3-implementation.md), [diccionario](docs/data-dictionary-sprint-3.md) y [matriz](docs/sprint-3-test-matrix.md).

Las dos migraciones del Sprint 3 se aplicaron con autorización, respaldo y restauración de ensayo en lote 13. Su estado histórico fue 31 tablas, 29 migraciones, 62 permisos y 126 vínculos. No repetir sus migraciones. Véase [despliegue S3](docs/sprint-3-deployment.md). El Sprint 4 amplió después el esquema en lote 14. El seguimiento de TEST-S3-01 y los casos CSV figura en la [matriz S4](docs/sprint-4-test-matrix.md); las revisiones personales sin evidencia permanecen pendientes.

## Sprint 1

Orden implementado: HU-01 (autenticación), HU-03 (roles), HU-02 (usuarios), HU-04 (trabajadores). Consulta [el alcance y los criterios](docs/sprint-1.md).

| Operación | Administrador | Encargado | Consulta/Auditor |
|---|---|---|---|
| Acceder y consultar trabajadores | Sí | Sí | Sí |
| Registrar, editar y cambiar estado de trabajadores | Sí | Sí | No |
| Gestionar usuarios y asignar roles | Sí | No | No |
| Configurar roles y permisos | Sí | No | No |
| Consultar auditoría administrativa | Sí | No | Sí |

La tabla muestra la configuración inicial. Los roles y sus permisos son configurables, manteniendo un rol por usuario. No hay registro público ni rutas de eliminación. Los trabajadores son independientes de las cuentas de usuario.

## Sprint 2

**Validación funcional del 19 de octubre:** [informe de aceptación](docs/sprint-2-acceptance.md), matriz AF-01 a AF-36 y evidencias reales. Regresión completa histórica: **315 pruebas y 2.409 aserciones**, sin fallos, exclusivamente en testing. El seguimiento de UI-S2-02 añade una vista 419 en español y **39 pruebas seleccionadas con 306 aserciones aprobadas**, incluidas cuatro nuevas; mantiene íntegra la protección CSRF. Hay dos capturas posteriores, sin nuevos datos demostrativos ni cambios en la principal. El [guion personal de aceptación](docs/sprint-2-human-acceptance.md) está listo: recorrido asistido y corrección técnica no sustituyen la aceptación humana documentada. Posteriormente el usuario declaró conformidad para avanzar, con reservas de evidencia personal pendientes. Sprint 3 autorizado en rama de trabajo.

**Actualización del 19 de octubre de 2026:** HU-10 implementada, probada y aplicada a `sga_rio_grande` con autorización y respaldo nuevo verificado, lote 12. Estado al cierre del Sprint 2: **24 tablas, 27 migraciones aplicadas, sin pendientes del Sprint 2, 48 permisos y 95 vínculos**. Consulta la [Etapa 5](docs/sprint-2-stage-5.md).

El [correctivo HU-03](docs/sprint-1-hu03-corrective.md) y las historias HU-05/HU-06/HU-07/HU-09 están implementados e integrados en `main`, con migraciones principales autorizadas y respaldadas. Consulta la [Etapa 1](docs/sprint-2-stage-1.md), la [Etapa 2: atributos por categoría](docs/sprint-2-stage-2.md) y la [Etapa 3: ubicaciones](docs/sprint-2-stage-3.md).

HU-09 se desarrolló en `sprint-2/hu09-ubicaciones` y sus dos migraciones principales se aplicaron con autorización y respaldo el 14 de octubre de 2026, lote 6. HU-08 añade productos, valores técnicos con revisiones y asociaciones de ubicaciones: sus cinco migraciones principales se aplicaron con autorización y respaldo verificado el **18 de octubre de 2026**, lotes 7 a 11. Consulta la [Etapa 4](docs/sprint-2-stage-4.md). Al cierre del Sprint 2 la principal tenía 27 migraciones aplicadas, sin pendientes de ese sprint, 24 tablas, 48 permisos y 95 vínculos. La regresión histórica de esta entrega, ejecutada el 19 de octubre exclusivamente en testing, aprobó 315 pruebas y 2.411 verificaciones. HU-10 ya se aplicó a la principal; la aceptación visual permanece pendiente. Los resultados actuales figuran en [Etapa 5](docs/sprint-2-stage-5.md).

## Diagnóstico y planificación

- [Validación del Sprint 1](docs/sprint-1-validation.md): estado verificado, resultados de pruebas y diferencias con los requerimientos.
- [Propuesta del Sprint 2](docs/sprint-2.md): historias, permisos, interfaces, tareas y entregables.
- [Diccionario de datos propuesto](docs/data-dictionary-sprint-2.md): esquema físico, relaciones e invariantes.
- [Matriz de pruebas del Sprint 2](docs/sprint-2-test-matrix.md): evidencia por etapa y casos todavía planificados.
- [Validación y preparación del cierre del Sprint 2](docs/sprint-2-acceptance.md): resultados actuales, capturas, incidencias y aceptación humana pendiente.
- [Planificación global de cinco sprints](docs/global-plan.md): distribución provisional y dependencias.

El diseño de `product_locations`, las revisiones de valores y el control de concurrencia de HU-08 fueron aprobados. La comparación exacta de identificadores genera candidatos para revisión humana; no certifica equivalencia, no fusiona productos y no convierte unidades.

Las etapas entregadas y migradas a la principal comprenden HU-05/HU-06/HU-07/HU-09/HU-08/HU-10. Categorías, unidades, atributos, ubicaciones, productos, valores, revisiones, asociaciones y unidades individualizadas tienen sus tablas y permisos aplicados. La suite PHPUnit y las pruebas de integridad permanecen aisladas en `sga_rio_grande_testing`; el recorrido asistido en navegador utiliza el entorno adicional de aceptación expresamente autorizado. Las verificaciones posteriores de HU-08 y HU-10 en la principal fueron de solo lectura, sin crear datos de prueba.

## Arranque local

**Continuidad en otro PC (23 de octubre):** consulta el [registro del entorno local](docs/local-environment-2026-10-23.md). En este equipo se utiliza `D:\PHP83\php.exe`; los registros de despliegue anteriores describen el PC de origen.

Desde la raíz con PHP 8.3 de este equipo:

```powershell
& "D:\PHP83\php.exe" --version
& "D:\PHP83\php.exe" artisan serve --host=127.0.0.1 --port=8000
```

La principal de este equipo tiene **43 migraciones aplicadas en lote 1, ninguna pendiente**. Los lotes 14 y 15 de los [despliegues S4](docs/sprint-4-deployment.md) y [S5](docs/sprint-5-deployment.md) corresponden al equipo anterior; no se repiten aquí. El administrador local ya existe; las siguientes cuentas se gestionan desde el sistema. No se publican credenciales ni se ejecuta el comando de creación como parte del arranque.

Abre `http://127.0.0.1:8000/login`. Apartados de las etapas publicadas: `/panel`, `/usuarios`, `/roles`, `/trabajadores`, `/auditoria`, `/categorias`, `/unidades-de-medida`, `/atributos-tecnicos`, `/categorias/{category}/atributos`, `/ubicaciones` y `/ubicaciones/arbol`, según permisos.

HU-08 tiene sus tablas y permisos aplicados: `/productos` y `/productos/{product}/ubicaciones` están disponibles según las capacidades del usuario autenticado.

HU-10 tiene sus tablas y permisos aplicados: `/unidades-individualizadas` está disponible según las capacidades del usuario autenticado.

**Configuración local:** `APP_KEY` se generó con autorización específica y se verificó que fuera el único cambio en `.env`. Laravel utiliza esa clave para cifrar las cookies de sesión. No regeneres una clave existente sin autorización. Las pruebas usan una clave temporal independiente.

Las migraciones anteriores se conservan intactas. No ejecutar seeders ni comandos de migración sobre la principal durante la estabilización.

Evita `composer setup`: instala dependencias, cambia configuración y ejecuta migraciones. El arranque actual utiliza el servidor integrado de PHP; no necesita `composer dev` ni Vite. No se verificó ni actualizó Node en esta estabilización.

## Pruebas

```powershell
& "D:\PHP83\php.exe" vendor/bin/phpunit --colors=never
& "D:\PHP83\php.exe" vendor/bin/pint --test app config scripts tests
```

Se usa exclusivamente `sga_rio_grande_testing`, que debe existir previamente, con servidor y credenciales locales o variables proporcionadas al proceso. El usuario MariaDB necesita permiso para aplicar sus migraciones. No se copian ni borran datos de `sga_rio_grande`.

El bootstrap rechaza configuración cacheada y destinos no verificados, exige que testing exista y genera una clave efímera. Laravel utiliza exclusivamente el alias `sga_testing`; el selector de entrada `DB_CONNECTION=mysql` del XML no conserva una conexión Laravel llamada `mysql`. Se verifican alias, nombre interno, host, puerto, `SELECT DATABASE()`, servidor y directorio real de datos antes de consultar o escribir. Los siete procesos concurrentes heredan la identidad comprobada. Se aplican migraciones normales solo en testing una vez por ejecución y se aíslan los casos mediante transacciones y limpieza por identificadores propios. Véase el [procedimiento de aislamiento](docs/installation.md#pruebas-aisladas).

Ejecuta la suite en un solo proceso, sin ejecuciones paralelas sobre esa base. Se conservan las barreras entre procesos, las comprobaciones de bloqueos InnoDB y los límites de tiempo existentes. No ejecutar los ensayos operativos históricos de actualización ni Backup como parte de PHPUnit.

## Restricciones

El código y el esquema local incluyen Sprints 1–5: ajustes, compensaciones, reportes, Backup y auditoría, además del inventario y las operaciones con trabajadores. La portabilidad de Backup se validó sin generar ni restaurar respaldos reales. En esta estabilización no se modifica `.env`, no se regeneran claves, no se actualizan dependencias y no se escribe en la principal. Commit y push requieren autorización posterior.

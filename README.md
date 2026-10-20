# SGA CONFIPETROL

Sistema Web de Gestión de Almacén para CONFIPETROL S.A.S., Planta Separadora de Líquidos Río Grande.

## Entorno

- Laravel 13.35.0 y PHP 8.3 o superior.
- MariaDB 10.4.32, base de desarrollo `sga_rio_grande`.
- Blade y CSS local para las historias implementadas de los Sprints 1 y 2; no se requiere npm ni compilación.
- La consola utiliza PHP 8.3.10 de Laragon. Apache de XAMPP carga PHP 8.2.12 y no puede ejecutar Laravel 13. Usa el servidor integrado de PHP 8.3 manteniendo MariaDB de XAMPP.

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

Desde la raíz con PHP 8.3 de Laragon:

```powershell
php --version
php artisan sga:create-admin
php artisan serve --host=127.0.0.1 --port=8000
```

La base principal local tiene **43 migraciones aplicadas, ninguna pendiente de Sprints 1–5**. Las ocho de S5 se aplicaron en lote 15, con respaldo nuevo, restauración de ensayo y autorización; véase [despliegue S5](docs/sprint-5-deployment.md). Las seis del Sprint 4 se aplicaron con autorización, respaldo nuevo y restauración verificada; no repetirlas. Véase [despliegue S4](docs/sprint-4-deployment.md). Otras aplicaciones requieren prevalidación, respaldo y autorización propios. El comando de administrador solicita nombre, correo y contraseña oculta de al menos 12 caracteres con mayúscula, minúscula y número. Solo crea la primera cuenta con acceso administrativo completo; las siguientes se gestionan desde el sistema. La principal continúa sin cuentas reales: su creación explícita está pendiente.

Abre `http://127.0.0.1:8000/login`. Apartados de las etapas publicadas: `/panel`, `/usuarios`, `/roles`, `/trabajadores`, `/auditoria`, `/categorias`, `/unidades-de-medida`, `/atributos-tecnicos`, `/categorias/{category}/atributos`, `/ubicaciones` y `/ubicaciones/arbol`, según permisos.

HU-08 tiene sus tablas y permisos aplicados: `/productos` y `/productos/{product}/ubicaciones` están disponibles según las capacidades del usuario autenticado.

HU-10 tiene sus tablas y permisos aplicados: `/unidades-individualizadas` está disponible según las capacidades del usuario autenticado.

**Configuración local:** `APP_KEY` se generó con autorización específica y se verificó que fuera el único cambio en `.env`. Laravel utiliza esa clave para cifrar las cookies de sesión. No regeneres una clave existente sin autorización. Las pruebas usan una clave temporal independiente.

Las cuatro migraciones originales del Sprint 1 y las cinco del correctivo son aditivas. La migración original del rol obligatorio se detiene si encuentra usuarios previos: hay que planificar su asignación explícita. El correctivo conserva identificadores y asignaciones existentes. `php artisan db:seed` asegura registros del catálogo sin crear cuentas o trabajadores, conceder permisos ni restaurar revocaciones.

Evita `composer setup`: el script inicial instala dependencias, cambia configuración y ejecuta migraciones. `composer dev` intenta iniciar Vite, incompatible con Node 18.8.0. Utiliza `php artisan serve` para este Sprint.

## Pruebas

```powershell
php vendor/bin/phpunit
php vendor/bin/pint --test app routes tests
```

Se usa exclusivamente `sga_rio_grande_testing`, que debe existir previamente, con servidor y credenciales locales o variables proporcionadas al proceso. El usuario MariaDB necesita permiso para aplicar sus migraciones. No se copian ni borran datos de `sga_rio_grande`.

El bootstrap rechaza configuración cacheada, valida el entorno, exige que la base exclusiva exista y genera una clave efímera. Antes de migrar, comprueba la configuración y `SELECT DATABASE()`. Aplica migraciones normales una vez por ejecución y aísla los casos con transacciones, sin `RefreshDatabase` ni `migrate:fresh`. Las pruebas de concurrencia usan procesos independientes con datos de prueba confirmados; limpian exclusivamente los identificadores o códigos únicos que crearon. HU-09 añade cuatro carreras a las dos administrativas existentes. Su informe detalla también la verificación de estilo de las migraciones nuevas, sin reformatear las anteriores.

HU-08 añade siete carreras de productos a las seis anteriores; HU-10 incorpora once más: veinticuatro escenarios concurrentes en total. Ejecuta la suite en un solo proceso, sin pruebas paralelas sobre esa misma base. El total de aserciones puede variar según las ramas válidas de las carreras de ubicaciones; los resultados y la diferencia con la ejecución anterior se explican en el informe de aceptación.

## Restricciones

La principal incluye acceso, personas, catálogo, recepción, remitos, apertura, existencias, entregas, préstamos, devoluciones, responsabilidades y disponibilidad de Sprints 1–4. No incluye ajustes ni anulaciones críticas del Sprint 5. La auditoría administrativa conserva su alcance; las regularizaciones de devolución tienen historial propio. No se instalan paquetes, no se actualiza Node y no se modifica `.env`.

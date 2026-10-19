# SGA CONFIPETROL

Sistema Web de Gestión de Almacén para CONFIPETROL S.A.S., Planta Separadora de Líquidos Río Grande.

## Entorno

- Laravel 13.35.0 y PHP 8.3 o superior.
- MariaDB 10.4.32, base de desarrollo `sga_rio_grande`.
- Blade y CSS local para las historias implementadas de los Sprints 1 y 2; no se requiere npm ni compilación.
- La consola utiliza PHP 8.3.10 de Laragon. Apache de XAMPP carga PHP 8.2.12 y no puede ejecutar Laravel 13. Usa el servidor integrado de PHP 8.3 manteniendo MariaDB de XAMPP.

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

**Validación funcional del 19 de octubre:** [informe de aceptación](docs/sprint-2-acceptance.md), matriz AF-01 a AF-36 y evidencias reales. Nueva regresión: **315 pruebas y 2.409 aserciones**, sin fallos, exclusivamente en testing. Se preparó un entorno adicional de aceptación con datos demostrativos autorizados; la principal permanece intacta. El recorrido asistido y las capturas no sustituyen la aceptación humana, todavía pendiente. Se corrigió la leyenda española de unidades individualizadas en roles; la página genérica 419 en inglés queda como incidencia menor abierta. Sprint 3 no iniciado.

**Actualización del 19 de octubre de 2026:** HU-10 implementada, probada y aplicada a `sga_rio_grande` con autorización y respaldo nuevo verificado, lote 12. Estado actual: **24 tablas, 27 migraciones aplicadas, ninguna pendiente, 48 permisos y 95 vínculos**. Consulta la [Etapa 5](docs/sprint-2-stage-5.md).

El [correctivo HU-03](docs/sprint-1-hu03-corrective.md) y las historias HU-05/HU-06/HU-07/HU-09 están implementados e integrados en `main`, con migraciones principales autorizadas y respaldadas. Consulta la [Etapa 1](docs/sprint-2-stage-1.md), la [Etapa 2: atributos por categoría](docs/sprint-2-stage-2.md) y la [Etapa 3: ubicaciones](docs/sprint-2-stage-3.md).

HU-09 se desarrolló en `sprint-2/hu09-ubicaciones` y sus dos migraciones principales se aplicaron con autorización y respaldo el 14 de octubre de 2026, lote 6. HU-08 añade productos, valores técnicos con revisiones y asociaciones de ubicaciones: sus cinco migraciones principales se aplicaron con autorización y respaldo verificado el **18 de octubre de 2026**, lotes 7 a 11. Consulta la [Etapa 4](docs/sprint-2-stage-4.md). La principal tiene 27 migraciones aplicadas, ninguna pendiente, 24 tablas, 48 permisos y 95 vínculos. La última regresión, ejecutada el 19 de octubre exclusivamente en testing, aprobó 315 pruebas y 2.411 verificaciones. HU-10 ya se aplicó a la principal; la aceptación visual permanece pendiente. Los resultados actuales figuran en [Etapa 5](docs/sprint-2-stage-5.md).

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

La base principal local tiene **27 migraciones aplicadas y ninguna pendiente**, incluidas las cinco del correctivo HU-03 y las quince de HU-05/HU-06/HU-07/HU-09/HU-08/HU-10. Las Etapas 4 y 5 registran los respaldos y las verificaciones de HU-08 y HU-10. Otras aplicaciones de migraciones requieren su propia prevalidación, respaldo y autorización. El comando de administrador solicita nombre, correo y contraseña oculta de al menos 12 caracteres con mayúscula, minúscula y número. Solo crea la primera cuenta con acceso administrativo completo; las siguientes se gestionan desde el sistema. La base principal local sigue sin cuentas: esta creación explícita está pendiente.

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

El alcance implementado incluye el catálogo de productos y sus valores técnicos, con historial de revisiones, asociaciones a ubicaciones sin cantidades y unidades individualizadas de HU-10. Las dos migraciones de HU-10 se aplicaron con autorización y respaldo el 19 de octubre de 2026. No incluye inventario, movimientos, remitos, entregas o préstamos. La auditoría administrativa conserva su alcance de acceso; el historial técnico usa su propia tabla. No se instalan paquetes, no se actualiza Node y no se modifica `.env` sin autorización específica. MariaDB 10.4 está fuera de mantenimiento; su actualización debe planificarse antes de producción.

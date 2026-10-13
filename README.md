# SGA CONFIPETROL

Sistema Web de Gestión de Almacén para CONFIPETROL S.A.S., Planta Separadora de Líquidos Río Grande.

## Entorno

- Laravel 13.35.0 y PHP 8.3 o superior.
- MariaDB 10.4.32, base de desarrollo `sga_rio_grande`.
- Blade y CSS local para el Sprint 1; no se requiere npm ni compilación.
- La consola utiliza PHP 8.3.10 de Laragon. Apache de XAMPP carga PHP 8.2.12 y no puede ejecutar Laravel 13. Usa el servidor integrado de PHP 8.3 manteniendo MariaDB de XAMPP.

## Sprint 1

Orden implementado: HU-01 (autenticación), HU-03 (roles), HU-02 (usuarios), HU-04 (trabajadores). Consulta [el alcance y los criterios](docs/sprint-1.md).

| Operación | Administrador | Encargado | Consulta/Auditor |
|---|---|---|---|
| Acceder y consultar trabajadores | Sí | Sí | Sí |
| Registrar, editar y cambiar estado de trabajadores | Sí | Sí | No |
| Gestionar usuarios y asignar roles | Sí | No | No |

Los roles son un catálogo fijo. No hay registro público ni rutas de eliminación. Los trabajadores son independientes de las cuentas de usuario.

La validación del 12 de octubre de 2026 confirmó una brecha de HU-03: el requisito original exige roles y permisos configurables. Esa corrección y el Sprint 2 aún no están implementados ni aprobados para programación.

## Diagnóstico y planificación

- [Validación del Sprint 1](docs/sprint-1-validation.md): estado verificado, resultados de pruebas y diferencias con los requerimientos.
- [Propuesta del Sprint 2](docs/sprint-2.md): historias, permisos, interfaces, tareas y entregables.
- [Diccionario de datos propuesto](docs/data-dictionary-sprint-2.md): esquema físico, relaciones e invariantes.
- [Matriz de pruebas del Sprint 2](docs/sprint-2-test-matrix.md): casos previstos, pendientes de ejecución.
- [Planificación global de cinco sprints](docs/global-plan.md): distribución provisional y dependencias.

Publicar estos documentos no autoriza cambiar la arquitectura de permisos ni implementar el catálogo. La asociación `product_locations`, el correctivo de HU-03 y los criterios de equivalencia técnica siguen pendientes de aprobación.

## Arranque local

Desde la raíz con PHP 8.3 de Laragon:

```powershell
php --version
php artisan migrate
php artisan sga:create-admin
php artisan serve --host=127.0.0.1 --port=8000
```

El comando solicita nombre, correo y contraseña oculta de al menos 12 caracteres con mayúscula, minúscula y número. Solo crea el primer administrador activo; las siguientes cuentas se gestionan desde el sistema.

Abre `http://127.0.0.1:8000/login`. Apartados: `/panel`, `/usuarios` y `/trabajadores`, según permisos.

**Configuración local:** `APP_KEY` se generó con autorización específica y se verificó que fuera el único cambio en `.env`. Laravel utiliza esa clave para cifrar las cookies de sesión. No regeneres una clave existente sin autorización. Las pruebas usan una clave temporal independiente.

Las cuatro migraciones nuevas son aditivas y conservan las migraciones iniciales. La que añade el rol obligatorio se detiene si encuentra usuarios previos: hay que planificar su asignación explícita. El catálogo se crea con la migración. `php artisan db:seed` únicamente asegura ese catálogo, sin crear cuentas o trabajadores de ejemplo.

Evita `composer setup`: el script inicial instala dependencias, cambia configuración y ejecuta migraciones. `composer dev` intenta iniciar Vite, incompatible con Node 18.8.0. Utiliza `php artisan serve` para este Sprint.

## Pruebas

```powershell
php vendor/phpunit/phpunit/phpunit
php vendor/laravel/pint/builds/pint --test app bootstrap/app.php routes database tests
```

Se usa exclusivamente `sga_rio_grande_testing`, con servidor y credenciales locales o variables proporcionadas al proceso. El usuario MariaDB necesita permiso para crear esa base y aplicar sus migraciones. No se copian ni borran datos de `sga_rio_grande`.

El bootstrap rechaza configuración cacheada, valida el entorno, crea la base exclusiva si falta y genera una clave efímera. Antes de migrar, comprueba la configuración y `SELECT DATABASE()`. Aplica migraciones normales una vez por ejecución y aísla los casos con transacciones, sin `RefreshDatabase` ni `migrate:fresh`.

Ejecuta la suite en un solo proceso, sin pruebas paralelas sobre esa misma base.

## Restricciones

Este Sprint no incluye productos, inventario, movimientos, remitos, entregas ni un módulo de auditoría. No se instalan paquetes, no se actualiza Node y no se modifica `.env` sin autorización específica. MariaDB 10.4 está fuera de mantenimiento; su actualización debe planificarse antes de producción.

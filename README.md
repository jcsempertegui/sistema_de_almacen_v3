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

El [correctivo HU-03](docs/sprint-1-hu03-corrective.md) y las historias HU-05/HU-06/HU-07 están implementados e integrados en `main`, con migraciones principales autorizadas y respaldadas. Consulta la [Etapa 1](docs/sprint-2-stage-1.md) y la [Etapa 2: atributos por categoría](docs/sprint-2-stage-2.md).

HU-09 está implementada y probada en la rama `sprint-2/hu09-ubicaciones`, con sus dos migraciones aplicadas únicamente a `sga_rio_grande_testing`. Suite completa: **221 pruebas y 1.602 verificaciones aprobadas**. Su publicación y aplicación principal requieren autorización posterior. Consulta la [Etapa 3](docs/sprint-2-stage-3.md). HU-08 y HU-10 permanecen sin implementar.

## Diagnóstico y planificación

- [Validación del Sprint 1](docs/sprint-1-validation.md): estado verificado, resultados de pruebas y diferencias con los requerimientos.
- [Propuesta del Sprint 2](docs/sprint-2.md): historias, permisos, interfaces, tareas y entregables.
- [Diccionario de datos propuesto](docs/data-dictionary-sprint-2.md): esquema físico, relaciones e invariantes.
- [Matriz de pruebas del Sprint 2](docs/sprint-2-test-matrix.md): evidencia por etapa y casos todavía planificados.
- [Planificación global de cinco sprints](docs/global-plan.md): distribución provisional y dependencias.

La asociación `product_locations` y los criterios de equivalencia técnica del Sprint 2 siguen pendientes de aprobación. El correctivo de HU-03 tiene autorización independiente y no inicia ese Sprint.

Las etapas publicadas comprenden HU-05/HU-06/HU-07. Categorías, unidades y atributos ya tienen sus tablas y permisos en la base principal local. La autorización de implementación de HU-09 no incluye migrarla a la principal ni publicar sus cambios. Las pruebas automatizadas permanecen aisladas en `sga_rio_grande_testing`.

## Arranque local

Desde la raíz con PHP 8.3 de Laragon:

```powershell
php --version
php artisan sga:create-admin
php artisan serve --host=127.0.0.1 --port=8000
```

La base principal local tiene las 18 migraciones aplicadas, incluidas las cinco del correctivo HU-03 y las seis de HU-05/HU-06/HU-07. Las dos nuevas de HU-09 permanecen pendientes en la principal. En otros entornos deben aplicarse las migraciones normales con autorización del responsable. El comando solicita nombre, correo y contraseña oculta de al menos 12 caracteres con mayúscula, minúscula y número. Solo crea la primera cuenta con acceso administrativo completo; las siguientes se gestionan desde el sistema. La base principal local sigue sin cuentas: esta creación explícita está pendiente.

Abre `http://127.0.0.1:8000/login`. Apartados de las etapas publicadas: `/panel`, `/usuarios`, `/roles`, `/trabajadores`, `/auditoria`, `/categorias`, `/unidades-de-medida`, `/atributos-tecnicos` y `/categorias/{category}/atributos`, según permisos. Las pantallas `/ubicaciones` y `/ubicaciones/arbol` requieren el esquema y los permisos de HU-09; por ahora solo se validan en testing.

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

Ejecuta la suite en un solo proceso, sin pruebas paralelas sobre esa misma base.

## Restricciones

El alcance implementado no incluye productos, unidades individualizadas, inventario, movimientos, remitos ni entregas. HU-08/HU-10 y los sprints posteriores requieren su propia aprobación. La auditoría se limita a cambios administrativos de acceso; los catálogos conservan autoría y fechas. No se instalan paquetes, no se actualiza Node y no se modifica `.env` sin autorización específica. MariaDB 10.4 está fuera de mantenimiento; su actualización debe planificarse antes de producción.

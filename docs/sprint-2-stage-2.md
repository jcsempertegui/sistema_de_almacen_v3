# Sprint 2 — Etapa 2: HU-07, atributos técnicos por categoría

Fecha: 14 de octubre de 2026, America/La_Paz.

Estado: implementación autorizada y probada en la rama `sprint-2/hu07-atributos`, basada en `31d55fb44bfba859ac537ddc81b4368b8d46c987`. Las tres migraciones principales siguen pendientes. No se ha realizado commit, merge ni push.

## Revisión y alcance aprobado

Se leyeron `AGENTS.md`, el plan, el diccionario y la matriz de pruebas del Sprint 2. La revisión inicial encontró el árbol limpio y `main` sincronizada con GitHub. Laravel 13.35.0, PHP 8.3.10 y MariaDB 10.4.32; permisos configurables de HU-03 disponibles. HU-05/HU-06 tenían sus tres migraciones aplicadas en el lote 4 de la base principal.

La conexión Laravel y `SELECT DATABASE()` confirmaron `sga_rio_grande`: 16 tablas y 15 migraciones; 25 permisos, 43 vínculos de permisos, tres roles, seis registros de auditoría y cero usuarios/trabajadores/categorías/unidades. La base `sga_rio_grande_testing` existía, con 15 migraciones y cero usuarios; no había configuración cacheada. Se capturaron huellas SHA-256 de los datos y definiciones de todas las tablas principales y de `.env` para comprobar su conservación.

Se implementan únicamente definiciones de atributos y configuraciones por categoría. No se crean tablas, modelos, capturas ni rutas de productos, valores de productos, ubicaciones, unidades individualizadas, inventario, remitos o entregas. No se modifican dependencias, `.env`, autenticación ni las 15 migraciones anteriores.

## Tablas y reglas implementadas

Las tablas nuevas usan InnoDB, claves BIGINT UNSIGNED y `utf8mb4_unicode_ci`; `data_type` usa `utf8mb4_bin` para exigir códigos exactos. Campos y relaciones se detallan en [el diccionario](data-dictionary-sprint-2.md).

- `attributes`: código único normalizado a mayúsculas, nombre, tipo `text/decimal/boolean/date`, unidad técnica opcional, ayuda, estado, autores y timestamps. Nombre sin unicidad obligatoria.
- `category_attributes`: categoría, atributo, obligatoriedad, identificación técnica, orden 0–65535, mínimo/máximo DECIMAL(18,6), estado, autores y timestamps.
- FKs con RESTRICT para eliminación y modificación de claves; UNIQUE del código y de `(category_id,attribute_id)`, incluso en filas inactivas. UNIQUE de `(id,category_id)` prepara la futura FK compuesta de HU-08, sin crear la tabla de valores.
- CHECK de tipos, unidad solo decimal, booleanos y mínimo ≤ máximo. Las reglas entre tablas se comprueban en el servidor: MariaDB no admite resolverlas con CHECK y subconsultas.

Reglas conservadoras aprobadas:

1. Desde cualquier asociación, incluso inactiva, se conservan tipo y unidad del atributo. Nombre, ayuda y código pueden corregirse manteniendo el ID y la unicidad.
2. Desactivar una definición exige desactivar primero todas sus asociaciones activas. No se realiza una cascada automática.
3. Crear o reactivar una asociación exige categoría, atributo y unidad técnica activos. Se puede corregir el nombre/ayuda de una definición histórica inactiva conservando su unidad inactiva.
4. La categoría procede de la ruta; la asociación existente conserva categoría y atributo. El binding anidado devuelve 404 ante otra categoría y la acción vuelve a comprobarlo.
5. Límites solo para atributos decimales, con punto decimal y sin miles ni exponentes. Se comparan cadenas exactas, sin FLOAT, BCMath ni redondeo silencioso. Hasta 12 dígitos enteros y 6 decimales; se respeta la precisión de la unidad. Cero y negativos se admiten; los ceros finales no consumen precisión significativa.
6. No hay endpoints DELETE ni reutilización de asociaciones/códigos desactivados. Se reactiva la fila existente.
7. Las dependencias futuras se consultan mediante `AttributeReferences`: identificación/límites quedan bloqueados si hay valores históricos; convertir en obligatorio se bloquea ante productos activos incompletos. Las pruebas de estos dos comportamientos usan simulaciones, pues HU-08 no está implementada. La integración, cardinalidad, tipado y pertenencia de valores reales se deberán validar en HU-08.
8. Desactivar una categoría conserva los estados y las filas de su configuración; su actividad efectiva requiere también categoría y atributo activos. Se conserva el comportamiento aprobado de HU-05 respecto de productos.
9. Las referencias de atributos a unidades son ahora reales: impiden desactivar unidades usadas por atributos activos y cambiar código/símbolo/dimensión/precisión con cualquier referencia histórica.

## Autorización y concurrencia

| Recurso | Consultar | Crear | Editar | Estado |
|---|---|---|---|---|
| Definiciones | `attributes.view` | `attributes.create` | `attributes.update` | `attributes.change_status` |
| Por categoría | `category_attributes.view` | `category_attributes.create` | `category_attributes.update` | `category_attributes.change_status` |

Administrador y Encargado reciben inicialmente los ocho permisos; Consulta/Auditor solo los dos de consulta. Las Policies y Gates comprueban permisos, nunca nombres fijos de roles. La pantalla existente de roles permite configurar estas capacidades por separado.

La migración de inicialización usa etiquetas congeladas, añade únicamente vínculos ausentes para los códigos iniciales conocidos y conserva vínculos revocados. No asigna roles ni privilegios a usuarios o roles desconocidos, ni repone permisos de HU-05/HU-06 o del Sprint 1. Incrementa `permissions_version` y registra `roles.permissions_changed` solamente si añade capacidades, invalidando sesiones anteriores del rol afectado. Reejecutarla conserva la configuración.

Las acciones reutilizan `SecurityMutation`: bloqueo común, operador recargado, permisos/versiones revalidados y filas bloqueadas dentro de una transacción. Así se serializan escrituras con cambios de permisos, estados de categorías/unidades y cambios administrativos existentes. Las colisiones UNIQUE al guardar se traducen a errores españoles y rollback.

Los cambios de catálogos registran `created_by/updated_by` del operador real y timestamps. No se amplía la auditoría de usuarios/roles a otros sujetos: su CHECK admite únicamente esos sujetos. La auditoría de nuevas concesiones permanece en el mecanismo existente.

## Pantallas

`/atributos-tecnicos`: búsqueda por código/nombre, filtros de tipo y estado, paginación de 20, creación/edición/activación.

`/categorias/{category}/atributos`: configuración propia de la categoría, búsqueda y filtro, orden de presentación con desempate por ID, flags, límites y estado. En creación se ofrecen definiciones activas aún no asociadas; en edición se conserva la identidad.

Navegación desde categorías y menú/panel según permisos; grupos españoles en el formulario de roles. Blade, CSS existente, CSRF, etiquetas y mensajes españoles, HTML escapado y validaciones del servidor. No se requiere actualizar Node ni compilar assets.

## Pruebas y evidencia

La suite se ejecuta mediante:

```powershell
php vendor/bin/phpunit --colors=never --log-junit storage/logs/hu07-phpunit.xml
php vendor/bin/pint --test app routes tests database/migrations/2026_10_14_000001_create_attributes_table.php database/migrations/2026_10_14_000002_create_category_attributes_table.php database/migrations/2026_10_14_000003_add_attribute_permissions.php
git diff --check
```

`tests/bootstrap.php`, `phpunit.xml` y `Tests\TestCase` verifican el entorno testing, la base exclusiva, ausencia de DB_URL/caché y `SELECT DATABASE()` antes de preparar el esquema. Se conservan transacciones ordinarias y las pruebas concurrentes anteriores, con limpieza limitada a sus propios fixtures en testing.

Resultados reales de la ejecución final:

| Comprobación | Resultado |
|---|---|
| PHPUnit 12.5.38, PHP 8.3.10, MariaDB 10.4.32 | **185 pruebas, 1.286 verificaciones; cero errores y fallos.** |
| Duración informada por PHPUnit | 2 minutos y 1,050 segundos; memoria 66 MB. |
| Regresión | Incluye las 120 pruebas anteriores y 65 casos nuevos de HU-07. |
| Aislamiento | `sga_rio_grande_testing` confirmado antes de preparar el esquema. |
| Testing después de ejecutar | 18 migraciones, 33 permisos, 61 vínculos, nueve auditorías; tres roles con versión 3. |
| Fixtures después de rollback/limpieza propia | Cero usuarios, trabajadores, categorías, unidades, atributos y asociaciones en testing. |
| Base principal | Las huellas de datos y definiciones de sus 16 tablas coinciden antes/después. Conserva 15 migraciones, 25 permisos y 43 vínculos. |
| `.env` | Huella SHA-256 conservada. |
| Pint sobre app/rutas/tests y solo las migraciones nuevas | Aprobado. |
| `git diff --check` | Aprobado. |
| Migraciones/dependencias previas | Ningún archivo anterior modificado; no se instalaron paquetes. |

Evidencia JUnit local: `storage/logs/hu07-phpunit.xml`, ignorada por Git. Las pruebas SQL comprobaron restricciones reales de MariaDB mediante inserciones inválidas rechazadas, no solo inspección del esquema. Las dos pruebas concurrentes de administración del Sprint 1 también forman parte de la regresión aprobada.

Durante la verificación inicial se corrigió una directiva Blade adyacente que impedía compilar el listado anidado. También se corrigió una pérdida de acentos introducida al generar etiquetas desde PowerShell: solo las ocho etiquetas nuevas del borrador en la base de pruebas requirieron corrección, sin tocar permisos concedidos/revocados, usuarios ni base principal. Se ajustó el test de precisión para disponer del validador Laravel al comprobar sus excepciones. Estos hallazgos se incluyen en la regresión final.

### Trazabilidad

| Requerimiento / historia | Código principal | Prueba / evidencia |
|---|---|---|
| ATR-01/02 · HU-07: definiciones, tipos y unidad | CatalogAttribute, CatalogAttributeRequest, SaveCatalogAttribute | TechnicalAttributeManagementTest; TechnicalAttributeIntegrityTest |
| ATR-03 · HU-07: no reinterpretar referencias | SaveCatalogAttribute; relación categoryAttributes | Asociación histórica real; cambio rechazado y rollback en TechnicalAttributeManagementTest/TransactionTest |
| ASC-01 · HU-07: asociación única y categoría correcta | CategoryAttribute, CategoryAttributeRequest, rutas con scopeBindings | CategoryAttributeManagementTest; TechnicalAttributeIntegrityTest; acción con padre ajeno |
| ASC-02 · HU-07: límites exactos y precisión | TechnicalDecimal; SaveCategoryAttribute | CategoryAttributeManagementTest; TechnicalDecimalTest; TransactionTest; CHECK MariaDB |
| ASC-03 · HU-07: obligatoriedad e impacto histórico | AttributeReferences; SaveCategoryAttribute | Simulación explícita en CategoryAttributeManagementTest; integración con valores reales pendiente de HU-08 |
| ASC-04 · HU-07: estados y conservación | Acciones y FormRequests de estado | TechnicalAttributeManagementTest; CategoryAttributeManagementTest |
| MED-04 · HU-06: proteger unidades usadas | CatalogReferences existente; MeasurementUnit.attributes | Referencias reales a atributos activos/inactivos en TechnicalAttributeManagementTest |
| SEC-01/02/03/04 · HU-07 | Policies, Gate, FormRequests, SecurityMutation, vistas | TechnicalAttributeAuthorizationTest; solicitudes directas, permisos independientes, CSRF, sesión revocada y HTML escapado |
| RBAC-02/03/07 · HU-03 + HU-07 | Migración nueva de permisos; PermissionCatalog | AttributePermissionInitializationTest; no restaurar revocaciones ni privilegiar desconocidos |
| REG-01 · Sprint 1 + HU-05/HU-06 | Código existente conservado | Suite completa y comparación de huellas principales |

Pruebas de aceptación visual, teclado y tamaños de pantalla pendientes; los tests HTTP no equivalen a aceptación en navegador. Valores de productos, FKs compuestas hacia valores, captura según tipos, obligatoriedad con productos reales y movimientos siguen pendientes, sin presentarlos como verificados.

## Archivos de esta etapa

Nuevos:

- `database/migrations/2026_10_14_000001_create_attributes_table.php`
- `database/migrations/2026_10_14_000002_create_category_attributes_table.php`
- `database/migrations/2026_10_14_000003_add_attribute_permissions.php`
- `app/Models/CatalogAttribute.php`, `app/Models/CategoryAttribute.php`
- `app/Actions/Catalog/SaveCatalogAttribute.php`, `app/Actions/Catalog/SaveCategoryAttribute.php`
- `app/Services/AttributeReferences.php`, `app/Support/TechnicalDecimal.php`
- `app/Policies/CatalogAttributePolicy.php`, `app/Policies/CategoryAttributePolicy.php`
- `app/Http/Controllers/Catalog/CatalogAttributeController.php`, `app/Http/Controllers/Catalog/CategoryAttributeController.php`
- `app/Http/Requests/Catalog/CatalogAttributeRequest.php`, `CatalogAttributeStatusRequest.php`, `CategoryAttributeRequest.php`, `CategoryAttributeStatusRequest.php`, `AttributeSearchRequest.php` (misma carpeta)
- `resources/views/catalog/attributes/index.blade.php`, `form.blade.php`; `resources/views/catalog/category-attributes/index.blade.php`, `form.blade.php`
- `tests/Support/CreatesTechnicalCatalog.php`, `tests/Unit/TechnicalDecimalTest.php`
- `tests/Feature/Catalog/TechnicalAttributeManagementTest.php`, `CategoryAttributeManagementTest.php`, `TechnicalAttributeAuthorizationTest.php`, `TechnicalAttributeIntegrityTest.php`, `TechnicalAttributeTransactionTest.php`, `AttributePermissionInitializationTest.php` (misma carpeta)
- Este informe `docs/sprint-2-stage-2.md`.

Modificados:

- `app/Models/Category.php`, `app/Models/MeasurementUnit.php`: relaciones.
- `app/Support/PermissionCatalog.php`, `app/Providers/AppServiceProvider.php`, `routes/catalog.php`: permisos, Policies y rutas.
- `resources/views/layouts/app.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/roles/form.blade.php`, `resources/views/catalog/categories/index.blade.php`: navegación y grupos.
- `tests/Feature/RbacSchemaTest.php`, `tests/Feature/Catalog/CatalogPermissionInitializationTest.php`, `CatalogIntegrityTest.php`: catálogos esperados 33 permisos / 61 vínculos, capacidades 33/21/7 y tablas futuras excluyendo las implementadas.
- `docs/sprint-2.md`, `docs/data-dictionary-sprint-2.md`, `docs/sprint-2-test-matrix.md`: estado y evidencia de HU-07.

## Aplicación principal pendiente

No ejecutar estos comandos sin autorización específica para `sga_rio_grande`. Primero realizar prevalidación actualizada, verificar destino y solo estas tres pendientes, preparar respaldo SQL completo fuera del repositorio y verificarlo, registrar datos/permisos existentes y programar mantenimiento.

```powershell
php artisan down
php artisan migrate --force --path=database/migrations/2026_10_14_000001_create_attributes_table.php --path=database/migrations/2026_10_14_000002_create_category_attributes_table.php --path=database/migrations/2026_10_14_000003_add_attribute_permissions.php
php artisan migrate:status
# Verificar esquema, FKs, CHECK, permisos, versiones y datos conservados.
php artisan up
```

El destino deberá confirmarse mediante la conexión Laravel y `SELECT DATABASE()` inmediatamente antes de la operación; `.env` no se cambia. La prevalidación de Etapa 1 exige otro commit y no se debe reutilizar como herramienta de despliegue de HU-07.

MariaDB realiza commits implícitos en DDL: un fallo puede dejar una tabla creada sin marcar la migración. Ante un fallo, mantener mantenimiento, inspeccionar el estado y definir recuperación/restauración autorizada desde el respaldo; no ejecutar rollback destructivo, fresh, reset ni wipe. Los métodos `down()` nuevos bloquean la reversión destructiva.

Después de migrar: dos tablas nuevas vacías; tres migraciones registradas; tres FKs de attributes y cuatro de category_attributes; índices únicos y CHECK presentes; los permisos previos y vínculos revocados intactos; solo ocho permisos nuevos y concesiones iniciales autorizadas. Con los tres roles actuales sin configuración previa de HU-07, el total previsto es 33 permisos, 61 vínculos y versiones 3, con tres auditorías nuevas. Los usuarios, trabajadores y datos previos deben conservar sus huellas, salvo los cambios de roles/auditoría expresamente previstos.

Commit, merge, push y aplicación principal permanecen pendientes de autorización. HU-08 no ha comenzado.

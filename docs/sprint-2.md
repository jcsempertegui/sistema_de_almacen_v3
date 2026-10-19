# Sprint 2 — Propuesta técnica y Scrum

Fecha: 12 de octubre de 2026, America/La_Paz.

**Estado al 19 de octubre de 2026:** HU-05/HU-06/HU-07/HU-09/HU-08 aplicadas a la principal con autorización y respaldo. HU-10 implementada, con dos migraciones nuevas pendientes de aplicación principal. La principal conserva 25 migraciones aplicadas, 23 tablas, 44 permisos y 86 vínculos. Resultados actuales en [Etapa 5](sprint-2-stage-5.md); la aceptación visual permanece pendiente. Evidencias anteriores: [Etapa 1](sprint-2-stage-1.md), [Etapa 2](sprint-2-stage-2.md), [Etapa 3](sprint-2-stage-3.md) y [Etapa 4](sprint-2-stage-4.md).

## Objetivo y alcance

**Actualización del 19 de octubre de 2026:** HU-10 implementada; sus dos migraciones se validan en testing y no se aplican a la principal en esta entrega. Consulta [Etapa 5](sprint-2-stage-5.md) para las decisiones aprobadas, conservación histórica y resultados actuales.

Gestionar categorías, unidades de medida, atributos por categoría, productos, ubicaciones físicas y unidades individualizadas de un único almacén en Río Grande.

La revisión inicial del Sprint 1 identificó la brecha de roles fijos. El correctivo HU-03 se aprobó posteriormente y su implementación, pruebas y migración principal autorizada del 13 de octubre de 2026 se describen en [el informe del correctivo](sprint-1-hu03-corrective.md). La Etapa 1 añadió ocho permisos explícitos para categorías y unidades mediante una migración principal autorizada. No añade permisos de historias futuras ni restaura revocaciones previas.

Se conservan Laravel 13, PHP 8.3, MariaDB 10.4.32, las vistas Blade y el CSS existente. No se necesitan paquetes nuevos, compilación con Vite ni actualización de Node 18.8.0.

## Reglas aprobadas de negocio

1. Un solo almacén en Río Grande.
2. Los productos se registran manualmente desde el sistema.
3. Excel no crea ni modifica productos.
4. Cada producto pertenece a una categoría y tiene una unidad de medida.
5. Atributos configurables por categoría.
6. Código interno de producto único.
7. Control por cantidad o individual.
8. Toda herramienta prestable tiene un identificador individual único.
9. Ubicaciones administrables como sectores, estantes y gabinetes.
10. Jerarquía de ubicaciones sin ciclos.
11. Un producto puede almacenarse en varias ubicaciones.
12. El Encargado selecciona las ubicaciones al retirar productos.
13. Existencias nunca negativas.
14. Productos y unidades con historial conservan trazabilidad.
15. Atributos y características técnicas permiten identificar posibles equivalentes o duplicados.

Las reglas sobre retiros, saldos y documentos se implementarán en sprints posteriores. Este Sprint prepara identidades y relaciones del catálogo, sin anticipar esas operaciones.

## Decisiones confirmadas y pendientes

| Decisión | Estado |
|---|---|
| Roles y permisos configurables son el requisito original | Confirmado; correctivo implementado y probado, con migración principal autorizada aplicada. |
| El Encargado también gestiona unidades de medida | Confirmado por el usuario. |
| Conservar un rol por usuario inicialmente | Confirmado en el alcance del correctivo HU-03. |
| Añadir `product_locations` para la relación muchos a muchos | Aprobado, implementado y migrado en HU-08, sin cantidades. |
| Aviso de posibles equivalentes con revisión humana | Aprobado e implementado mediante comparación exacta de identificadores vigentes; sin conversiones, tolerancias ni fusión automática. |
| Numeración HU-05 a HU-10 | Provisional; contrastar con backlog académico. |

## Historias y criterios de aceptación

### HU-05 — Gestionar categorías

Como Administrador o Encargado, quiero gestionar categorías para organizar el catálogo.

- Registrar y editar código, nombre y descripción opcional.
- Rechazar códigos/nombres repetidos y campos obligatorios vacíos.
- Consultar, buscar y filtrar por estado con paginación.
- Bloquear desactivación mientras existan productos activos asociados.
- Conservar registros y referencias al desactivar.
- Consulta/Auditor puede leer, pero toda solicitud de modificación debe rechazarse en el servidor.

Tareas: migración nueva, modelo, factory, Policy, FormRequests, controlador, listado/formulario y pruebas de validación, permisos y dependencias.

Dependencias: correctivo de autorización; no depende de productos para comenzar su implementación.

### HU-06 — Gestionar unidades de medida

Como Administrador o Encargado, quiero definir unidades para registrar cantidades y características técnicas coherentes.

- Gestionar código, nombre, símbolo, dimensión y precisión decimal.
- Código único; símbolo único con comparación sensible a mayúsculas.
- Precisión entre 0 y 6 decimales y dimensión válida.
- Rechazar cambios incompatibles con datos que ya utilicen la unidad.
- Bloquear desactivación si hay productos o atributos activos que la utilizan.
- Consulta/Auditor accede en modo lectura.

Tareas: esquema, modelo, solicitudes, Policy, vistas y casos de precisión, referencias y permisos del Encargado.

Dependencias: correctivo de autorización; requerida para productos y atributos numéricos.

### HU-07 — Gestionar atributos por categoría

Como Administrador o Encargado, quiero configurar características por categoría para describir e identificar productos.

- Definir atributos de texto, decimal, booleano o fecha.
- Asociarlos a categorías sin duplicar el mismo atributo en una categoría.
- Configurar obligatoriedad, identificación técnica, orden y límites numéricos opcionales.
- Unidad técnica únicamente para atributos decimales; límites coherentes con el tipo.
- No reinterpretar valores existentes cambiando tipo o unidad de un atributo ya utilizado.
- Completar los productos activos antes de convertir un atributo en obligatorio.
- Desactivar asociaciones conserva valores anteriores y evita usarlas en nuevas capturas.

Implementación de Etapa 2: dos migraciones de tablas y una de ocho permisos; modelos `CatalogAttribute` y `CategoryAttribute`, acciones transaccionales, Policies, FormRequests, controladores, vistas Blade y pruebas. Se conserva tipo/unidad desde cualquier asociación, incluso histórica, y se bloquea la desactivación global mientras haya asociaciones activas. Las configuraciones existentes mantienen su categoría y atributo. Límites exactos DECIMAL(18,6), con precisión de la unidad, sin FLOAT ni conversiones.

La Etapa 4 integra productos y valores reales en testing, incluidas FKs compuestas, bloqueo de cambios con historial y reactivación de asociaciones obligatorias con productos incompletos. La evidencia de la Etapa 2 conserva su contexto original; la ampliación y pruebas reales se documentan en [Etapa 4](sprint-2-stage-4.md).

Dependencias: categorías, unidades y autorización.

### HU-08 — Gestionar productos

Como Administrador o Encargado, quiero registrar manualmente productos con sus características y forma de control.

- Código interno obligatorio y único, incluso respecto de productos inactivos.
- Categoría y unidad válidas/activas.
- Modo por cantidad o individual. Producto prestable exige modo individual.
- Captura dinámica de atributos según la categoría, validada también por el servidor.
- Rechazar valores de otra categoría, tipos incorrectos o atributos obligatorios ausentes.
- Guardar producto y valores en una transacción; fallos no dejan registros parciales.
- Avisar de coincidencias técnicas y permitir comparación de candidatos.
- Desactivación sin borrar referencias; identidad y modalidad protegidas cuando existan unidades o historial.
- No existe importación Excel para crear o modificar productos.

Implementación de Etapa 4: cuatro tablas nuevas (productos, valores, revisiones y asociaciones de ubicaciones), una migración de siete permisos, modelos, acciones transaccionales, Policies, FormRequests, listados, formularios, ficha e historial Blade y revisión humana de coincidencias. Código inmutable y edición optimista mediante `lock_version`. Las pruebas se ejecutaron exclusivamente en testing. Las cinco migraciones principales se aplicaron con autorización y respaldo el 18 de octubre de 2026, lotes 7 a 11; la principal tiene 25 migraciones aplicadas y ninguna pendiente. Las comprobaciones posteriores de datos y esquema fueron de solo lectura.

Dependencias: categorías, unidades, atributos y ubicaciones para asignación múltiple.

### HU-09 — Gestionar ubicaciones físicas

Como Administrador o Encargado, quiero organizar sectores, estantes y gabinetes jerárquicamente.

- Código único, nombre, tipo, padre opcional y posibilidad de almacenar.
- Rechazar un padre inexistente, inactivo, la propia ubicación o un descendiente.
- Evitar ciclos incluso ante dos modificaciones concurrentes.
- Mostrar árbol, ruta y estado; filtrar por tipo o padre.
- Bloquear desactivación con hijos activos o unidades asignadas.
- Conservar asociaciones e historial; no existe pantalla de traslados en este Sprint.

Implementación de Etapa 3: migración de `locations` y otra de cuatro permisos; modelo y factory, Policy, FormRequests, controlador, acción transaccional y servicios de jerarquía/referencias. Listado y árbol Blade, con ruta, tipos, estado y posibilidad de almacenar. La acción reutiliza `SecurityMutation`, recarga ancestros mediante lecturas con bloqueo y detecta ciclos, incluso con nodos inactivos. Cuatro carreras de dos procesos verifican ciclos cruzados y creación/reactivación de hijos frente a desactivación del padre, incluidas lecturas previas en REPEATABLE-READ.

La Etapa 4 verifica asociaciones reales con productos y bloquea la desactivación de ubicaciones con asociaciones activas. La Etapa 5 integra unidades reales y sustituye las comprobaciones simuladas de sus dependencias. La aceptación visual permanece pendiente. La integración y aplicación principal de HU-09 se documentan en [Etapa 3](sprint-2-stage-3.md).

Dependencias: autorización. Puede implementarse antes de productos.

### HU-10 — Gestionar unidades individualizadas

Como Administrador o Encargado, quiero identificar cada unidad de un producto individual para conservar su trazabilidad.

- Identificador obligatorio y único en todo el catálogo, sin reutilizar identificadores desactivados.
- Producto necesariamente de control individual.
- Número de serie opcional; si se informa, no se repite para el mismo producto.
- Ubicación inicial opcional y, cuando se informe, válida, activa y asociada al producto.
- Listar/buscar por identificador, serie, producto, estado y ubicación.
- Desactivar sin borrar; conservar identidad y producto cuando exista historial.
- Registrar una unidad no genera automáticamente una entrada de existencias.

Tareas: migración, modelo, solicitudes, Policy, formularios, detalle y pruebas de identificación, referencias y permisos.

Dependencias: productos, ubicaciones y asociación producto–ubicación aprobada.

## Permisos iniciales propuestos

| Operación | Administrador | Encargado | Consulta/Auditor |
|---|---|---|---|
| Consultar los seis módulos | Sí | Sí | Sí |
| Crear y editar | Sí | Sí | No |
| Activar/desactivar respetando dependencias | Sí | Sí | No |
| Configurar roles y asignar permisos | Sí | No | No |

La gestión del Encargado incluye unidades de medida. Esta matriz describe la configuración inicial propuesta; no añade reglas automáticas por nombre de rol a la arquitectura futura. Cada acción se comprueba mediante capacidades en el servidor y `@can` en las vistas.

## Interfaces

- Conservar layout, navegación y estilo del Sprint 1; mensajes y errores en español.
- Cada catálogo: listado paginado, búsqueda, estado, creación/edición y detalle cuando corresponda.
- Productos: filtros implementados por categoría, unidad, modo y estado, y búsqueda por código/nombre/marca/referencia; valores tipados generados desde la categoría. No se incorpora en HU-08 un buscador general por valores técnicos.
- Ubicaciones: árbol y ruta legible además del listado.
- Unidades individualizadas: búsqueda por identificador/serie y filtros por producto/ubicación.
- Los campos dinámicos pueden renderizarse mediante recarga del formulario con categoría seleccionada, sin nuevas dependencias de frontend.
- Los valores mostrados se escapan; los formularios de escritura usan CSRF y nunca aceptan autores enviados por el cliente.

## Comparación de productos

Código interno repetido implica rechazo. Las posibles equivalencias se buscan por categoría, unidad, modo y atributos marcados `is_identity`, con normalización de valores.

Las unidades técnicas se fijan en la definición del atributo. No se comparan medidas incompatibles como si fueran iguales; conversiones automáticas y tolerancias aún requieren definición.

La coincidencia genera un aviso y una comparación de candidatos, no una certificación de intercambiabilidad. No se impone unicidad por nombre o por combinación de características, porque puede haber alternativas legítimas. La configuración de atributos de identificación ya usados requiere análisis de impacto.

## Límite con inventario

`product_locations` representa ubicaciones configuradas y no tiene cantidades. `individual_units.location_id` representa una asignación inicial, no un traslado documentado. Cambios posteriores con historial deben pasar por el flujo autorizado que corresponda en los siguientes sprints.

No se crean saldos, remitos, entregas, devoluciones, movimientos o ajustes. No hay stock editable ni confirmación de documentos. Las existencias no negativas se comprobarán cuando se implemente su registro transaccional; esa garantía no puede darse por implementada en un catálogo sin saldos.

## Orden recomendado

1. Revisar y aceptar el correctivo HU-03 ya implementado, probado y migrado con autorización; consultar su informe de resultados y completar la configuración del primer administrador.
2. Categorías y unidades de medida.
3. Atributos y asociaciones por categoría.
4. Ubicaciones con protección de jerarquía.
5. Productos, valores y asociaciones de ubicación.
6. Unidades individualizadas.
7. Pruebas integradas, aceptación visual y documentación.

## Archivos previstos

| Área | Creación o modificación prevista |
|---|---|
| Datos | Categorías, unidades, atributos y ubicaciones implementados. HU-08 añade productos, valores, revisiones, asociaciones aprobadas y permisos. Solo `create_individual_units_table` permanece como diseño futuro. |
| Modelos | `Category`, `MeasurementUnit`, `CatalogAttribute`, `CategoryAttribute`, `Product`, `ProductAttributeValue`, `Location`, `IndividualUnit` y `ProductLocation`. |
| Autorización | Policies del catálogo; correctivo separado de permisos; registro en `AppServiceProvider.php`. |
| Validación | `app/Http/Requests/Catalog/`, reutilizando `SgaFormRequest`. |
| Operaciones | Acciones para guardado de producto/valores, jerarquía y desactivación con dependencias. |
| Rutas | `routes/catalog.php`, incluido desde `routes/web.php`. |
| Vistas | `resources/views/catalog/`, menú y estilos existentes. |
| Pruebas | `tests/Feature/Catalog/`, factories, pruebas de permisos y concurrencia. |
| Documentación | Plan, diccionario, matriz de pruebas, capturas y decisiones de aceptación. |

No se modificarán migraciones ya aplicadas, `.env` ni dependencias sin autorización específica. No se necesita una tabla adicional de almacenes.

## Definición de terminado

- Criterios de aceptación de la historia cumplidos y revisados.
- Validación, autorización y atribución de autores en el servidor.
- Integridad y restricciones verificadas en MariaDB 10.4.32.
- Pruebas automatizadas, negativas y de caja negra aprobadas, junto con regresión del Sprint 1.
- Sin cambios ni pruebas que afecten a la base principal durante la validación.
- Interfaz revisada en navegador, con teclado y tamaños de pantalla previstos.
- Mensajes en español, datos escapados y formularios protegidos.
- Migraciones nuevas documentadas, sin recrear bases o alterar migraciones ejecutadas.
- Diccionario de datos, diagramas, evidencias y matriz de resultados actualizados.

## Entregables académicos

Backlog y criterios, tareas y dependencias, diagrama entidad-relación, diccionario físico, matriz de permisos, matriz de caja negra/automatizadas, resultados reales, capturas de aceptación, registro de decisiones y retrospectiva. Los casos previstos no se presentarán como ejecutados.

## Referencias

- [Diccionario físico propuesto](data-dictionary-sprint-2.md).
- [Matriz de pruebas prevista](sprint-2-test-matrix.md).
- [Planificación global de cinco sprints](global-plan.md).
- [Validación del Sprint 1](sprint-1-validation.md).

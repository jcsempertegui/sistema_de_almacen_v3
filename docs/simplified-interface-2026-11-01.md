# Simplificación de la operación diaria — 1 de noviembre de 2026

## Punto de partida y alcance

Antes de modificar archivos se verificó `git fetch origin`: `main` y `origin/main` coincidían en `bd89c978b2cccdfb4df018b54d2010f36cad41d7`, sin cambios locales. La propuesta autorizada se desarrolla en `mejora/navegacion-simplificada`. Esta revisión no publica cambios en GitHub.

La versión anterior permanece en `main`. Si se rechaza esta interfaz, se puede recuperar el código de ese commit conservando antes los cambios de la rama de trabajo. La reversión no necesita restaurar datos ni ejecutar migraciones: no se modificaron esquemas, documentos, saldos ni permisos.

## Organización implementada

| Apartado | Acceso cotidiano y opciones secundarias |
| --- | --- |
| Inicio | Entregar materiales, prestar herramientas, recibir herramientas y consultar existencias; debajo permanecen indicadores, borradores y movimientos recientes. |
| Existencias | Consulta física con enlaces a historial y disponibilidad. |
| Entregas | Documentos de entrega; búsqueda de trabajador por código o nombre en el formulario; varias líneas por documento. |
| Herramientas | Grupo desplegable con préstamos, devoluciones, disponibilidad e identidades. Desde un préstamo pendiente se abre una devolución con ese préstamo seleccionado. |
| Remitos | Ingresos y egresos; se conserva la consulta histórica de aperturas. La creación e importación de existencias iniciales se abre desde Administración. |
| Catálogo | Productos, categorías, atributos, unidades y ubicaciones en un solo grupo. Productos ofrece también un desplegable de configuración. Marca y referencia quedan en una sección opcional; los atributos obligatorios y las secciones con errores se abren automáticamente. |
| Trabajadores | Se conserva la gestión independiente de las cuentas de usuario. |
| Reportes | Se conservan los cinco grupos y sus diez consultas, CSV y PDF. Búsqueda y fechas visibles; filtros secundarios en “Más filtros”, abierto cuando hay filtros aplicados. |
| Administración | Correcciones de inventario, usuarios, roles, auditoría, respaldos y existencias iniciales, según permisos. |

Las correcciones diferencian “Corregir una diferencia de inventario” y “Compensar una operación confirmada”. Conservan los nombres técnicos de los documentos, su autorización por otra persona y su historial.

Los grupos se ocultan si no hay opciones autorizadas. El grupo de la pantalla actual se abre automáticamente. El menú móvil incluye los desplegables en el recorrido de teclado; funciona con `details/summary` nativos. El buscador del trabajador mejora el selector existente sin sustituirlo ni cambiar su valor automáticamente.

## Contratos conservados

- Gates, Policies, permisos configurables y revocación vigentes; no se conceden permisos nuevos.
- CSRF, validaciones de servidor, versiones de edición y confirmaciones transaccionales existentes.
- Las devoluciones siguen requiriendo selección manual de herramienta y destino, guardado de borrador y confirmación física. El acceso rápido no crea documentos ni movimientos.
- La preselección de préstamo valida entero positivo, documento confirmado y herramientas pendientes; el guardado y la confirmación vuelven a aplicar todas sus reglas.
- No hay cambios de dependencias, `.env`, migraciones ni datos principales.

## Verificación

- Regresión seleccionada ejecutada en MariaDB 10.4.32: **48 pruebas y 451 aserciones aprobadas**, PHPUnit 12.5.38, PHP 8.3.10; duración 02:28.993. Incluye `SimplifiedInterfaceTest`, `VisualNavigationTest`, `DashboardOperationsTest`, `DashboardChartsTest`, `DetailedReportsTest`, `WorkerOperationHttpTest`, `ControlHttpTest` y `ProductManagementTest`. No equivale a una regresión integral de los cinco sprints ni a aceptación humana.
- Las cinco pruebas nuevas de `tests/Feature/SimplifiedInterfaceTest.php` cubren los nueve apartados y cuatro acciones, perfil de consulta, apertura del grupo activo, filtros secundarios persistentes, errores reales de validación y preselección de préstamos pendientes sin escritura. Se comprobó también el rechazo al revocar el permiso de crear devoluciones.
- La primera tanda se detuvo por una preparación incorrecta del error de sesión en una prueba nueva (40 pruebas, un fallo). Se corrigió la prueba para provocar la validación mediante un POST real; la tanda final indicada arriba aprobó sin fallos. Se conservan ambas evidencias, sin reinterpretar el resultado inicial.
- Antes de escribir en testing, el guard verificó `SELECT DATABASE()`, servidor `DESKTOP-U7NQKIM`, puerto `3306` y directorio `C:/xampp/mysql/data/`: destino exclusivo `sga_rio_grande_testing`.
- Comparación de huellas de datos y estructura de las **53 tablas principales: ninguna cambió**. Se mantienen 43 migraciones y cero pendientes; `.env`, dependencias y archivos de migraciones conservan sus hashes.
- Chrome sin interfaz, sobre renders de solo lectura: panel, formulario de entrega, formulario de devolución y reporte a 1440, 390 y 320 px, sin desbordamiento horizontal de página. Se revisaron capturas de escritorio y móvil.
- Interacciones reales de JavaScript en esas vistas aisladas: búsqueda de un trabajador (25 opciones → 2, incluida la opción vacía → 25 al limpiar), selección preservada; añadir/quitar línea (1 → 2 → 1); apertura de menú y desplegable, cierre con Escape y devolución del foco.
- Pint y `git diff --check` correctos. No se instalaron paquetes ni se modificaron dependencias.

Comando de regresión ejecutado:

```powershell
php vendor/bin/phpunit --colors=never --stop-on-failure --filter 'SimplifiedInterfaceTest|VisualNavigationTest|DashboardOperationsTest|DashboardChartsTest|DetailedReportsTest|WorkerOperationHttpTest|ControlHttpTest|ProductManagementTest' --log-junit C:/Users/jcsem/SGA-evidence/simplification-20261101/regression-final.xml
```

Evidencias externas: `regression-final.xml`, `before.json`, `after.json`, `conservation.json`, `render.json`, `responsive-results.json`, `interactions.json` y capturas `viewport-*.png`. No se incorporan credenciales, renders privados ni volcados SQL al repositorio.

Las capturas técnicas de navegador se realizan sobre HTML renderizado con conexión de solo lectura y recursos locales. No constituyen aceptación funcional humana ni una operación real de entrega o devolución. Las evidencias completas permanecen fuera del repositorio en `C:/Users/jcsem/SGA-evidence/simplification-20261101/`.

## Recorrido para la revisión del usuario

1. Abrir Inicio y revisar las cuatro acciones según el perfil.
2. Abrir Entregar materiales y filtrar un trabajador; revisar las líneas y cancelar sin guardar si solo se está evaluando el diseño.
3. Consultar un préstamo con obligaciones pendientes y abrir Registrar devolución; comprobar préstamo, herramientas y destino sin confirmar una operación ficticia.
4. Abrir Catálogo → Productos; comprobar configuración, marca/referencia y atributos obligatorios.
5. Consultar un reporte; aplicar un filtro secundario y comprobar que sigue visible después de buscar.
6. Abrir Administración → Correcciones de inventario y contrastar las dos explicaciones.
7. Repetir la navegación en móvil y con los perfiles de Encargado y Consulta/Auditor.

La decisión de conservar esta propuesta o volver a la interfaz anterior queda pendiente de revisión del usuario.

## Corrección posterior: paginación de tablas

Se detectó que los listados con `links()` utilizaban la vista Tailwind predeterminada de Laravel, mientras que esta aplicación usa CSS propio. Se estableció `pagination.simple` como vista predeterminada en `AppServiceProvider`, conservando los listados que ya la indicaban explícitamente. Los controles Anterior/Siguiente usan flechas SVG con dimensiones explícitas de 16 × 16 px, texto en español y límites deshabilitados accesibles; el indicador de página se acomoda debajo en móvil.

Validación ejecutada por separado: `PaginationPresentationTest|VisualNavigationTest`, **10 pruebas y 61 aserciones aprobadas**, exclusivamente en testing. Incluye conservación de filtros y parámetros independientes para pantallas con dos tablas, primera/última página y ausencia de controles cuando solo hay una página. No se modificaron datos, permisos ni consultas de inventario. No se suma este resultado a la tanda histórica de 48 pruebas.
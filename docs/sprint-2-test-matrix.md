# Matriz de pruebas propuesta — Sprint 2

Fecha: 12 de octubre de 2026, America/La_Paz.

**Estado: HU-05 y HU-06 tienen implementación y pruebas de Etapa 1; los casos de HU-07 a HU-10 continúan planificados y sin ejecutar.** Consulta [la evidencia actual de Etapa 1](sprint-2-stage-1.md). La regresión del Sprint 1 incluye el [correctivo HU-03: 74 pruebas y 475 verificaciones de su validación original](sprint-1-hu03-corrective.md), además de la ejecución conjunta de esta etapa.

## Condiciones de ejecución

- Usar exclusivamente `sga_rio_grande_testing`, con comprobación de configuración y `SELECT DATABASE()` antes de preparar el esquema.
- Mantener transacciones para casos ordinarios, sin `RefreshDatabase`, `migrate:fresh`, `migrate:reset` o `db:wipe`.
- No crear ni modificar productos o datos de prueba en `sga_rio_grande`.
- Migraciones nuevas solo después de aprobar el diseño y confirmar el destino de ejecución.
- Pruebas concurrentes usan conexiones dedicadas a la base exclusiva. Requieren fixtures visibles entre conexiones y una estrategia explícita de aislamiento; no basta una transacción no confirmada del TestCase principal.
- Antes de iniciar casos concurrentes, documentar preparación y conservación de datos. No añadir limpieza destructiva que incumpla las reglas del proyecto.
- Repetir la regresión del Sprint 1 después de modificar código compartido o permisos.

## Caja negra y casos automatizados

| ID | Área y entrada/acción | Resultado esperado | Validación prevista |
|---|---|---|---|
| CAT-01 | Crear categoría con datos válidos | Registro visible y autores del operador. | Feature HTTP y aceptación en navegador. |
| CAT-02 | Código/nombre duplicado o nombre vacío | Error español; sin registro nuevo. | Feature y restricción UNIQUE. |
| CAT-03 | Desactivar categoría con producto activo | Operación bloqueada; dependencias intactas. | Feature. |
| CAT-04 | Buscar y filtrar categorías activas/inactivas | Paginación y filtros coherentes. | Feature y caja negra. |
| MED-01 | Encargado crea/edita unidad | Operación autorizada y visible. | Feature de permisos. |
| MED-02 | Precisión -1 o 7, dimensión inválida | Rechazo con mensaje español. | Feature y CHECK donde corresponda. |
| MED-03 | Símbolos iguales y símbolos de distinta capitalización | Rechazar repetido exacto; respetar comparación binaria. | Integración MariaDB. |
| MED-04 | Desactivar o alterar unidad utilizada de forma incompatible | Bloqueo; ningún dato reinterpretado. | Feature e integración. |
| ATR-01 | Definir cada tipo y unidad técnica decimal | Datos válidos persistidos. | Feature parametrizado por tipo. |
| ATR-02 | Tipo no permitido o unidad en atributo no decimal | Rechazo. | Feature y CHECK aplicable. |
| ATR-03 | Cambiar tipo/unidad de atributo con valores | Bloqueo; valores conservados. | Feature. |
| ASC-01 | Asociar atributo a categoría y repetir asociación | Primera válida; segunda rechazada. | Feature y UNIQUE. |
| ASC-02 | Mínimo mayor que máximo o límites de tipo no decimal | Rechazo. | Feature y CHECK aplicable. |
| ASC-03 | Convertir en obligatorio con productos activos incompletos | Bloqueo hasta completar datos. | Feature transaccional. |
| ASC-04 | Desactivar asociación utilizada | Conservar definición y valores; excluir de nuevas capturas. | Feature y caja negra. |
| PRO-01 | Crear manualmente producto válido | Producto y valores guardados juntos. | Feature y transacción. |
| PRO-02 | Código duplicado, incluso contra producto inactivo | Rechazo; no reutilizar identidad. | Feature y UNIQUE. |
| PRO-03 | Categoría o unidad inexistente/inactiva | Rechazo. | Feature y FK para inexistencia. |
| PRO-04 | Producto prestable con control por cantidad | Rechazo. | Feature y CHECK de producto. |
| PRO-05 | Valores obligatorios ausentes, ajenos a categoría o de tipo incorrecto | Rechazo; sin persistencia parcial. | Feature y FKs compuestas. |
| PRO-06 | Fallo al guardar un valor después de crear producto | Rollback del conjunto. | Integración transaccional. |
| PRO-07 | Producto con características coincidentes | Aviso y candidatos comparables; no afirmar equivalencia certificada. | Feature y caja negra. |
| PRO-08 | Buscar por categoría/unidad/modo/estado | Resultados y paginación coherentes. | Feature y caja negra. |
| VAL-01 | Decimal cero, booleano falso y fecha válida | Se aceptan como valores informados. | Feature por tipo. |
| VAL-02 | Dos columnas de valor o ninguna | Rechazo de cardinalidad. | Integración CHECK. |
| VAL-03 | Valor de otra categoría a través de escritura directa de prueba | FK compuesta rechaza inconsistencia. | Integración MariaDB. |
| VAL-04 | Vaciar valor opcional y reactivarlo | Desactivación/actualización sin borrar la asociación. | Feature. |
| VAL-05 | Temperatura negativa dentro del rango técnico | Valor permitido; no confundir con existencia negativa. | Feature. |
| LOC-01 | Crear sector, estante y gabinete con padres válidos | Árbol y ruta correctos. | Feature y navegador. |
| LOC-02 | Padre inexistente, inactivo o igual al nodo | Rechazo; árbol intacto. | Feature/FK. |
| LOC-03 | Elegir un descendiente como padre | Rechazo de ciclo indirecto. | Feature. |
| LOC-04 | Dos solicitudes intentan ciclos cruzados simultáneamente | No se forma ciclo; bloqueo/reintento o rechazo controlado. | Dos conexiones MariaDB. |
| LOC-05 | Desactivar nodo con hijos activos o unidades asignadas | Bloqueo con motivo español. | Feature. |
| PLC-01 | Asociar un producto a varias ubicaciones válidas | Varias asociaciones, sin cantidades. | Feature, si se aprueba tabla. |
| PLC-02 | Repetir asociación o usar ubicación no almacenable/inactiva | Rechazo. | Feature y UNIQUE. |
| PLC-03 | Desactivar asociación con unidad asignada | Bloqueo; referencia conservada. | Feature. |
| IND-01 | Registrar unidad de producto individual | Identificador visible y único, sin movimiento de stock. | Feature. |
| IND-02 | Identificador duplicado, incluso de inactivo | Rechazo. | Feature y UNIQUE. |
| IND-03 | Producto por cantidad o producto inactivo | Rechazo. | Feature. |
| IND-04 | Serie omitida y serie repetida del mismo producto | Omitida válida; repetida rechazada. | Feature y UNIQUE. |
| IND-05 | Ubicación sin asociación al producto | Rechazo del servidor y FK compuesta cuando se informe. | Feature e integración. |
| IND-06 | Desactivar unidad con historial | Conservar identidad y referencias; no borrado. | Caso con historial cuando exista su módulo; conservación básica en Sprint 2. |
| SEC-01 | Consulta/Auditor envía POST/PUT/PATCH directamente | Respuesta prohibida, sin cambios. | Feature para cada módulo. |
| SEC-02 | Invitado o cuenta desactivada intenta acceder | Sin lectura ni escritura protegida. | Feature. |
| SEC-03 | Enviar autores, identificadores de otra categoría o campos no autorizados | No aceptar autoría del cliente ni asociaciones ajenas. | Feature. |
| SEC-04 | Formulario sin CSRF y nombres con etiquetas HTML | Escritura rechazada; contenido mostrado escapado. | Feature y navegador. |
| UI-01 | Recorrer listados, formularios y detalles con cada rol | Solo acciones permitidas, navegación consistente. | Caja negra en navegador. |
| UI-02 | Teclado y tamaños de pantalla acordados | Controles utilizables, etiquetas visibles y errores comprensibles. | Aceptación visual/manual. |
| REG-01 | Ejecutar suite del Sprint 1 tras cambios compartidos | Regresión aprobada; misma base principal sin alteraciones. | PHPUnit y comprobaciones de aislamiento. |

## Casos del correctivo de HU-03

| ID | Acción | Resultado esperado |
|---|---|---|
| RBAC-01 | Crear un rol nuevo y asignarlo a un usuario | Rol utilizable sin necesitar añadirlo a constantes del código. |
| RBAC-02 | Conceder/revocar una capacidad | La Policy refleja la asignación y revoca accesos antiguos cuando corresponda. |
| RBAC-03 | Cambiar capacidades con sesión iniciada | Versión de permisos invalida la sesión anterior. |
| RBAC-04 | Usuario sin permiso intenta gestionar roles | Operación prohibida en el servidor. |
| RBAC-05 | Retirar el último acceso administrativo completo | Bloqueo; permanece una cuenta activa capaz de administrar usuarios y permisos. |
| RBAC-06 | Dos cambios administrativos concurrentes | No dejar el sistema sin acceso administrativo. |
| RBAC-07 | Migrar desde los tres roles actuales | Identificadores y usuarios asociados conservados, sin privilegios inesperados. |

El correctivo se aprobó y estos comportamientos se verificaron dentro de la suite del Sprint 1: consulta la [trazabilidad y los resultados actuales](sprint-1-hu03-corrective.md). Se conserva un rol por usuario; no se añaden permisos arbitrarios ejecutables desde datos. La migración principal del correctivo se aplicó con autorización y respaldo el 13 de octubre de 2026; los casos del catálogo del Sprint 2 permanecen sin ejecutar.

## Registro de resultados

La ejecución de Etapa 1 se documenta en [sprint-2-stage-1.md](sprint-2-stage-1.md), relacionando requerimiento, historia, código y pruebas. CAT-03 y MED-04 verifican bloqueo y rollback con referencias simuladas; su integración con productos y atributos reales sigue pendiente. SEC-01 a SEC-04 y REG-01 se aplican únicamente a los módulos implementados. UI-01/UI-02 permanecen pendientes de aceptación en navegador.

Al ejecutar, cada caso debe registrar versión/commit, precondiciones, entrada, resultado esperado, resultado obtenido, estado, fecha, responsable y evidencia. Solo los casos realmente ejecutados se contabilizarán como aprobados.

La condición de existencias no negativas se probará en el módulo futuro de movimientos y retiros. No se presenta como verificada por las pruebas de catálogo del Sprint 2.

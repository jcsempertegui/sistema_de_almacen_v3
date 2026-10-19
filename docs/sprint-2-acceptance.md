# Sprint 2 — Validación funcional y preparación del cierre

Fecha: 19 de octubre de 2026, America/La_Paz.

**Estado vigente: aceptación funcional declarada por el usuario para avanzar, con reservas de evidencia humana.** El usuario declaró «todo esta bien podemos continuar» y autorizó la continuación tras revisar la propuesta. Registro documental: 19 de octubre de 2026. No se atribuyen firma, identidad legal ni fecha de ejecución a pruebas humanas sin constancia. Esta declaración no convierte los recorridos asistidos en pruebas manuales; los casos pendientes del guion y las reservas siguientes se conservan. El inicio del Sprint 3 fue autorizado posteriormente; su implementación no autoriza migraciones principales ni publicación.

Base de la revisión original: `44e0335c380bb26ebae5cac88cbef2049e68ea25`, `main` limpia y coincidente con GitHub al comenzar. En aquella revisión, el único cambio de aplicación fue la etiqueta española del grupo de permisos de unidades individualizadas; no se cambiaron reglas de negocio, dependencias ni migraciones.

**Seguimiento de UI-S2-02, autorizado después de `bd28aa410109`:** se añadió la vista española 419 conservando el middleware y el código HTTP. Se ejecutaron **39 pruebas seleccionadas, 306 aserciones, sin fallos**, incluidas cuatro pruebas nuevas y regresión de autenticación, roles y autorización de catálogos. Ocho comprobaciones asistidas en Edge y dos capturas posteriores verifican la recuperación y presentación. La regresión completa histórica de 315 pruebas no se repitió en este seguimiento. Evidencia vigente del correctivo: [sprint-2-ui-s2-02.json](evidence/sprint-2-ui-s2-02.json). La revisión humana continúa pendiente; usar el [guion personal de aceptación](sprint-2-human-acceptance.md).

## 1. Historias implementadas y trazabilidad

Las rutas están en `routes/web.php`, `routes/auth.php` y `routes/catalog.php`. Los nombres de clases siguientes se resuelven bajo `app/` y `tests/Feature/`; las pruebas de catálogo están en `tests/Feature/Catalog/`. La evidencia agregada es [sprint-2-acceptance.json](evidence/sprint-2-acceptance.json); el JUnit identifica individualmente los casos ejecutados.

| Requisito / historia | Código principal | Pruebas existentes ejecutadas | Recorrido asistido |
|---|---|---|---|
| Acceso seguro / HU-01 | `Http/Requests/Auth/LoginRequest`, `AuthenticatedSessionController`, `EnsureUserIsActive` | `AuthenticationTest` | AF-01/02/04/35 |
| Usuarios / HU-02 | `UserController`, `UserRequest`, acciones `Users` | `UserManagementTest`, `AdministrativeConcurrencyTest` | AF-03/04/06 |
| Roles configurables / HU-03 | `RoleController`, `SaveRole`, `SecurityMutation`, Policies | `RbacManagementTest`, `PermissionAuthorizationTest`, `RbacSchemaTest` | AF-05/06/07/33/34 |
| Trabajadores sin cuenta / HU-04 | `WorkerController`, `WorkerRequest`, `Worker` | `WorkerManagementTest` | AF-08 |
| Categorías / HU-05 | `CategoryController`, `SaveCategory`, Requests/Policy | `CategoryManagementTest`, `CatalogIntegrityTest` | AF-09/10 |
| Unidades de medida / HU-06 | `MeasurementUnitController`, `SaveMeasurementUnit`, Requests/Policy | `MeasurementUnitManagementTest`, `CatalogAuthorizationTest` | AF-11/12 |
| Atributos por categoría / HU-07 | `CatalogAttributeController`, `CategoryAttributeController`, acciones y validadores | `TechnicalAttributeManagementTest`, `CategoryAttributeManagementTest`, `TechnicalAttributeIntegrityTest` | AF-13 a AF-16 |
| Productos, características e historial / HU-08 | `ProductController`, `SaveProduct`, `ProductAttributeValidator`, `ProductDuplicateFinder`, `ProductAttributeHistory` | `ProductManagementTest`, `ProductAttributeValueTest`, `ProductDuplicateTest`, `ProductHistoryTest`, `ProductIntegrityTest` | AF-17 a AF-24 |
| Ubicaciones y jerarquía / HU-09 | `LocationController`, `SaveLocation`, `LocationHierarchy` | `LocationManagementTest`, `LocationConcurrencyTest`, `LocationIntegrityTest` | AF-25/26 |
| Asociaciones producto–ubicación / HU-08 | `ProductLocationController`, `SaveProductLocation`, `ProductReferences` | `ProductLocationManagementTest`, `ProductConcurrencyTest` | AF-27/28 |
| Identidades individuales / HU-10 | `IndividualUnitController`, `SaveIndividualUnit`, Requests/Policy | `IndividualUnitManagementTest`, `IndividualUnitHistoryTest`, `IndividualUnitIntegrityTest`, `IndividualUnitConcurrencyTest` | AF-29 a AF-32 |
| Permisos, formularios y presentación | Policies, middleware, Blade y `public/css/sga.css` | Clases de autorización y regresión de los dos sprints | AF-33 a AF-36 |

## 2. Requisitos y criterios de aceptación

- Conservar un rol por usuario, con capacidades configurables verificadas en el servidor; impedir perder el último acceso administrativo efectivo y revocar sesiones cuando cambien capacidades.
- Mantener a los trabajadores independientes de las cuentas de acceso.
- Validar códigos, símbolos, tipos, precisión, límites y pertenencia de atributos a categorías. Conservar identidades y referencias históricas.
- Guardar productos y valores de forma atómica; advertir candidatos coincidentes sin fusionarlos ni certificar equivalencia técnica.
- Conservar una jerarquía acíclica de ubicaciones y proteger dependencias activas e históricas.
- Registrar unidades solamente para productos individuales activos; preservar identificador, producto, serie informada y ubicación inicial según las reglas de HU-10.
- No generar existencias con altas de catálogo o asociaciones de ubicación. No implementar documentos ni movimientos.
- Interfaz en español, utilizable con teclado y en tamaños acordados; aceptación visual y de usabilidad a cargo del responsable funcional.

## 3. Casos ejecutados y resultados reales

### Regresión automatizada

Se ejecutó nuevamente `php vendor/bin/phpunit --log-junit <archivo externo>` desde el proyecto, con los controles de `tests/bootstrap.php` y `tests/TestCase.php`: configuración sin caché, `APP_ENV=testing`, conexión MySQL, `DB_URL` vacío y comprobación de `SELECT DATABASE()` antes de migrar. Destino exclusivo de PHPUnit: **`sga_rio_grande_testing`**.

Resultado: **315 pruebas, 2.409 aserciones, cero fallos, errores u omisiones**, 9 min 41,507 s. Incluye regresión del Sprint 1, integridad MariaDB y concurrencia. Véase [JUnit](evidence/sprint-2-acceptance/regression-junit.xml).

El resultado anterior de 2.411 aserciones sigue siendo evidencia histórica válida. Los mismos 315 casos se ejecutan ahora: las dos aserciones netas de diferencia proceden de las ramas de resultado permitidas en dos carreras de `LocationConcurrencyTest` —creación y activación de hijos frente a desactivación del padre—; no se eliminaron casos ni se modificaron pruebas.

### Recorrido asistido en navegador

Se usó Edge real en modo headless, controlado mediante CDP con scripts locales y bibliotecas estándar de Python. Se enviaron formularios, solicitudes desde el navegador y eventos Tab; se tomaron capturas reales. **Es verificación asistida/programática, no una sesión manual realizada por el usuario ni una suite PHPUnit sobre la principal.** Se utilizó exclusivamente el entorno de aceptación adicional autorizado; no se usaron sus registros en testing.

En la matriz, **E** significa que se ejecutaron las comprobaciones indicadas y **P** que queda parte del alcance original pendiente de revisión humana o de un caso adicional. Una fila E no equivale a aprobación funcional formal. Los JSON conservan observaciones, respuestas y repeticiones; no se deduce aprobación a partir de una captura.

Se conservaron **162 observaciones del guion y 33 capturas**. No representan 162 casos manuales aprobados: incluyen doce expectativas iniciales no satisfechas, clasificadas y repetidas según la sección 5, además de comprobaciones parciales. La incidencia visual de la página 419 estaba abierta en ese recorrido aunque su rechazo de seguridad era correcto; su resolución posterior se registra en la sección 5.

Estos conteos y el JSON agregado original corresponden al recorrido anterior, cuyo estado se conserva como evidencia histórica. El correctivo posterior añade ocho comprobaciones asistidas y dos capturas; no transforma observaciones anteriores en aceptación humana ni reemplaza sus archivos originales.

| Caso | Alcance efectivamente comprobado | Situación / referencia de la matriz original |
|---|---|---|
| AF-01 | Invitado redirigido; acceso válido; errores para clave inválida y usuario inactivo | E / HU-01, SEC-02 |
| AF-02 | Cierre de sesión y acceso posterior rechazado; límite de intentos con mensaje español | E / HU-01 |
| AF-03 | Alta y edición de usuario con rol configurable; correo duplicado y contraseña débil rechazados | E / HU-02 |
| AF-04 | Desactivación de cuenta; revocación de permiso del Encargado invalida sesión | E / HU-02/03 |
| AF-05 | Alta/edición de rol, asignación/revocación y denegación efectiva de capacidad retirada | E / HU-03 |
| AF-06 | Desactivación del único administrador y retiro de capacidad administrativa bloqueados | E / HU-03 |
| AF-07 | Auditoría administrativa accesible al administrador, con cambios demostrativos registrados | E / HU-03 |
| AF-08 | Dos trabajadores sin crear cuentas; edición y cambios de estado | E / HU-04 |
| AF-09 | Alta de categorías; duplicados y campos vacíos rechazados con errores españoles | E parcial; edición/búsqueda visual exhaustiva P / CAT-01/02/04 |
| AF-10 | Cambio de estado de categoría libre y bloqueo con productos activos | E / CAT-03 |
| AF-11 | Altas con distintas dimensiones; precisión −1 y 7 rechazada | E parcial; extremos válidos 6 y edición completa P / MED-01/02 |
| AF-12 | Símbolos `L` y `l` distintos, repetido exacto rechazado; precisión de unidad utilizada protegida | E / MED-03/04 |
| AF-13 | Cuatro tipos de atributo; unidad en texto rechazada | E / ATR-01/02 |
| AF-14 | Cambio de tipo de atributo asociado rechazado | E parcial; variantes de unidad respaldadas por suite / ATR-03 |
| AF-15 | Asociaciones, orden, obligatoriedad e identidad; duplicado y límites invertidos rechazados | E parcial; endurecimiento de obligatoriedad cubierto por suite / ASC-01/02/03 |
| AF-16 | Desactivar/reactivar asociación opcional y conservar fecha histórica | E / ASC-04 |
| AF-17 | Alta individual/prestable y por cantidad; impedir guardar prestable por cantidad | E / PRO-01/04 |
| AF-18 | Código interno repetido rechazado | E parcial; repetición contra inactivo respaldada por suite / PRO-02 |
| AF-19 | Obligatorio ausente y atributo no perteneciente a la categoría rechazados | E parcial; todas las variantes de tipos en suite / PRO-05 |
| AF-20 | Falso y fecha conservados; decimal de precisión excesiva rechazado | E parcial; cero y negativos técnicos respaldados por suite / VAL-01/05 |
| AF-21 | Alta inválida sin producto parcial; fallo interno y rollback comprobados por suite | E parcial; inyección de fallo exclusivamente automatizada / PRO-06 |
| AF-22 | Candidatos, advertencia de equivalencia no certificada y confirmación de producto distinto | E / PRO-07; evaluación técnica humana de candidatos P |
| AF-23 | Editar característica, consultar revisiones, vaciar y volver a informar booleano opcional | E / VAL-04 |
| AF-24 | Filtros de productos y rechazo 409 de edición obsoleta | E parcial; paginación visual con muchos registros P / PRO-08 |
| AF-25 | Sector/estante/gabinete, árbol y rechazo de padre propio/descendiente | E parcial; otros padres inválidos y carreras en suite / LOC-01 a LOC-04 |
| AF-26 | Bloqueo por hijos y dependencias de ubicación | E / LOC-05 |
| AF-27 | Varias ubicaciones iniciales; duplicado y destino no almacenable rechazados | E parcial; variantes de destino inactivo en suite / PLC-01/02 |
| AF-28 | Unidad asignada impide desactivar asociación | E / PLC-03; unidades inactivas también cubiertas por suite |
| AF-29 | Alta individual, rechazo de producto por cantidad y ausencia de tablas operativas | E parcial; producto inactivo en suite / IND-01/03 |
| AF-30 | Identificador reservado incluso inactivo, serie repetida rechazada y múltiples series NULL válidas | E / IND-02/04 |
| AF-31 | Destino no almacenable rechazado; cambios de identidad, serie y ubicación bloqueados | E / IND-05/06; FK compuesta en suite |
| AF-32 | Estado, filtros combinados y edición obsoleta 409 | E / IND-06; historial con documentos operativos futuros P |
| AF-33 | Acceso con los tres perfiles; listados del Encargado y Auditor | E parcial; recorrido humano completo de cada formulario P / UI-01 |
| AF-34 | Auditor no accede a altas ni puede crear categoría por solicitud directa; Encargado no administra roles | E parcial; matriz completa de mutaciones por módulo en suite / SEC-01/02/03 |
| AF-35 | Origen no confiable sin token rechazado y texto almacenado escapado | E / SEC-04; ver aclaración del protocolo debajo |
| AF-36 | Capturas de panel, producto, listado de unidades y rol a 1366×768, 768×1024 y 390×844; Tab y desplazamiento interno de tabla | E parcial; revisión humana, foco/contraste, dispositivos físicos y zoom real 200 % P / UI-02 |

El caso de CSRF distingue el protocolo de Laravel 13: `PreventRequestForgery` admite una prueba válida de mismo origen mediante `Sec-Fetch-Site: same-origin`, o un token válido. Omitir token en un `fetch` del mismo origen no debe interpretarse como vulnerabilidad. El caso negativo utiliza metadatos de origen no confiable y omite token. El límite de inicio de sesión utiliza `ValidationException` con respuesta 422 y «Demasiados intentos», no HTTP 429.

## 4. Evidencias visuales y pendientes

Las capturas se publican en [evidence/sprint-2-acceptance](evidence/sprint-2-acceptance/), con hashes SHA-256 e índice en el JSON agregado. Incluyen errores, árbol, formulario y revisión de producto, candidatos, historial, perfiles y las doce combinaciones de pantalla/viewport muestreadas.

- [Defecto de etiqueta antes](evidence/sprint-2-acceptance/02-role-label-before.png) y [corrección](evidence/sprint-2-acceptance/12-role-label-fixed.png).
- [Revisión de duplicados](evidence/sprint-2-acceptance/08-duplicate-review.png).
- [Formulario de producto móvil](evidence/sprint-2-acceptance/ui-product-form-390.png) y [panel en tableta](evidence/sprint-2-acceptance/ui-panel-768.png).
- [Tabla móvil, columnas finales](evidence/sprint-2-acceptance/ui-individual-mobile-right.png): utiliza desplazamiento interno, sin ensanchar la página.
- [Historial con revisiones desplegadas](evidence/sprint-2-acceptance/20-expanded-history.png).
- [Rechazo 419 original](evidence/sprint-2-acceptance/19-cross-origin-419.png), antes de corregir su traducción.
- Corrección posterior: [419 en escritorio](evidence/sprint-2-ui-s2-02/419-es-desktop.png) y [419 móvil](evidence/sprint-2-ui-s2-02/419-es-mobile.png). El archivo anterior conserva el defecto original; estas dos capturas muestran su corrección.

Pendientes: recorrido del responsable funcional, evaluación de comprensión de errores y advertencias, teclado completo, contraste/foco, zoom real al 200 %, otros navegadores/dispositivos y capturas adicionales de los casos P. La captura de reflujo a 683 px no se presenta como prueba de zoom real.

## 5. Incidencias y resolución

**UI-S2-01, menor, corregida:** el formulario de roles mostraba `individual_units` como leyenda. Se añadió «Unidades individualizadas» a `resources/views/roles/form.blade.php`. Confirmación en navegador y capturas antes/después; no cambia autorizaciones.

**UI-S2-02, menor, corregida y verificada técnicamente; revisión humana pendiente:** la página genérica HTTP 419 mostraba «Page Expired». Con autorización posterior se añadió `resources/views/errors/419.blade.php`, extendiendo `layouts.app` y reutilizando su CSS. Presenta «Solicitud no válida o caducada», instrucciones en español y un enlace local «Volver al inicio». Conserva 419, no reenvía formularios y no expone la excepción. No se modifican middleware, rutas, `.env`, dependencias ni migraciones. La captura `19-cross-origin-419.png` conserva el defecto original; las nuevas capturas y pruebas están en `evidence/sprint-2-ui-s2-02/`.

Las cuatro pruebas de `ExpiredRequestPageTest` comprueban HTML español para invitado, rechazo de token inválido para usuario autenticado sin alta de categoría, respuesta JSON nativa y acceso normal con token válido. Se ejecutaron junto con `AuthenticationTest`, `RbacManagementTest`, `CatalogAuthorizationTest`, `ProductAuthorizationTest` e `IndividualUnitAuthorizationTest`: **39 pruebas y 306 aserciones aprobadas**, sin omisiones. `Pint --test` del archivo nuevo y `git diff --check` pasaron. PHPUnit utiliza únicamente `sga_rio_grande_testing`, con sus controles existentes de aislamiento; no se crearon migraciones nuevas ni se aplicaron migraciones a la principal o a aceptación.

En el entorno de aceptación existente se envió un formulario sin token desde un origen opaco utilizando un código ya existente. La respuesta fue 419. Se comprobaron mensaje español, marca, enlace local, foco por Tab, ausencia de desbordamiento a 1366×768 y 390×844, navegación al login y acceso con una cuenta demostrativa existente. No se crearon registros demostrativos. Los hashes anteriores/posteriores confirman conservación de las 21 tablas de negocio y configuración comparadas en aceptación; solo se excluyen sesiones, caché y bloqueos de caché, propios del funcionamiento web. La comparación completa de la principal y `.env` también coincide. Estas verificaciones fueron asistidas, no humanas.

Los registros originales conservan expectativas fallidas del guion: serialización de nodos DOM y arrays, selección de identificadores de asociaciones, formularios GET sin `action`, expectativa 422 en previsualización en lugar del guardado final, uso de PATCH directo seguido de redirect 302, expectativa HTTP 429 y omisión de token desde mismo origen. Se corrigieron las comprobaciones y se repitieron los casos pertinentes. El intento de imponer metadatos de origen con CDP no produjo una solicitud válida y se reemplazó por un formulario real desde un origen opaco. No se cambió la aplicación para hacer coincidir esas expectativas. El seed demostrativo inicial también se corrigió para usar el valor permitido `console` en auditoría; su transacción fallida no dejó cuentas parciales.

Las observaciones iniciales no se eliminan ni se convierten retroactivamente en éxitos: el JSON distingue el defecto real de interfaz, las correcciones del guion y sus repeticiones. Los resultados válidos finales se interpretan junto con esa trazabilidad.

## 6. Permisos y seguridad

La principal conserva tres roles, 48 permisos y 95 vínculos: 48 capacidades activas para Administrador, 36 para Encargado y 11 para Consulta/Auditor. No se conceden capacidades por nombre de rol en las operaciones. La cuenta con rol configurable y las revocaciones del recorrido existen solo en aceptación.

Se comprobaron último administrador, revocación de sesiones, solicitudes no autorizadas, errores de autenticación y escape de texto. La auditoría administrativa registra cambios de acceso; el historial técnico tiene su mecanismo propio. No se afirma auditoría administrativa universal de todas las operaciones del catálogo.

La principal continúa sin cuentas ni administrador real. Su creación requiere datos reales y autorización específica; no se ejecuta aquí `sga:create-admin` sobre ella.

## 7. Base de datos, migraciones y entorno

`SELECT DATABASE()` confirmó `sga_rio_grande`; PHP 8.3.10, Laravel 13.35.0 y MariaDB 10.4.32. Principal: 24 tablas, 27 migraciones aplicadas, ninguna pendiente. Los conteos y hashes de esquemas/datos, archivos de migración y `.env` anteriores y posteriores se comparan en la evidencia agregada. No se repiten migraciones de HU-08/HU-10 ni se crean datos demostrativos en la principal.

Se creó **`sga_rio_grande_acceptance_s2`**, con verificación explícita de destino antes de aplicar las 27 migraciones a ese esquema nuevo. No se ejecutaron seeders generales. Las cuentas demostrativas iniciales se crearon expresamente para los tres roles reconocidos; los catálogos se registraron mediante los flujos web. El JSON guarda conteos finales y demuestra separación de destinos.

Directorio externo: `C:\Users\jcsem\SGA-acceptance\sprint-2-20261019`, con acceso limitado al usuario local y SYSTEM. Contiene copia de ejecución, scripts de operación, capturas, registros y archivos privados `runtime.private.json` y `accounts.private.json`. Las credenciales y claves no se publican. No se copia ni modifica `.env`; la configuración se proporciona al proceso. Sesiones, caché, cookie y almacenamiento son independientes.

Servidor de aceptación: `http://127.0.0.1:8012`, PHP 8.3.10 existente. La configuración de Apache/XAMPP carga PHP 8.2.12 y no se utiliza para esta aceptación. No se actualizan herramientas ni se instalan paquetes. La caché de opcode se habilita solo en el proceso temporal, sin modificar archivos INI.

El entorno y sus datos se conservan para revisión; no hay limpieza destructiva autorizada. Para reutilizarlo, consultar primero su estado y el puerto. Con el servidor detenido, `python C:\Users\jcsem\SGA-acceptance\sprint-2-20261019\restart.py` inicia únicamente la copia aislada. No repetir `setup.py`, migraciones ni recorridos de alta sobre registros existentes.

## 8. Riesgos y dependencias preliminares del Sprint 3

- MariaDB 10.4 está fuera de mantenimiento; planificar actualización antes de producción, fuera de esta etapa.
- El respaldo de despliegue tiene integridad verificada, pero no un ensayo de restauración registrado.
- El mutex global preserva coherencia y serializa escrituras; este recorrido no es una prueba de carga.
- Falta completar la evidencia de revisiones humanas reservadas, configurar el primer administrador real y validar la numeración académica del backlog. La aceptación declarada posterior del usuario figura en la sección 9.
- IND-06 con documentos operativos no puede validarse todavía; se conserva la identidad básica comprobada en Sprint 2.

| Tema preliminar | Dependencia o decisión para el diseño futuro |
|---|---|
| Recepción / remitos | Definir campos y estados de ingreso/egreso y sus confirmaciones; los remitos y las entregas permanecen independientes. |
| Existencias | Definir saldos por producto y ubicación y la recepción inicial; un alta de identidad no es ingreso físico. |
| Herramientas y equipos | Vincular cada identificador a su situación física y documentos, preservando historia. |
| Confirmación | Transacción de documento, movimientos y efecto en saldos; comprobar permisos vigentes. |
| Idempotencia | Una confirmación repetida o concurrente no genera movimientos adicionales; definir identidad única de operación y restricciones SQL. |
| Retiros | El Encargado selecciona ubicaciones; bloquear saldos/unidades afectados y rechazar existencias negativas. |
| Cantidades | DECIMAL y precisión de unidad aprobada, sin FLOAT; decidir relación entre cantidad y unidades físicas individuales. |
| Correcciones | Diseñar autorizaciones y trazabilidad de anulaciones/ajustes; conservar documentos confirmados y relaciones históricas. |

La revisión original del Sprint 2 no creó componentes del Sprint 3. Su implementación posterior está autorizada y se documenta separadamente en `sprint-3.md`; no se altera esta evidencia histórica.

## 9. Resultado de aceptación

**Aceptación funcional declarada por el usuario para avanzar, con reservas de evidencia humana.** La declaración «todo esta bien podemos continuar», ratificada mediante autorización de continuación después de la propuesta, se registra sin inferir una firma o ejecución de los casos manuales pendientes. Véase `evidence/sprint-2-user-conformity.json`.

El [guion de aceptación humana](sprint-2-human-acceptance.md) utiliza los registros existentes, especifica qué revisar personalmente y deja los resultados sin prellenar. UI-S2-02 tiene verificación técnica posterior; el responsable aún debe revisar su presentación y la recuperación.

Se conserva como reserva la ejecución documentada de los casos humanos pendientes, incluidas las revisiones visuales y de accesibilidad. No se detectaron incidencias técnicas bloqueantes en los resultados registrados. La conformidad declarada permite avanzar y no completa retrospectivamente esa matriz ni acredita una firma formal externa.

## 10. Retrospectiva preliminar

- Funcionó la entrega por dependencias, las migraciones aditivas y los respaldos de despliegue; la revisión actual conserva la principal.
- La suite cubre integridad MariaDB, permisos, concurrencia y regresión, con trazabilidad por etapas.
- Mejorar la preparación temprana del entorno de aceptación y de sus datos, y revisar etiquetas al incorporar grupos de permisos.
- Separar claramente prueba HTTP, recorrido de navegador, revisión visual del agente y aceptación humana.
- Documentar los contratos reales —revisión/guardado, redirecciones, validación 422 y protección por origen/token— antes de automatizar expectativas.
- No hay métricas de velocidad ni opiniones del equipo recogidas; completar la retrospectiva con el responsable al cerrar la aceptación.

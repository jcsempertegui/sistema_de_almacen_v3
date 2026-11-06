# Navegación y direcciones en español — 5 de noviembre de 2026

> Informe de la corrección local inicial. Su consolidación posterior, verificación completa por lotes y publicación se registran en el [cierre del proyecto](cierre-proyecto.md); los estados locales de esta etapa se conservan como antecedente.

## Problema y corrección

Préstamos y devoluciones mostraban una barra de navegación adicional sobre el título, duplicando las opciones del menú lateral. Se retiró de listado, alta, edición y detalle. Las acciones del documento, como registrar devolución, editar borrador o confirmar, se conservan con sus permisos.

También se retiró la barra duplicada de ajustes/compensaciones y se puso un título específico para cada pantalla. En reportes se conservan las pestañas para consultas diferentes del mismo grupo; no se muestra esa barra cuando el usuario solo tiene una consulta disponible.

## Direcciones

| Antes | Ahora |
|---|---|
| `/operaciones/loans` | `/operaciones/prestamos` |
| `/operaciones/deliveries` | `/operaciones/entregas` |
| `/operaciones/returns` | `/operaciones/devoluciones` |
| `/reportes/stock` | `/reportes/existencias` |
| `/reportes/movements` | `/reportes/movimientos` |
| `/reportes/responsibilities` | `/reportes/responsabilidades` |
| `/correcciones/adjustment` | `/correcciones/ajustes` |
| `/correcciones/compensation` | `/correcciones/compensaciones` |
| `/productos/create`, `/productos/5/edit` | `/productos/crear`, `/productos/5/editar` |
| `/login`, `/logout` | `/ingresar`, `/cerrar-sesion` |

La misma traducción se aplica a los demás recursos, tipos de reporte y acciones de autorización. La comprobación de salud pasa a `/estado-sistema`.

`RutasEspanol` centraliza la correspondencia de segmentos públicos. Se usan los verbos de recursos de Laravel y su formateador de URL. Los parámetros de ruta se traducen antes de las validaciones y Policies, manteniendo los identificadores existentes de permisos y operaciones. No se reinterpretan estados históricos ni se cambian tablas, columnas o migraciones. Los códigos internos de compatibilidad no se presentan como nombres de opciones al usuario.

Los GET/HEAD antiguos redirigen a su dirección en español y conservan la consulta. Las solicitudes POST no se redirigen: se conservan los puntos compatibles de autenticación y las variantes antiguas de operaciones/autorización, sujetos al mismo middleware CSRF y a los permisos existentes. No se convierten acciones de escritura en GET.

También se traducen filtros y paginación: `buscar`, `pendientes`, `desde`, `hasta`, `formato`, `pagina` y `custodia`, así como valores tales como `estado=confirmado`, `tipo=ingreso` y `formato=imprimir`. Los códigos de producto, nombres y texto libre no se traducen. Los filtros se convierten a los identificadores esperados por las validaciones únicamente durante la petición; no modifican valores persistidos.

La revisión de cierre completó las correspondencias de `pagina_unidades`, `solo_raices`, tipos de ubicación, tipos de atributo y modo por cantidad. Las verificaciones de paginación mantienen filtros y páginas independientes con sus nombres públicos en español. Sus resultados y la regresión de cierre están separados de la tanda inicial de 51 casos descrita abajo.

`GeneradorUrlEspanol` extiende el generador de Laravel para presentar la consulta en español al construir enlaces GET. Conserva los extensores del framework para sesión, claves de firma y cambios de colección de rutas. Los enlaces del paginador se presentan con la misma correspondencia. Los formularios GET existentes siguen siendo compatibles: al enviarlos, cualquier filtro antiguo se redirige a su forma pública y después se valida en el servidor. Los POST conservan sus campos y comprobaciones. No se declara que todo identificador interno del framework o del historial esté traducido.

## Archivos

- `app/Support/RutasEspanol.php`, `app/Support/GeneradorUrlEspanol.php` y `app/Http/Middleware/RedirigirRutasAntiguas.php`.
- `app/Providers/AppServiceProvider.php`, `bootstrap/app.php` y rutas de autenticación, catálogo, operaciones y control.
- Vistas de operaciones, índice de correcciones, reportes y paginación.
- `tests/Feature/NavegacionEspanolTest.php`; ajuste de verificaciones de autenticación y CSV para utilizar sus rutas y filtros canónicos.

## Verificación

Las pruebas se ejecutan únicamente en `sga_rio_grande_testing`, con el control existente de base, servidor, puerto y directorio de datos antes de cualquier escritura. La regresión seleccionada incluye rutas antiguas/canónicas, menú activo único, formularios, permisos de Consulta/Auditor, autenticación, CSRF, préstamos, devoluciones, correcciones, reportes y rendimiento de consultas. No representa aceptación humana ni una regresión completa de los cinco sprints.

Resultado consolidado por caso: **51 pruebas distintas y 546 aserciones**, sin fallos pendientes. El lote de 51 casos detectó dos verificaciones de CSV que concatenaban filtros en inglés y esperaban respuesta directa; se actualizaron para usar el generador de rutas. Los nueve casos de navegación/control se ejecutaron de nuevo y aprobaron **207 aserciones**. Los otros 42 casos aprobaron en el lote anterior. La consolidación conserva el resultado más reciente por nombre de caso, sin sumar ejecuciones repetidas; véase [evidencia](evidence/navegacion-espanol-2026-11-05.json). No se presenta como una ejecución única completamente aprobada. Pint sobre los doce archivos PHP de esta corrección y `git diff --check` aprobaron.

Las comprobaciones HTTP reales, sin autenticación, confirmaron `/login` → `/ingresar`, `/operaciones/loans?pending=1` → `/operaciones/prestamos?pendientes=1` y `/reportes/stock` → `/reportes/existencias`, con estado 302. `/ingresar` respondió 200. Esto no sustituye una revisión visual humana de las pantallas autenticadas.

Las salidas se conservan fuera del repositorio bajo `C:\Users\jcsem\SGA-evidence\optimizacion-consultas-20261104\navegacion-*`. No se modifican `.env`, dependencias ni datos principales. Los cambios anteriores de optimización permanecen en la misma rama local. No se realiza commit ni push de esta corrección.

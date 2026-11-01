# Panel de operaciones — 31 de octubre de 2026

Ampliación autorizada del panel principal sobre `034c92f062d2438678740c3b54a905beb218ebca`. No introduce un nuevo sprint, movimientos, tablas, permisos ni dependencias.

## Información operativa

| Elemento | Definición | Autorización antes de consultar |
|---|---|---|
| Productos con existencias | Productos activos distintos con al menos una posición física mayor que cero | `stock.view` |
| Productos sin existencias | Productos activos sin posición física positiva; incluye productos sin ingreso inicial | `stock.view` |
| Herramientas prestadas | Custodias vigentes enlazadas a un ítem de préstamo confirmado | `loans.view` |
| Herramientas vencidas | Herramientas todavía en custodia cuyo préstamo tiene vencimiento anterior al día actual en Bolivia | `loans.view` |
| Vencimiento hoy | Herramientas todavía en custodia cuyo préstamo vence hoy, sin considerarlas vencidas aún | `loans.view` |
| Borradores | Documentos en estado `draft`, separados entre remitos, aperturas, entregas, préstamos y devoluciones | Permiso de consulta de cada módulo |
| Últimos movimientos | Últimas cinco líneas del registro único, por fecha de registro e ID descendentes; incluye ajustes y compensaciones | `movements.view` |

No se suman cantidades con unidades incompatibles. La custodia actual conserva las obligaciones de trabajadores inactivos y las reaperturas por compensación. Los préstamos sin fecha de vencimiento no se consideran vencidos. Los borradores no representan operaciones físicas. Los indicadores se actualizan al cargar la página; no hay actualización automática ni caché compartida entre usuarios.

Los enlaces de herramientas abren los préstamos pendientes, donde se revisa cada documento. Los enlaces de existencias abren la consulta general; no aplican un filtro nuevo de productos sin existencias. Los contadores de borradores sí abren el listado con `status=draft`, respetando la separación de permisos de aperturas y remitos. El catálogo y la administración se conservan en un bloque desplegable, además de la navegación lateral existente.

## Implementación

- `app/Services/DashboardOperations.php`: consultas de lectura limitadas por permisos.
- `app/Http/Controllers/DashboardController.php`: incorpora el resumen y conserva las gráficas.
- `resources/views/dashboard.blade.php`: ordena resumen, pendientes, accesos diarios, gráficas y catálogo.
- `resources/views/dashboard/operations.blade.php`: tarjetas, borradores y últimos movimientos con texto escapado, estados vacíos y enlaces a consultas existentes.
- `public/css/sga.css`: componentes adaptables a escritorio y móvil, sin compilación ni recursos externos nuevos.
- `tests/Feature/DashboardOperationsTest.php`: cobertura de cálculos, permisos, vencimientos y presentación segura.

## Verificaciones ejecutadas

Antes de ejecutar PHPUnit se verificó explícitamente `SELECT DATABASE()` mediante `TestingDatabaseGuard`: `sga_rio_grande_testing`, servidor `DESKTOP-U7NQKIM`, puerto `3306`, directorio `c:/xampp/mysql/data`. Los controles del bootstrap y de cada conexión permanecen activos. Las pruebas usan transacciones en testing.

Comando ejecutado:

```console
php vendor/bin/phpunit --colors=never --stop-on-failure --filter 'DashboardOperationsTest|DashboardChartsTest|VisualNavigationTest'
```

Resultado real: **19 pruebas, 102 aserciones, todas correctas**, tiempo informado por PHPUnit `01:28.006`, PHP 8.3.10. Se verificaron ceros reales, exclusión de borradores del saldo, conteo de productos distintos, separación de remitos y aperturas, ausencia de consultas a módulos revocados, límite y orden de movimientos, escape HTML, vencimientos locales, devoluciones y regresión de gráficas/navegación por rol. La regresión de gráficas también cubre devoluciones compensadas que reabren custodia.

También se ejecutaron `php -l` para servicio y prueba, Laravel Pint en modo verificación para los tres archivos PHP afectados y `git diff --check`, sin incidencias. No se ejecutó la regresión completa del sistema.

Se revisó visualmente una representación del panel con datos actuales vacíos y recursos de la aplicación, sin crear fixtures principales. Chrome se verificó a 1440, 390 y 320 píxeles mediante emulación de viewport; los anchos de contenido fueron 1425, 390 y 320 respectivamente, sin desbordamiento horizontal. Las capturas y herramientas de revisión se guardan fuera del repositorio en `C:/Users/jcsem/SGA-evidence/dashboard-20261031`, junto con `responsive-results.json`. Esta revisión técnica de presentación no constituye aceptación humana del responsable funcional ni prueba completa del flujo de navegador autenticado.

## Conservación

No se ejecutaron migraciones ni seeders, ni se modificaron datos principales, `.env`, dependencias o permisos. La lectura de verificación confirmó una cuenta local existente y 43 migraciones. `.env` conserva SHA-256 `7614a674f1246e3cd4a2696c6741785f80c2f5b47b55b0d27986bd2aabe88f3f`. No se realizaron commits ni push.

Esta ampliación incorpora vencimientos en el nuevo resumen; el servicio de gráficas descrito en `dashboard-charts-2026-10-25.md` conserva su definición y no calcula esos vencimientos.

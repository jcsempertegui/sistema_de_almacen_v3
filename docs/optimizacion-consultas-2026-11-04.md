# Optimización de consultas — 4 de noviembre de 2026

> Informe de la validación local inicial. La consolidación posterior con navegación y los cinco sprints se registra en el [cierre del proyecto](cierre-proyecto.md). Las referencias a rama local y publicación pendiente describen esta etapa, no el estado posterior del repositorio.

## Alcance y publicación anterior

El avance de traducción, navegación y selectores rápidos se publicó en `main`, commit `192e0a51a89096166a1e6e615c86d9e148b645f8`. Esta primera etapa de optimización se desarrolla localmente en `optimizacion/consultas-lectura`.

No incorpora migraciones, índices, paquetes ni cambios de `.env`. No modifica datos de la base principal. Las mediciones y pruebas utilizan exclusivamente `sga_rio_grande_testing`, con los controles existentes de identidad de servidor, puerto, directorio de datos y `SELECT DATABASE()` antes de escribir.

## Cambios

- `app/Models/Rol.php` y `app/Http/Middleware/VerificarUsuarioActivo.php`: una lectura de los permisos del actor durante cada respuesta GET/HEAD. La copia pertenece a esa instancia del rol y se elimina en `finally`; no se conserva entre peticiones. Cada petición sigue verificando usuario, rol y versiones de sesión/permisos. Una transacción anidada evita esa copia y vuelve a consultar con bloqueo. Las solicitudes de escritura mantienen las consultas y bloqueos anteriores.
- `app/Services/OperacionesPanel.php`: agrupa los conteos de artículos con/sin existencias, las custodias pendientes/vencidas y los borradores de inventario. Mantiene los filtros por permisos y cuenta cada artículo una sola vez aunque aparezca en varias ubicaciones. Las custodias actuales siguen siendo la referencia de herramientas pendientes.
- `app/Http/Controllers/Control/ControladorReporte.php`: obtiene el resumen con una sola consulta agregada y reutiliza su total para la paginación. Conserva filtros, detalle, límites de impresión, descarga CSV y autorización.
- `tests/Feature/ReadQueryPerformanceTest.php`: mide tres pantallas, limita consultas repetidas y comprueba revocación, transacciones anidadas y limpieza tras excepciones.

## Medición reproducible

Escenario aislado: un artículo por cantidad y una recepción confirmada. Se cuenta el SQL registrado por Laravel durante la respuesta HTTP completa, incluida la navegación. No se incluyen las comprobaciones PDO internas del control de aislamiento.

| Pantalla | Consultas antes | Consultas después | Lecturas de permisos antes → después |
|---|---:|---:|---:|
| Panel | 83 | 14 | 66 → 1 |
| Artículos | 53 | 9 | 45 → 1 |
| Reporte de existencias | 52 | 8 | 43 → 1 |

Las cifras son reales para este escenario, no una promesa de tiempos ni una medición de carga concurrente. No se comparan duraciones de procesos con cachés calientes/frías diferentes. La aceptación visual humana y una medición de latencia en uso diario siguen pendientes.

La prueba admite presupuestos de 18 consultas en el panel y 12 en cada una de las otras dos pantallas, con un máximo de una lectura de permisos del actor. Para registrar métricas JSON puede definirse `SGA_QUERY_EVIDENCE` solamente en el proceso de pruebas, apuntando a un archivo fuera del repositorio. No requiere modificar `.env`.

## Verificación

Comandos ejecutados desde la raíz, con PHP 8.3.10 y MariaDB 10.4.32:

```powershell
php vendor/bin/phpunit --colors=never --filter 'ReadQueryPerformanceTest|DashboardOperationsTest|DashboardChartsTest|DetailedReportsTest|PermissionAuthorizationTest|AuthenticationTest|RolesTest|RoleManagementTest|AdministrativeConcurrencyTest|ControlHttpTest|SearchableSelectorsTest|SimplifiedInterfaceTest'
php vendor/bin/phpunit --colors=never --filter 'RbacManagementTest|UserManagementTest'
```

El nombre `RoleManagementTest` del primer filtro no corresponde a una clase existente; la gestión de roles se verifica explícitamente mediante `RbacManagementTest` en el segundo lote.

Resultados reales: **60 pruebas/470 aserciones** en el primer lote y **25 pruebas/202 aserciones** en el segundo, sin errores, fallos ni omisiones. Son **85 pruebas distintas/672 aserciones**, exclusivamente en testing. Incluyen revocación de sesiones, permisos durante mutaciones, protección del último administrador, concurrencia administrativa, filtros, CSV, panel y selectores. Pint sobre los cinco archivos PHP modificados y `git diff --check` también aprobaron. No representan una regresión completa de los cinco sprints ni aceptación humana.

El SHA-256 de `.env` continúa siendo `7614A674F1246E3CD4A2696C6741785F80C2F5B47B55B0D27986BD2AABE88F3F`. No hay diferencias de migraciones, archivos de dependencias ni controles de aislamiento respecto al commit publicado.

Los registros originales de medición, salidas de PHPUnit y XML JUnit se conservan fuera del repositorio, en `C:\Users\jcsem\SGA-evidence\optimizacion-consultas-20261104`. La evidencia pública solo resume resultados técnicos y no contiene credenciales ni volcados SQL.

## Próximas evaluaciones

Antes de añadir índices, medir los planes `EXPLAIN` con filtros representativos. Evaluar por separado los selectores con catálogos mayores, las consultas del kardex y los reportes de intervalos largos. Esta etapa no introduce caché persistente de saldos, permisos o custodias.

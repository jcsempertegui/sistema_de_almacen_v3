# Panel principal — gráficas operativas

Fecha: 25 de octubre de 2026, America/La_Paz. El usuario aprobó la primera apariencia y solicitó gráficas relevantes. Se mantienen los cinco sprints y los flujos de inventario existentes; este cambio añade consultas y presentación en el panel, sin modificar datos ni esquema.

## Gráficas y significado

| Gráfica | Fuente y definición | Permiso comprobado antes de consultar |
|---|---|---|
| Actividad de los últimos 7 días | conteo de filas de `inventory_movements`, por día y dirección; incluye hoy y seis fechas locales anteriores | `movements.view` |
| Entradas frente a salidas | proporción del mismo conteo en los últimos 30 días de calendario, desde las 00:00 del primer día hasta la hora actual | `movements.view` |
| Herramientas pendientes | una herramienta por custodia actual, enlazada a su ítem de préstamo confirmado y trabajador; cinco trabajadores principales y el resto agrupado | `loans.view` |

Las entradas/salidas son **movimientos por línea**, no documentos ni suma de cantidades. No se suman metros, litros, piezas u otras unidades. El registro incluye ajustes y compensaciones ejecutados como movimientos adicionales; no se elimina ni netea el original. Los borradores no generan movimientos y quedan fuera de estas gráficas.

Fechas agrupadas en `America/La_Paz`, a partir de los timestamps UTC del registro. Se excluyen registros posteriores a la hora de consulta y se completan con cero los días sin actividad. No se interpretan las cantidades de la gráfica como saldo disponible.

La custodia usa `individual_unit_custodies.current_loan_item_id`. No cuenta todo el historial de préstamos ni supone que una devolución antigua haya cerrado una obligación reabierta. Conserva obligaciones de trabajadores inactivos si siguen pendientes. No calcula vencimientos ni inventa fechas de devolución. El código de trabajador distingue nombres iguales; nombres y códigos se escapan al renderizar.

## Implementación

- `DashboardController` sustituye la vista directa en la misma ruta `/panel`, conservando los middleware `auth`, `active` y `can:access-panel`.
- `DashboardCharts` consulta únicamente agregados: una consulta agrupada de movimientos y otra de custodias, cuando los respectivos permisos lo permiten. No se consultan ni envían esos datos para un usuario sin acceso; no se comparten cachés de agregados entre usuarios.
- `resources/views/dashboard/charts.blade.php` renderiza barras, anillo y custodia con SVG/CSS. No se añadieron librerías, dependencias, CDN ni endpoints de datos nuevos.
- Cada gráfica ofrece sus valores en tabla desplegable; las SVG tienen título y descripción. Las leyendas indican dirección y conteo, y los estados vacíos muestran ceros reales.
- La hora de actualización corresponde a la apertura/recarga del panel; no hay actualización automática en segundo plano.

El aspecto utiliza la línea visual ya aprobada, basada en [confipetrol2](https://github.com/jcsempertegui/confipetrol2/tree/9be4d7e1cda6d772100f0a08b3e0510f8e46758e): navegación lateral agrupada, azul corporativo, superficies claras y logotipo existente de esa referencia. No se incorporaron sus módulos o métricas ajenos al alcance actual.

## Validación

Pruebas en `sga_rio_grande_testing`, con el alias verificado `sga_testing`, transacciones y fixtures propios. Se comprobaron:

1. Estados vacíos y tablas con ceros, sin divisiones por cero.
2. Conteo por movimiento frente a cantidades distintas y exclusión de borradores.
3. Cambio de día UTC frente a medianoche de Bolivia.
4. Límite inicial inclusivo del período de 30 días y exclusión de registros futuros.
5. Ausencia de consultas y gráficas cuando faltan permisos.
6. Préstamo, devolución y compensación de devolución: reapertura de la obligación actual sin duplicar el cierre histórico.
7. Cinco trabajadores más grupo Otros, total completo y escape de nombres con apariencia de HTML.

La selección final de `DashboardChartsTest`, `VisualNavigationTest` y `AuthenticationTest` aprobó **22 pruebas/132 aserciones** en 4,119 segundos. La regresión completa final aprobó **472 pruebas/3.562 aserciones** en 1 minuto y 47,224 segundos, con 88 MB de memoria. Incluye estabilización, apariencia y gráficas. La primera regresión de esta etapa aprobó 472/3.561 antes de agregar las dos comprobaciones finales de tablas; cada total pertenece a su tanda y puede variar por las ramas válidas de concurrencia. No se suman tandas parciales como si fueran una regresión nueva. Véase la [evidencia](evidence/dashboard-charts-2026-10-25.json).

Pint, sintaxis de `public/js/sga-ui.js` y `git diff --check` aprobados. Las capturas y los datos de prueba no se introducen en la principal. La revisión visual de navegador no se ejecutó en esta fase; la revisión de la fase anterior se detuvo por la comprobación de URL de Computer Use. Las pruebas HTTP/DOM/SVG no sustituyen aceptación visual ni accesibilidad completa.

## Conservación y revisión

La comparación final de las huellas de filas y DDL de las 53 tablas de la principal no encontró diferencias. La configuración privada, las dependencias y las 43 migraciones conservan sus hashes. No se aplicaron migraciones, no se generaron respaldos reales y no se alteraron movimientos, saldos o custodias de la principal. Testing quedó sin fixtures: solo contienen filas sus cinco tablas iniciales de roles, permisos, relaciones, auditorías de migración y migraciones. El resultado final y la limpieza se registran en la evidencia.

Archivos de esta ampliación: controlador y servicio nuevos; ruta del panel; vista del panel y nueva parcial; CSS; `DashboardChartsTest`; adaptación del renderizador de demostración; README y este informe/evidencia. Los cambios anteriores de estabilización y apariencia permanecen locales, sin commit ni push.

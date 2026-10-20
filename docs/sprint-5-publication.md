# Sprint 5 — Seguimiento de publicación

## Autorización y alcance

El 20 de octubre de 2026 el usuario indicó: «todo esta bien de momento continuemos. y actualiza el repositorio». Se registra conformidad de momento y autorización para commit, integración y publicación de la implementación aprobada del Sprint 5 y sus evidencias. La aceptación humana detallada y el cierre académico conservan sus reservas.

La publicación no autoriza migraciones en `sga_rio_grande`, restauraciones reales, cuentas ni datos operativos. No se modifican `.env`, dependencias o las 35 migraciones anteriores.

## Entrega y verificaciones

- Base: `3d368275383ee472e1e58cdb57ed94b0a6e9cd96`; rama de desarrollo: `feature/sprint-5-control-and-close`.
- HU-24 a HU-29: ajustes y compensaciones autorizadas, ciclos de custodia, reportes, Backup y auditoría; ocho migraciones aditivas y once tablas nuevas.
- Regresión ejecutada exclusivamente en testing: **429 pruebas, 3.406 aserciones, cero errores, fallos u omisiones**.
- Cambios posteriores de presentación: **3 pruebas HTTP y 75 aserciones aprobadas**. Las tandas no se suman como casos independientes.
- Antes de publicar se verificaron los hashes de los 390 archivos del manifiesto final: ninguna diferencia. Los cambios de esta publicación son documentales.
- El [informe](sprint-5-implementation.md), el [inventario de 96 archivos de la entrega local](sprint-5-changed-files.md) y la [evidencia de implementación](evidence/sprint-5-implementation.json) describen el estado previo a publicar. Sus indicadores `commit_created`, `merge_executed` y `push_executed` en falso son históricos; no se reescribe esa evidencia.

## Base principal y reservas

Última comprobación de solo lectura de la implementación: **42 tablas, 35 migraciones aplicadas, 81 permisos, 166 vínculos rol–permiso y ocho migraciones S5 pendientes**. Se conservaron datos y estructuras de las tablas previas. Esta publicación no ejecuta comandos de base de datos.

Testing quedó con 53 tablas y 43 migraciones, 123 permisos y 260 vínculos. La generación real de respaldo probada corresponde únicamente a testing. No se ejecutó una restauración real del módulo ni el ensayo completo de las ocho migraciones sobre una copia histórica; requieren una prevalidación autorizada posterior. También permanecen pendientes el primer administrador real, la aceptación personal y el contraste con el catálogo académico externo.

## Publicación

La entrega local de 96 archivos se acompaña del seguimiento de publicación y una regla en `.gitattributes` para conservar los bytes exactos de las evidencias S5, incluidos sus finales de línea. Total de publicación: 98 archivos. No cambia el código probado.

Publicación ejecutada el 20 de octubre de 2026: commit de implementación [`1585a28d69de83a28626c279b83356e627a4d67d`](https://github.com/jcsempertegui/sistema_de_almacen_v3/commit/1585a28d69de83a28626c279b83356e627a4d67d), integración por avance directo a `main` y push sin forzar. `git ls-remote origin refs/heads/main` confirmó exactamente ese commit y el árbol de trabajo quedó limpio. Este seguimiento se registra después en un commit exclusivamente documental.

## Aceptación posterior del usuario

El usuario confirmó «aceptadas, y actualiza el repositorio». Se registra la aceptación declarada de la entrega en el [acta documental](sprint-5-acceptance.md), con [evidencia](evidence/sprint-5-acceptance.json). Esta declaración sucede a los estados históricos anteriores; las comprobaciones de navegador no documentadas, el contraste académico y el despliegue principal conservan sus pendientes específicos. No se ejecutaron nuevas pruebas ni operaciones de base de datos en esta actualización.

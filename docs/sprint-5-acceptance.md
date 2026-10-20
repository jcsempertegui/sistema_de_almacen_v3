# Sprint 5 — Aceptación declarada

Fecha de registro: 20 de octubre de 2026, America/La_Paz.

## Confirmación del usuario

Tras la publicación de la implementación y sus evidencias, el usuario confirmó: **«aceptadas, y actualiza el repositorio»**. Se registra la aceptación declarada de la entrega del Sprint 5 (HU-24 a HU-29) y la autorización para publicar esta actualización documental.

Entrega aceptada: implementación `1585a28d69de83a28626c279b83356e627a4d67d`, con seguimiento publicado en `f43cc407b284a41d58cc8f1bb596ed95592087d6`. No se atribuyen al usuario un nombre, cargo, firma o acta externa no proporcionados.

## Evidencias y alcance

- Regresión automatizada existente, exclusivamente en testing: **429 pruebas y 3.406 aserciones aprobadas**.
- Verificación posterior de presentación: **3 pruebas HTTP y 75 aserciones aprobadas**, sin sumarlas como casos independientes a la regresión.
- Evidencias técnicas: [informe de implementación](sprint-5-implementation.md), [matriz](sprint-5-test-matrix.md), [evidencia original](evidence/sprint-5-implementation.json) y [publicación](sprint-5-publication.md).
- La aceptación proviene expresamente del mensaje del usuario. No acredita por sí misma ejecución de los casos del [guion personal](sprint-5-operator-guide.md), resultados de navegador o capturas que no se han aportado. La evidencia original conserva su estado histórico anterior a esta declaración.

## Pendientes operativos y académicos

La aceptación de la entrega queda registrada. Permanecen pendientes de evidencia o ejecución: recorrido de navegador por caso y capturas, contraste con el catálogo definitivo de la tesis, primer administrador real, ensayo completo de las ocho migraciones sobre una copia histórica y restauración real del módulo en entorno aislado. No se declara completado el cierre académico externo ni una restauración no realizada.

Las **ocho migraciones del Sprint 5 siguen pendientes en la principal**, según la última comprobación documentada. Esta actualización es exclusivamente documental: no consulta ni modifica la base, no crea cuentas y no ejecuta migraciones, respaldos o restauraciones. El despliegue requiere prevalidación y autorización específica posteriores.

## Actualización posterior: despliegue del 20 de octubre

Los estados pendientes anteriores corresponden a la fecha de cada registro. Posteriormente se completaron el ensayo íntegro desde S4 y la restauración real del módulo Backup en la instancia aislada. El incidente de 12 registros ficticios en la principal se resolvió con retirada transaccional expresamente autorizada y verificación del estado original; después se aplicaron las ocho migraciones, lote 15. Estado final: 53 tablas, 43 migraciones, ninguna pendiente, 123 permisos y 260 vínculos; cero usuarios y trabajadores. Mantenimiento retirado. Véanse [despliegue e incidencia resuelta](sprint-5-deployment.md) y [evidencia actual](evidence/sprint-5-deployment.json). No se hicieron commit ni push. La aceptación visual por caso, el primer administrador y el contraste académico conservan sus pendientes.

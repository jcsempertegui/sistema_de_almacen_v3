# Cierre técnico y preparación académica del SGA

Fecha de preparación: 5 de noviembre de 2026, America/La_Paz. Los cinco sprints están implementados; esta fase consolida la versión existente y no crea módulos ni un sexto sprint. El estado técnico y el resultado de aceptación humana se registran por separado.

## Instalación e integridad comprobadas

Inspección real de la principal mediante transacciones SQL de solo lectura:

| Elemento | Resultado |
|---|---:|
| Tablas | 53 |
| Migraciones aplicadas | 44 |
| Migraciones pendientes | 0 |
| Permisos / asignaciones rol–permiso | 123 / 260 |
| Usuarios / trabajadores | 5 / 24 |
| Productos / unidades individualizadas | 116 / 192 |
| Movimientos | 2.206 |
| Entregas / préstamos / devoluciones | 778 / 33 / 25 |
| FK / CHECK / índices únicos revisados | 166 / 103 / 143 |
| Huérfanos / violaciones CHECK / grupos duplicados | 0 / 0 / 0 |
| Diferencias de saldo frente a movimientos / saldos faltantes | 0 / 0 |
| Existencias negativas | 0 |
| Acceso administrativo activo | Sí |

Entorno: PHP 8.3.10, Laravel 13.35.0 y MariaDB 10.4.32; servidor `DESKTOP-U7NQKIM`, puerto 3306 y datos en `C:\xampp\mysql\data\`. La identidad de la base principal es `sga_rio_grande`. Testing tiene el mismo esquema en `sga_rio_grande_testing`, con aislamiento por conexión y verificaciones de identidad; no es una segunda instancia física.

Las 43 migraciones históricas de los cinco sprints y la migración adicional de traducción ya estaban aplicadas. No se ejecuta DDL principal, no se reinstala el escenario y no se crean cuentas. El administrador existente no se recrea. La información anual cargada sigue siendo **simulada**, no evidencia de consumos reales ni de aceptación por trabajadores.

`scripts/verificar-cierre.php` reproduce las comprobaciones de integridad y conciliación con SELECT dentro de una transacción de solo lectura. No aplica reparaciones. `scripts/inspect-local-environment.php` conserva huellas privadas para comparar datos y estructuras antes/después del trabajo. Los originales se guardan fuera del repositorio, en `C:\Users\jcsem\SGA-evidence\cierre-proyecto-20261105`.

## Versión consolidada

Incluye la traducción del esquema/clases propias, escenario anual autorizado, panel operativo, reportes detallados, navegación simplificada y selectores por fragmentos publicados previamente. También incorpora los cambios locales de consultas de lectura y direcciones en español, con compatibilidad de enlaces antiguos y controles de autorización conservados.

Las mediciones previas de consultas fueron panel 83→14, productos 53→9 y reporte de existencias 52→8 en el escenario controlado. No se convierten esas cifras en una promesa de latencia ni en un porcentaje de productividad de CONFIPETROL.

## Pruebas de cierre

Se verificó **todo el catálogo actual: 522 casos distintos y 4.065 aserciones**, exclusivamente en testing, sin casos pendientes, omitidos, errores ni fallos sin resolver en la consolidación. Es una **regresión por lotes, no una única ejecución completamente aprobada**. El catálogo generado por PHPUnit se comparó por clase, método y conjunto de datos con los XML ejecutados; la cobertura y los resultados más recientes están en [la evidencia](evidence/cierre-proyecto-2026-11-05/regresion.json) y [el JUnit consolidado](evidence/cierre-proyecto-2026-11-05/regresion-consolidada.xml).

El primer lote se detuvo tras 75 casos por una expectativa de URL antigua. Las continuaciones detectaron las expectativas de paginación y ubicación pendientes de adaptar; se corrigieron sin debilitar validaciones ni cambiar timeouts. La última tanda aprobó 127 casos/1.003 aserciones y verificó nuevamente las clases afectadas por las correspondencias de filtros. Los originales con fallos se conservan fuera del repositorio; no se reescribieron como aprobados. El ensayo anual aprobado no se repitió innecesariamente. Las tandas anteriores de 85 y 51 pruebas siguen siendo antecedentes seleccionados y no se suman a estos 522 casos.

Las comprobaciones de navegador y las capturas técnicas se identifican como automatizadas. No sustituyen observación ni aprobación del responsable funcional.

La exportación aislada de formularios aprobó 3 pruebas/26 aserciones adicionales orientadas a producir los HTML de revisión; no se suman como casos nuevos al catálogo de 522. Chrome aprobó **53 comprobaciones en cinco escenarios**, con dos capturas limpias de 1440×1000 y 500×844. [Resumen de verificación](evidence/cierre-proyecto-2026-11-05/verificacion.json).

La comparación de las 53 huellas de datos y estructuras de la principal antes/después no encontró diferencias. Tampoco cambiaron `.env`, archivos de dependencias ni los 44 archivos de migración. Composer aprobó los requisitos de plataforma, sin instalar ni actualizar paquetes. No se generaron respaldos ni se ejecutaron restauraciones nuevas.

### Capturas técnicas

HTML generado con fixtures transitorias de testing y envíos deshabilitados para la captura. Son vistas de interfaz, no operaciones reales, firmas de entrega ni aceptación funcional humana.

[Escritorio, 1440×1000](evidence/cierre-proyecto-2026-11-05/entrega-escritorio.png) · [Móvil, 500×844](evidence/cierre-proyecto-2026-11-05/entrega-movil.png).

## Documentos entregables

| Documento | Finalidad |
|---|---|
| [Manual de usuario](manual-usuario.md) | Operación por sección, mensajes y ejemplos de ajustes/compensaciones |
| [Manual técnico actual](manual-tecnico.md) | Arranque en este PC, arquitectura, pruebas y recuperación controlada |
| [Matriz académica revisable](matriz-academica-cierre.md) | Seis objetivos textuales, 13 funcionales, diez no funcionales e historias históricas/actuales |
| [Diccionario español](diccionario-datos-espanol.md), [modelo lógico](modelo-datos-espanol.md) y [procesos](diagramas-procesos.md) | Esquema vigente, relaciones, confirmaciones, custodia y despliegue |
| [Guion de defensa](guion-defensa.md) | Demostración y selección de anexos sin inventar resultados |
| [Registro de aceptación](acta-aceptacion-final.md) | Casos humanos, campos de evidencia y decisión del responsable |
| [Guion funcional detallado](final-functional-acceptance.md) | Aislamiento y criterios para el recorrido humano |
| Planes, matrices e informes S1–S5 | Historia de implementación y evidencias fechadas |

Se corrigieron referencias vigentes que todavía describían el otro PC o un administrador inexistente. Los informes de despliegue históricos se conservan con su fecha; sus conteos no son instrucciones para repetir migraciones. La matriz anterior queda señalada como antecedente de la propuesta actual.

## Decisión de alcance y limitaciones

El autor confirmó mantener el catálogo técnico actual y documentar la diferencia con las variantes históricas. Registro, asociación, validación, consulta e historial de atributos existen; los valores técnicos detallados no se muestran ni se conservan como instantánea completa por línea en remitos/entregas. La cobertura histórica correspondiente sigue siendo parcial. Esta decisión no añade desarrollo ni transforma la limitación en cumplimiento pleno.

Otras reservas explícitas: criterios literales de la tesis por cotejar; valoración económica no declarada como requisito histórico; sin alta disponibilidad ni benchmark de carga; datos demostrativos no certificados; restauración real actual del esquema español por autorizar y ensayar mediante su procedimiento; aceptación humana aún sin acta completa. La separación de credenciales SQL por esquema y el uso productivo requieren decisiones operativas propias.

## Condiciones de cierre formal

El cierre técnico requiere resultados reales de pruebas, conservación de la principal y versión de código identificada. La aceptación funcional requiere completar y aprobar el registro por una persona responsable. El cierre académico requiere revisar el DOCX original, integrar la matriz/limitaciones y anexar instrumentos, capturas y conclusiones basados en evidencia.

Publicar una versión técnica no concede esas aprobaciones ni autoriza nuevas migraciones, restauraciones o reemplazo de datos. Ningún apartado de este documento se presenta como acta firmada por CONFIPETROL o por un tribunal.

# Sprint 4 — Aceptación declarada y publicación

## Autorización

El 19 de octubre de 2026 el usuario indicó: «autorizado y aceptado. actualiza el repositorio». Se registra su aceptación del trabajo entregado y su autorización para crear commits, integrar la rama `feature/sprint-4-worker-operations` en `main` y publicar en `jcsempertegui/sistema_de_almacen_v3`.

Esta conformidad no aporta resultados individuales de navegador, capturas ni una firma de cada caso del [guion funcional](sprint-4-operator-guide.md). Esas evidencias continúan pendientes. No autoriza migraciones sobre `sga_rio_grande`, respaldo nuevo, creación de cuentas ni Sprint 5.

## Evidencia que se publica

- HU-18 a HU-23, once tablas nuevas y seis migraciones aditivas aplicadas exclusivamente en testing.
- Regresión final ejecutada: **401 pruebas, 3.143 aserciones, cero errores, fallos u omisiones**. No se repite la suite para estos cambios documentales.
- [JUnit final](evidence/sprint-4/regression-final.xml), SHA-256 `a8888e60a383b8aaaf2ee757cd6e4f577281bde99a3a4391906f1f06a2d06675`.
- [Captura histórica de implementación](evidence/sprint-4-implementation.json): sus indicadores de ausencia de commit/merge/push describen el momento anterior a esta autorización.
- [Listado de implementación](sprint-4-changed-files.md): 89 archivos al entregar. Esta publicación añade este documento y actualiza los estados documentales; conserva el código probado.

## Procedimiento de publicación

Comparar el remoto `main` con la base `cd7b4eabc0319c0322be929db0de1af1a16675d5`, comprobar los hashes de las 323 fuentes congeladas y revisar el contenido preparado. Crear el commit de implementación, integrar mediante avance rápido y publicar `main` sin forzar. Verificar después la igualdad entre HEAD local y `refs/heads/main` remoto y el árbol de trabajo limpio. El resultado efectivo se comunica tras ejecutar esos pasos.

## Despliegue reservado

La última verificación de solo lectura conserva **31 tablas, 29 migraciones aplicadas, 62 permisos y 126 relaciones rol–permiso** en la principal. Las seis migraciones del Sprint 4 siguen pendientes. Su futura aplicación requiere prevalidación, respaldo actual verificado, ensayo de recuperación y autorización específica. No ejecutar un `migrate` general ni repetir migraciones del Sprint 3.

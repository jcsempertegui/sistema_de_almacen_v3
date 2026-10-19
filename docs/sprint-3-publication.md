# Sprint 3 — Publicación autorizada

Fecha de autorización: 19 de octubre de 2026, America/La_Paz. El usuario solicitó «actualiza nuestro repositorio» después de recibir los resultados de implementación y la reserva de pruebas.

## Alcance

Registrar los cambios preparados en `feature/sprint-3-inventory`, integrar mediante avance rápido en `main` si conserva su base y publicar en `jcsempertegui/sistema_de_almacen_v3`. Base revisada: `1f6d30e13dd20caa8ae1e5c1ba8c9bc5d411301c`. No se sobrescribe historia ni se utiliza push forzado. El commit publicado se identifica mediante Git y se informa al finalizar la operación.

La autorización corresponde al repositorio. No autoriza aplicar las dos migraciones principales, crear datos/cuentas reales ni iniciar el Sprint 4. La revisión funcional del Sprint 3 continúa pendiente.

## Evidencia conservada

- Los 44 casos nuevos de inventario pasaron. La regresión de 363 casos registró un timeout en la carrera anterior de edición de productos; el escenario aislado y el grupo completo de siete carreras pasaron al repetirlos. `TEST-S3-01` permanece como reserva de estabilidad, con causa no determinada.
- No se declara una regresión completa única sin errores ni aceptación humana de pantallas nuevas.
- `docs/evidence/sprint-3-implementation.json` y sus artefactos conservan el estado original anterior a esta autorización: sus campos `commit_created` y `push_executed` son históricos y no se reescriben como si las pruebas se hubieran ejecutado después de publicar.
- Los hashes de código, vistas, rutas, migraciones y pruebas se contrastan con las fuentes verificadas. Los ajustes posteriores son documentales: autorización de publicación, README y listado de archivos. El hash original del README se conserva como evidencia del estado previo; no representa su revisión documental posterior.
- `.env`, dependencias y migraciones anteriores permanecen fuera del commit. Las dos migraciones nuevas se publican como código pendiente de aplicación a la principal.

La comprobación final requiere confirmar que `main` local y `origin/main` apuntan al mismo commit y que el árbol de trabajo queda limpio. No se ejecutan nuevamente las pruebas de base de datos para estos ajustes documentales; se conservan sus resultados reales.

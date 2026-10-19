# Sprint 2 — Guion de aceptación humana

**Seguimiento del 19 de octubre de 2026:** el usuario declaró su conformidad para avanzar. Véase el estado vigente en `sprint-2-acceptance.md`. No se completan retrospectivamente los resultados de este guion: las revisiones personales sin evidencia siguen pendientes.

Estado: preparado para ejecución personal; **sin resultados humanos ni aprobación formal registrados**.

Usar únicamente `http://127.0.0.1:8012` y la base `sga_rio_grande_acceptance_s2`. La copia de ejecución existente está en `C:\Users\jcsem\SGA-acceptance\sprint-2-20261019\runtime`. El servidor es local: la simulación móvil se realiza en el navegador de ese equipo; probar un teléfono físico requerirá acordar previamente el acceso al entorno aislado.

No ejecutar migraciones, scripts de preparación o recorridos anteriores de alta. No crear registros ni usuarios adicionales. Utilizar las cuentas demostrativas existentes de Administrador, Encargado y Consulta/Auditor, en sesiones separadas. Las credenciales están exclusivamente en el archivo privado local `accounts.private.json` del directorio de aceptación; no copiarlas a capturas ni al repositorio.

Este guion revisa los registros existentes y permite previsualizar productos sin guardar. No acredita como ejecutados manualmente los guardados, altas o carreras concurrentes cubiertos por pruebas automatizadas. Ningún paso requiere acceder a la principal.

## Recorrido

| Paso / referencia | Acción personal | Criterio observable |
|---|---|---|
| 1 / AF-01/02/33/34 | Iniciar y cerrar sesión con cada perfil. Con Auditor, intentar `/categorias/create`; con Encargado, `/roles`. Tras cerrar sesión, intentar `/panel`. | Accesos permitidos coherentes; altas del Auditor y administración de roles del Encargado rechazadas; sesión cerrada sin acceso protegido. |
| 2 / AF-09/10 | Buscar `AC-HER` y `AC-MAT`; abrir edición sin guardar. | Códigos, nombres, estados, campos obligatorios y búsqueda comprensibles. Las restricciones de desactivación tienen evidencia automatizada; no intentar desactivar registros durante esta revisión de consulta. |
| 3 / AF-11/12 | Consultar `AC-UN`, `AC-M`, `AC-L` y `AC-LMIN`; abrir formularios sin guardar. | Símbolos `L` y `l` diferenciados; dimensión y precisión visibles. La validación de límites se consulta en la evidencia automatizada, sin alterar unidades utilizadas. |
| 4 / AF-13 a AF-16 | Consultar `AC-MATERIAL`, `AC-LONG`, `AC-CERT` y `AC-FECHA`; abrir atributos de `AC-HER`. | Tipos, unidad decimal, ayuda, obligatoriedad, identificación técnica, orden y límites comprensibles. Tipo/unidad protegidos cuando corresponda. |
| 5 / AF-17 a AF-20 | Consultar `AC-P001` y `AC-P002`. | Diferencia entre control individual y por cantidad; condición prestable, categoría, unidad y valores técnicos claros. El catálogo no muestra saldos como si fueran existencias. |
| 6 / AF-22 | Consultar los identificadores actuales de `AC-P001`. Abrir edición de `AC-P003` e igualar solamente en el formulario sus características de identificación con las de `AC-P001`; pulsar **Revisar**. Salir sin confirmar el guardado. | Candidato visible; advertencia de que la coincidencia no certifica equivalencia técnica; ninguna fusión automática. Si los registros hubieran cambiado y no hay coincidencia, registrar lo observado y solicitar revisión técnica, sin recrearlos. |
| 7 / AF-23 | En `AC-P001`, desplegar **Ver revisiones**. | Diferencia entre valor actual e histórico; fechas y autoría legibles. |
| 8 / AF-25 a AF-28 | Abrir árbol de ubicaciones y asociaciones de `AC-P001`. | Sector → Estante → Gabinete comprensible; varias ubicaciones como destinos de catálogo, sin cantidades o movimientos. |
| 9 / AF-29 a AF-32 | Buscar `AC-I001`, filtrar por su serie y ubicación; consultar unidades sin serie y abrir edición sin guardar. | Identificador, producto, serie y ubicación claros; campos de identidad protegidos; filtros utilizables. |
| 10 / AF-36 | Repetir panel, producto, rol y listado de unidades a tamaño de escritorio y modo móvil. Usar Tab, Shift+Tab y zoom real del navegador al 200 %. | Texto y errores comprensibles; foco visible; acciones accesibles; tablas desplazables internamente sin ensanchar toda la página. Anotar navegador y dimensiones reales. |
| 11 / AF-35/36, UI-S2-02 | Revisar con apoyo técnico el rechazo 419 corregido y el enlace **Volver al inicio**, exclusivamente en aceptación. | Mensaje español y diseño del sistema; respuesta 419 conservada; recuperación por navegación sin reenvío automático. La prueba asistida previa no sustituye esta revisión personal. |

## Registro de resultados

Completar una fila por paso y, cuando corresponda, por perfil. No prellenar resultados satisfactorios.

| Paso | Responsable | Fecha | Perfil | Navegador / pantalla | Resultado observado | Conforme / no conforme / no ejecutado | Captura / incidencia |
|---|---|---|---|---|---|---|---|
| Pendiente de ejecución | | | | | | No ejecutado | |

Las capturas deben identificar el paso y el perfil, sin contraseñas. Registrar los problemas concretos con pasos para reproducirlos y su impacto, sin sustituir el resultado observado por el esperado.

## Criterios para el cierre formal

1. Revisar los resultados automatizados históricos y la verificación específica de UI-S2-02; mantener separadas ejecución automatizada, recorrido asistido y revisión humana.
2. Completar el recorrido personal acordado. Registrar de forma expresa los casos no ejecutados y las reservas que el responsable acepte; los casos no ejecutados no pasan automáticamente.
3. Resolver incidencias bloqueantes. Confirmar personalmente la presentación española de UI-S2-02; su corrección técnica por sí sola no firma la aceptación.
4. Delimitar IND-06 con documentos operativos como dependencia futura, sin declarar implementados remitos, entregas, saldos ni movimientos.
5. Registrar confirmación expresa del responsable funcional, con nombre, fecha, versión revisada y reservas. Solo entonces actualizar `sprint-2-acceptance.md` a su resultado formal.

La creación del primer administrador real de la principal sigue pendiente de autorización específica. Posteriormente el usuario declaró conformidad para avanzar y autorizó implementar el Sprint 3; esa declaración se registra separadamente y no completa los casos personales de este guion. Publicar el guion o corregir el 419 por sí solos no constituyen aceptación funcional.

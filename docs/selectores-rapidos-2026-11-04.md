# Búsqueda rápida y selección asistida — 4 de noviembre de 2026

## Uso diario

Los campos que seleccionan trabajadores, productos, categorías, unidades de medida, ubicaciones, atributos, roles, préstamos e identidades permiten escribir un fragmento del nombre o código y elegir una coincidencia. Los filtros de consulta utilizan el mismo comportamiento. Las listas pequeñas de estado o de sí/no conservan su selección sencilla.

La búsqueda ignora mayúsculas y tildes y admite varias palabras en diferente orden: `mend jos` encuentra «José Carlos Mendoza Rojas». No afirma equivalencias ni corrige identidades automáticamente: hay que seleccionar una opción con clic o con flechas y Enter. Escape cancela la búsqueda y conserva lo elegido; × limpia la selección. No se envía un nombre escrito libremente como si fuera un identificador válido.

Para seleccionar varios identificadores o ubicaciones, se busca y se añade cada opción; aparecen etiquetas que se pueden retirar individualmente. Ya no hace falta mantener Ctrl presionado cuando JavaScript está activo. Sin JavaScript, los selectores originales siguen funcionando y conservan sus campos, valores y validaciones.

Las sugerencias se obtienen inmediatamente de las opciones que el servidor ya autorizó y cargó en la página. No se incorporan endpoints públicos ni consultas que eludan permisos. Se excluyen opciones ocultas o deshabilitadas, incluidos destinos de otro producto o ítems de otro préstamo. Si hay más de 60 coincidencias se invita a escribir más; la búsqueda considera la lista completa disponible.

## Implementación

- `public/js/sga-selectores.js`: componente compartido, teclado, ARIA, selección simple/múltiple, sincronización y soporte para filas nuevas.
- `public/css/sga.css` y `resources/views/layouts/app.blade.php`: presentación y carga del recurso con versión de caché.
- `public/js/sga-ui.js` y formulario de operaciones: sustitución del buscador anterior de trabajador para evitar controles duplicados.
- Formularios de operaciones, remitos, ajustes y unidades individualizadas: corrección de referencias JavaScript traducidas indebidamente (`documento`, `hijos`, `nombre`) que impedían añadir filas o filtrar destinos. Se conservan `document`, `children` y `name` como contratos del navegador.
- Clonado de ajustes: retirada de los controles generados antes de inicializar la nueva fila, manteniendo nombres e identificadores únicos.
- Ayuda de identificadores del remito: instrucciones para búsqueda y etiquetas, con alternativa nativa sin JavaScript.

No hay cambios de esquema, migraciones, dependencias, `.env`, permisos ni datos operativos. Las validaciones, CSRF y autorizaciones del servidor siguen vigentes. Las modificaciones anteriores del árbol de trabajo se conservan; esta etapa no incluye publicación Git.

## Verificación ejecutada

PHPUnit se ejecutó exclusivamente en `sga_rio_grande_testing`, con los controles existentes de aislamiento. El primer lote aprobó 10 casos y 73 aserciones; un segundo lote aprobó los tres casos de `SearchableSelectorsTest` y 26 aserciones, incorporando la comprobación producto–ubicación. Como dos casos se repiten, la consolidación corresponde a **11 casos distintos y 80 aserciones**, no a 13 casos distintos.

Chrome ejecutó **53 comprobaciones, sin fallos, en cinco escenarios** sobre HTML real exportado desde testing: entrega en escritorio y ventana estrecha, remito, ajuste y unidad individualizada. Se comprobaron búsqueda por fragmentos y tildes, teclado, cancelación, campos obligatorios, selección múltiple, etiquetas, opciones deshabilitadas, nuevas filas, nombres enviados al servidor y filtros producto–ubicación. No se enviaron formularios a la principal.

Las ventanas efectivas fueron de aproximadamente 1.424 y 500 píxeles interiores; Chrome impuso su ancho mínimo a la solicitud inicial de 390. No se declara validación en un teléfono físico, lector de pantalla ni aceptación humana.

También pasaron la revisión de sintaxis de JavaScript y el formato PHP. Los resultados y las huellas de archivos están en [la evidencia](evidence/selectores-rapidos-2026-11-04.json); las capturas HTML, resultados JUnit y salidas del navegador se conservan fuera del repositorio.

## Repetir las comprobaciones

Crear una carpeta externa de evidencias y definir temporalmente `SGA_SELECTORES_EVIDENCE` con su ruta. Ejecutar `php vendor/bin/phpunit --filter SearchableSelectorsTest`; retirar después esa variable temporal. No editar `.env`.

Con las páginas exportadas, ejecutar:

```powershell
php tests/Browser/verificar-selectores.php C:\ruta\externa\evidencias
```

El segundo argumento opcional indica el ejecutable Chrome si no está en su ruta habitual. El verificador no conecta a bases de datos: abre copias HTML locales y bloquea el envío de sus formularios. La aplicación no requiere Chrome ni herramientas adicionales para funcionar; este ejecutable se utiliza solamente en la comprobación de navegador.

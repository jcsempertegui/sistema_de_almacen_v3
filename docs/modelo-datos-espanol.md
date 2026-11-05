# Modelo de datos en español

Vista simplificada de las entidades de negocio de la rama de traducción. El detalle de las 53 tablas y todas sus columnas figura en el [diccionario](diccionario-datos-espanol.md). No reemplaza los diagramas históricos de los sprints ni acredita aceptación académica externa.

## Acceso y catálogo

```mermaid
erDiagram
    roles ||--o{ usuarios : asignacion
    roles ||--o{ permiso_rol : capacidades
    permisos ||--o{ permiso_rol : autorizacion
    categorias ||--o{ productos : clasificacion
    unidades_medida ||--o{ productos : medicion
    categorias ||--o{ atributos_categoria : configuracion
    atributos ||--o{ atributos_categoria : definicion
    productos ||--o{ valores_atributo_producto : caracteristicas
    atributos_categoria ||--o{ valores_atributo_producto : pertenencia
    valores_atributo_producto ||--o{ revisiones_valor_atributo : historial
    productos ||--o{ ubicaciones_producto : asociaciones
    ubicaciones ||--o{ ubicaciones_producto : destino
    ubicaciones o|--o{ ubicaciones : jerarquia
    productos ||--o{ unidades_individuales : identificacion
```

`trabajadores` representa al personal del contrato. Un trabajador no necesita una fila en `usuarios`: tener herramientas o materiales asignados es independiente de disponer de acceso al sistema.

## Documentos e inventario

```mermaid
erDiagram
    documentos_inventario ||--o{ lineas_documento_inventario : detalle
    trabajadores ||--o{ entregas_materiales : responsable
    entregas_materiales ||--o{ lineas_entrega_material : detalle
    trabajadores ||--o{ prestamos_herramientas : responsable
    prestamos_herramientas ||--o{ items_prestamo_herramienta : herramientas
    prestamos_herramientas ||--o{ devoluciones_herramientas : recepciones
    devoluciones_herramientas ||--o{ items_devolucion_herramienta : herramientas
    items_prestamo_herramienta ||--o{ items_devolucion_herramienta : origen
    lineas_documento_inventario o|--o| movimientos_inventario : confirmacion
    lineas_entrega_material o|--o| movimientos_inventario : salida
    items_prestamo_herramienta o|--o| movimientos_inventario : salida
    items_devolucion_herramienta o|--o| movimientos_inventario : ingreso
    lineas_correccion_inventario o|--o| movimientos_inventario : correccion
    productos ||--o{ existencias_ubicacion : saldo
    ubicaciones ||--o{ existencias_ubicacion : saldo
    unidades_individuales ||--o| posiciones_unidad_individual : presencia
    unidades_individuales ||--o| custodias_unidad_individual : custodia
```

Las cinco relaciones de origen de `movimientos_inventario` son alternativas: el CHECK exige exactamente una fuente por movimiento y los índices únicos impiden repetir el efecto físico de una línea o ítem. El diagrama no representa cinco movimientos para una misma operación. Las asociaciones `ubicaciones_producto` tampoco representan cantidades.

## Control y respaldo

```mermaid
erDiagram
    correcciones_inventario ||--o{ lineas_correccion_inventario : detalle
    correcciones_inventario ||--o{ autorizaciones_correccion : decisiones
    autorizaciones_correccion ||--o{ eventos_autorizacion_correccion : historial
    correcciones_inventario ||--o| compensaciones_documento : aplicacion
    items_devolucion_herramienta ||--o{ autorizaciones_regularizacion_devolucion : excepciones
    autorizaciones_regularizacion_devolucion ||--o{ eventos_regularizacion_devolucion : historial
    ejecuciones_respaldo ||--o{ ensayos_restauracion_respaldo : verificacion
```

Los ajustes y las compensaciones conservan sus documentos y movimientos. La traducción cambia identificadores del esquema, no las reglas de autorización, las custodias vigentes ni los requisitos de separación de funciones en Backup.

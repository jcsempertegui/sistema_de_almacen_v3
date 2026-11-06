# Diagramas de procesos y despliegue

Versión documental del 5 de noviembre de 2026, basada en las acciones y servicios implementados. Son diagramas lógicos simplificados, no evidencia de ejecución ni de autorización humana. El [modelo de datos](modelo-datos-espanol.md) complementa estas vistas.

## Confirmación de un documento

```mermaid
flowchart TD
    A[Usuario autorizado prepara borrador] --> B[Validar líneas y referencias en servidor]
    B --> C[Guardar sin modificar existencias]
    C --> D[Operación física efectiva]
    D --> E[Solicitar confirmación con versión del documento]
    E --> T[Iniciar transacción y bloquear referencias]
    T --> P{Sesión y permisos vigentes}
    P -->|No| R[Rechazar y revertir]
    P -->|Sí| S{Documento ya confirmado}
    S -->|Sí| I[Responder sin repetir el movimiento]
    S -->|No| V{Saldo y referencias válidos}
    V -->|No| R
    V -->|Sí| M[Registrar movimiento único por origen]
    M --> U[Actualizar saldo, presencia y custodia según operación]
    U --> H[Conservar responsables y confirmar documento]
    H --> K[Commit]
```

Las restricciones SQL complementan la validación del servicio. Las cinco fuentes documentales son alternativas del mismo historial; no crean varios movimientos físicos para la misma operación.

## Préstamo y devolución

```mermaid
flowchart TD
    A[Seleccionar trabajador y herramientas identificadas] --> B[Elegir ubicaciones de retiro]
    B --> C{Disponibles y sin custodia vigente}
    C -->|No| X[Rechazar préstamo]
    C -->|Sí| D[Confirmar salida y custodia en transacción]
    D --> E[Préstamo pendiente]
    E --> F[Preparar devolución del préstamo original]
    F --> G[Seleccionar ítems recibidos y destino manual]
    G --> H{Referencias activas y válidas}
    H -->|No| J{Regularización excepcional específica vigente}
    J -->|No| Y[Solicitar revisión administrativa]
    J -->|Sí| K[Validar destino y autorización sin reactivar catálogo]
    H -->|Sí| L[Confirmar recepción física transaccional]
    K --> L
    L --> M[Ingreso único y cierre de cada ítem recibido]
    M --> N{Quedan ítems pendientes}
    N -->|Sí| E
    N -->|No| O[Préstamo completamente devuelto]
```

Desactivar al trabajador no borra la obligación. La ubicación inicial histórica de la unidad no se reemplaza por una supuesta ubicación actual. La compensación de una devolución puede reabrir un ciclo si sus dependencias lo permiten; ese flujo está protegido por el servicio de correcciones.

## Corrección crítica

```mermaid
flowchart TD
    A[Preparar ajuste o compensación con motivo] --> B[Guardar borrador]
    B --> C[Solicitar autorización]
    C --> D[Otra persona con permiso revisa]
    D --> E{Autoriza}
    E -->|No| F[Rechazar o devolver para revisión]
    E -->|Sí| G[Autorización vigente ligada a la operación]
    G --> H[Operador autorizado solicita ejecución]
    H --> I[Transacción y bloqueos]
    I --> J{Permisos, vigencia, versión y dependencias válidos}
    J -->|No| R[Rechazar sin efectos parciales]
    J -->|Sí| K[Calcular efecto y comprobar saldo no negativo]
    K --> L[Registrar movimiento y trazabilidad del original]
    L --> M[Consumir autorización y registrar auditoría]
    M --> N[Commit]
```

No se borra ni reescribe el movimiento confirmado. La solicitud y la autorización, por sí solas, no cambian existencias. Una compensación es total y sin cascadas automáticas según el alcance aprobado.

## Despliegue local y fronteras de datos

```mermaid
flowchart LR
    N[Navegador del operador] -->|HTTP local 8000| W[Laravel / PHP 8.3]
    W -->|Sesión y permisos| C[Acciones y servicios]
    C -->|SQL 3306| P[(sga_rio_grande)]
    T[PHPUnit con guardián de aislamiento] -->|Única conexión sga_testing| Q[(sga_rio_grande_testing)]
    P -. Inspección de identidad de solo lectura .-> T
    C -->|Solo flujo autorizado| B[SQL y manifiestos fuera del repositorio]
    B -->|Solicitud y autorización separadas| E[(Destino nuevo de ensayo)]
```

Principal y testing están en la misma instancia MariaDB local, separados por esquema y controles de conexión. El diagnóstico de identidad de la principal no autoriza escrituras desde PHPUnit. Un entorno persistente de aceptación debe prepararse por separado y no compartir fixtures con la suite. La restauración principal no es una flecha automática de este diseño.


# SGA CONFIPETROL — Instrucciones de desarrollo

## Proyecto
Sistema Web de Gestión de Almacén para CONFIPETROL
S.A.S., Planta Separadora de Líquidos Río Grande.

## Tecnologías
- Laravel 13
- PHP 8.3
- MariaDB 10.4 (entorno local)
- Git y GitHub

## Reglas generales
- Mantener la arquitectura de Laravel.
- Utilizar migraciones para modificar la base de datos.
- No eliminar tablas ni datos sin autorización.
- No modificar el archivo .env.
- No ejecutar migrate:fresh.
- Implementar validaciones y permisos del lado del servidor.
- Crear pruebas para las funcionalidades desarrolladas.

## Reglas de negocio
- El sistema administra un solo almacén.
- Los productos se registran desde el sistema.
- Excel no puede crear ni modificar productos.
- Los remitos y las entregas son independientes.
- Cada documento confirmado genera movimientos únicos.
- No permitir existencias negativas.
- El encargado selecciona las ubicaciones de retiro.
- Las herramientas prestables requieren identificación individual.
- Los trabajadores no necesitan cuentas de usuario.
- Los ajustes y anulaciones críticas requieren autorización.

## Metodología
Trabajar por sprints e historias de usuario.
Antes de realizar cambios importantes, presentar un plan.
No crear funcionalidades fuera del alcance aprobado.

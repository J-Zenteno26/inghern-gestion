# Arquitectura funcional de INGHERN

INGHERN se organiza como un monolito modular en Laravel. La interfaz agrupa las funciones por contexto y conserva una navegación superior: **Operación**, **Comercial** y **Relaciones**. El servicio es la unidad operativa central; una cotización es un documento comercial que puede incluir uno o más servicios.

## Límites de módulo

| Módulo | Responsabilidad | Código principal |
|---|---|---|
| Relaciones | Clientes, contactos y plantas | `Cliente`, `Contacto`, `Planta` |
| Operación | Servicios, tipo, plantas involucradas y estado | `Servicio`, `TipoServicio` |
| Comercial | Cotizaciones, revisiones, bloques, plazos y partidas | `Cotizacion`, `CotizacionRevision`, `RevisionServicio` |
| Compartido | Códigos consecutivos, usuarios e historial | `GeneraCodigo`, `EventoHistorial`, `User` |

Los controladores y acciones viven dentro de carpetas por módulo. Las reglas con transacciones, como crear una cotización completa, se implementan en `app/Domain` para no mezclarlas con la capa HTTP.

## Decisiones del modelo

- Una cotización mantiene una cabecera estable y múltiples revisiones. `revision_actual_id` permite abrir la versión vigente sin borrar las anteriores.
- Cada revisión guarda una copia de los datos del cliente y contacto. Un cambio posterior en su ficha no altera documentos históricos.
- `revision_servicios` separa los servicios incluidos en una propuesta de los servicios operativos. Pueden vincularse cuando exista el trabajo real.
- Cada partida registra clase, método de precio, valor sugerido, valor final y justificación. Esto permite introducir normalización sin ocultar el criterio profesional.
- Traslados y costos adicionales son partidas explícitas y opcionales.
- `eventos_historial` es polimórfica para incorporar trazabilidad transversal en las siguientes iteraciones.

## Sistema visual

La identidad parte de la landing existente. Los tokens viven una sola vez en `resources/css/app.css`; las vistas consumen componentes en `resources/views/components/ui`. Nuevas pantallas deben usar estos componentes antes de crear estilos particulares.

| Token | Valor | Uso |
|---|---:|---|
| Tinta | `#0B1F33` | Encabezados y navegación |
| Petróleo | `#0E3A5B` | Acciones secundarias y superficies oscuras |
| Acero | `#2F6F91` | Información, foco y enlaces |
| Naranjo | `#F58220` | Acción principal |
| Fondo | `#F3F6F8` | Lienzo de la aplicación |
| Borde | `#D9E2E8` | Separación y estructura |

## Siguiente crecimiento previsto

1. Motor de normalización de precios con variables versionadas por tipo de servicio.
2. Nueva revisión de cotización a partir de la vigente, manteniendo las anteriores cerradas.
3. Generación de PDF con plantilla documental de INGHERN.
4. Planificación, entregables y estados técnicos dentro de cada servicio.
5. Historial automático mediante observadores de dominio.


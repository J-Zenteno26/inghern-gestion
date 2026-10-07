# INGHERN Gestión

Plataforma interna para transformar información técnica dispersa en servicios trazables, relaciones ordenadas y propuestas comerciales versionadas.

## Estado actual

- Acceso privado con Laravel Fortify, recuperación de contraseña y segundo factor.
- Navegación superior por contextos, sin menú lateral permanente.
- Dashboard operativo.
- Directorio de clientes con contactos, plantas, servicios y cotizaciones relacionadas.
- Servicios como unidad central de operación.
- Cotizaciones con revisiones, partidas, traslados, costos adicionales y registro del criterio de precio.
- Base para normalización de valores mediante tamaño de planta, complejidad, calidad de antecedentes y condición operacional.
- Sistema visual reusable basado en componentes Blade y tokens CSS de la identidad INGHERN.

## Tecnologías

- Laravel 13 / PHP 8.4
- PostgreSQL 18
- Fortify y Livewire 4
- Vite 8 y Tailwind CSS 4

## Instalación

Consulta [docs/INICIO.md](docs/INICIO.md) para preparar la base de datos, crear el administrador y levantar el proyecto. Las decisiones del modelo están documentadas en [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md).

## Convenciones

- El código y las tablas usan español de forma consistente.
- Las reglas de negocio con transacciones viven en `app/Domain`.
- Los controladores se agrupan por módulo.
- Los estilos visuales se definen en `resources/css/app.css`.
- Los formularios y acciones deben reutilizar `resources/views/components/ui`.
- Una cotización emitida se modifica creando una nueva revisión; nunca sobrescribiendo la anterior.

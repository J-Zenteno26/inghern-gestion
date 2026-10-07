<?php

namespace App\Enums;

enum UnidadPrecio: string
{
    case Servicio = 'servicio';
    case HoraProfesional = 'hora_profesional';
    case Jornada = 'jornada';
    case Visita = 'visita';
    case Entregable = 'entregable';
    case Documento = 'documento';
    case Plano = 'plano';
    case Activo = 'activo';
    case Equipo = 'equipo';
    case Punto = 'punto';
    case MetroLineal = 'metro_lineal';
    case MetroCuadrado = 'metro_cuadrado';
    case Unidad = 'unidad';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Servicio => 'Servicio',
            self::HoraProfesional => 'Hora profesional',
            self::Jornada => 'Jornada',
            self::Visita => 'Visita',
            self::Entregable => 'Entregable',
            self::Documento => 'Documento',
            self::Plano => 'Plano',
            self::Activo => 'Activo',
            self::Equipo => 'Equipo',
            self::Punto => 'Punto',
            self::MetroLineal => 'Metro lineal',
            self::MetroCuadrado => 'Metro cuadrado',
            self::Unidad => 'Unidad',
        };
    }
}

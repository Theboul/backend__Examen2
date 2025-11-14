<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Carbon\Carbon;

class AsistenciaExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    ShouldAutoSize, 
    WithTitle, 
    WithEvents
{
    protected $asistencias;

    public function __construct($asistencias)
    {
        $this->asistencias = $asistencias;
    }

    public function collection()
    {
        return $this->asistencias;
    }

    public function headings(): array
    {
        return [
            'ID Asistencia',
            'Fecha Registro',
            'Hora Registro',
            'Cod. Docente',
            'Nombre Docente',
            'Materia',
            'Grupo',
            'Día Clase',
            'Bloque Clase',
            'Estado',
            'Tipo Registro',
            'Observación',
        ];
    }

    public function map($a): array
    {
        return [
            $a->id_asistencia,
            $a->fecha_registro ? Carbon::parse($a->fecha_registro)->format('d/m/Y') : 'N/A',
            $a->hora_registro ? Carbon::parse($a->hora_registro)->format('H:i:s') : 'N/A',

            // Docente
            $a->asignacionDocente->docente->cod_docente ?? 'N/A',
            $a->asignacionDocente->docente->perfil->nombre_completo ?? 'N/A',

            // Materia y grupo
            $a->asignacionDocente->materiaGrupo->materia->nombre ?? 'N/A',
            $a->asignacionDocente->materiaGrupo->grupo->nombre ?? 'N/A',

            // Horario
            $a->horarioClase->dia->nombre ?? 'N/A',
            $a->horarioClase->bloqueHorario->nombre ?? 'N/A',

            // Estado
            $a->estado->nombre ?? 'N/A',

            $a->tipo_registro ?? '-',
            $a->observacion ?? '-',
        ];
    }

    public function title(): string
    {
        return 'Reporte de Asistencia';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getDelegate()
                    ->getStyle('A1:L1')
                    ->getFont()
                    ->setBold(true);
            },
        ];
    }
}

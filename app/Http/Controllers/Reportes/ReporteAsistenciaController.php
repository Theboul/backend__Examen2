<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Horarios\Asistencia;
use Illuminate\Support\Facades\Validator;

use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AsistenciaExport;

class ReporteAsistenciaController extends Controller
{
    /**
     * CU11: Generar Reporte (Dinámico, PDF, Excel)
     */
    public function generarReporte(Request $request)
    {
        // Validación
        $validator = Validator::make($request->all(), [
            'id_gestion'   => 'required|integer|exists:gestion,id_gestion',
            'id_docente'   => 'nullable|integer|exists:docente,cod_docente',
            'id_materia'   => 'nullable|integer|exists:materia,id_materia',
            'id_grupo'     => 'nullable|integer|exists:grupo,id_grupo',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
            'exportar'     => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filtros inválidos',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {

            $query = $this->construirConsulta($request);

            $asistencias = $query->with([
                'estado',
                'asignacionDocente.docente.perfil',
                'asignacionDocente.materiaGrupo.materia',
                'asignacionDocente.materiaGrupo.grupo',
                'horarioClase.dia',
                'horarioClase.bloqueHorario'
            ])
            ->orderBy('fecha_registro', 'desc')
            ->orderBy('hora_registro', 'desc')
            ->get();

            if ($asistencias->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sin registros de asistencia para los filtros seleccionados'
                ], 404);
            }

            $estadisticas = $this->calcularEstadisticas($asistencias);

            // EXPORTACIÓN
            if ($request->filled('exportar')) {

                $payload = [
                    'filtros'     => $request->all(),
                    'estadisticas'=> $estadisticas,
                    'asistencias' => $asistencias
                ];

                // PDF
                if ($request->exportar === 'pdf') {
                    // Usamos la vista blade que ya existe
                    $pdf = Pdf::loadView('reportes.asistencia_pdf', $payload)
                             ->setPaper('a4', 'landscape');

                    return $pdf->download('reporte_asistencia.pdf');
                }

                // EXCEL
                if ($request->exportar === 'excel') {
                    // Usamos la clase Export que ya existe
                    return Excel::download(
                        new AsistenciaExport($payload),
                        'reporte_asistencia.xlsx'
                    );
                }
            }

            // RESPUESTA JSON (DINÁMICA)
            return response()->json([
                'success'        => true,
                'message'        => 'Reporte generado correctamente',
                'filtros_aplicados'=> $request->all(),
                'estadisticas'     => $estadisticas,
                'data_detallada'   => $asistencias
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error inesperado',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    /**
     * -----------------  CONSULTA DINÁMICA  -----------------
     */
    private function construirConsulta(Request $request)
    {
        $query = Asistencia::query();

        // Gestión obligatoria
        $query->whereHas('asignacionDocente.materiaGrupo', function ($q) use ($request) {
            $q->where('id_gestion', $request->id_gestion);
        });

        // Fechas
        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('fecha_registro', [
                $request->fecha_inicio,
                $request->fecha_fin
            ]);
        }

        // Docente
        if ($request->id_docente) {
            $query->whereHas('asignacionDocente', function ($q) use ($request) {
                $q->where('id_docente', $request->id_docente);
            });
        }

        // Materia
        if ($request->id_materia) {
            $query->whereHas('asignacionDocente.materiaGrupo', function ($q) use ($request) {
                $q->where('id_materia', $request->id_materia);
            });
        }

        // Grupo
        if ($request->id_grupo) {
            $query->whereHas('asignacionDocente.materiaGrupo', function ($q) use ($request) {
                $q->where('id_grupo', $request->id_grupo);
            });
        }

        return $query;
    }



    /**
     * -----------------  ESTADÍSTICAS  -----------------
     */
    private function calcularEstadisticas($asistencias)
    {
        $total = $asistencias->count();
        if ($total == 0) {
            return ['total_clases_registradas' => 0];
        }

        $conteo = $asistencias->groupBy('estado.nombre')->map->count();

        return [
            'total_clases_registradas'   => $total,
            'total_presente'             => $conteo->get('Presente', 0),
            'total_tardanza'             => $conteo->get('Tardanza', 0),
            'total_ausente_injustificado'=> $conteo->get('Ausente', 0),
            'total_ausente_justificado'  => $conteo->get('Ausente Justificado', 0),
            'porcentaje_asistencia_efectiva' => round(
                (($conteo->get('Presente', 0)
                +$conteo->get('Tardanza', 0)
                +$conteo->get('Ausente Justificado', 0)) / $total) * 100, 2
            ),
            'porcentaje_ausentismo_real' => round(
                ($conteo->get('Ausente', 0) / $total) * 100, 2
            )
        ];
    }
}

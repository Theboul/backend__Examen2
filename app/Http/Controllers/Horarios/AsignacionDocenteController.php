<?php

namespace App\Http\Controllers\Horarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\AsignarDocenteRequest;
use App\Models\Horarios\AsignacionDocente;
use App\Models\Usuarios\Docente;
use App\Models\Maestros\MateriaGrupo;
use App\Models\Sistema\Gestion;
use App\Models\Sistema\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AsignacionDocenteController extends Controller
{
    /**
     * Listar asignaciones de docentes
     */
    public function index(Request $request)
    {
        try {
            $query = AsignacionDocente::with([
                'docente.perfil',
                'materiaGrupo.materia',
                'materiaGrupo.grupo',
                'materiaGrupo.gestion',
                'estado'
            ])->activos();
            

            // Filtrar por gestión
            if ($request->has('id_gestion')) {
                $query->porGestion($request->id_gestion);
            }

            // Filtrar por docente
            if ($request->has('id_docente')) {
                $query->porDocente($request->id_docente);
            }

            $asignaciones = $query->orderBy('fecha_asignacion', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $asignaciones
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las asignaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Asignar docente a materia-grupo (CU16)
     */
    public function store(AsignarDocenteRequest $request)
    {
        DB::beginTransaction();

        try {
            // 1. Verificar gestión activa
            $gestionActiva = Gestion::where('activo', true)->first();
            
            if (!$gestionActiva) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay gestión académica activa. Active una gestión primero'
                ], 422);
            }

            // 2. Obtener materia-grupo
            $materiaGrupo = MateriaGrupo::with(['materia', 'grupo'])
                ->where('id_materia_grupo', $request->id_materia_grupo)
                ->where('activo', true)
                ->first();

            if (!$materiaGrupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'La relación materia-grupo no existe o está inactiva'
                ], 422);
            }

            // 3. Verificar que la materia y el grupo estén activos
            if (!$materiaGrupo->materia->activo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede asignar. La materia está inactiva'
                ], 422);
            }

            if (!$materiaGrupo->grupo->activo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede asignar. El grupo está inactivo'
                ], 422);
            }

            // 4. Obtener docente (CORREGIDO)
            $docente = Docente::with('tipoContrato')->find($request->id_docente);

            if (!$docente) {
                return response()->json([
                    'success' => false,
                    'message' => 'El docente no existe'
                ], 422);
            }

            // 5. Verificar que el docente esté activo
            if (!$docente->activo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede asignar. El docente está inactivo'
                ], 422);
            }

            // 6. Verificar asignación duplicada (CORREGIDO)
            if (AsignacionDocente::existeAsignacion($request->id_docente, $request->id_materia_grupo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una asignación del mismo docente a este materia-grupo'
                ], 422);
            }

            // 7. Verificar si el grupo ya tiene docente para esa materia
            if (AsignacionDocente::materiaGrupoTieneDocente($request->id_materia_grupo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El grupo ya tiene un docente asignado para esta materia'
                ], 422);
            }

            // 8. Validar carga horaria máxima (CORREGIDO)
            $validacionCarga = AsignacionDocente::excedeCargarMaxima(
                $request->id_docente,
                $materiaGrupo->id_gestion,
                $request->hrs_asignadas
            );

            if ($validacionCarga['excede']) {
                return response()->json([
                    'success' => false,
                    'message' => $validacionCarga['mensaje']
                ], 422);
            }

            // 9. Crear la asignación (CORREGIDO)
            $asignacion = AsignacionDocente::create([
                'id_docente' => $request->id_docente,
                'id_materia_grupo' => $request->id_materia_grupo,
                'id_estado' => 1,
                'hrs_asignadas' => $request->hrs_asignadas,
                'activo' => true,
            ]);

            // 10. Registrar en bitácora
                $nombreDocente = $docente->perfil->nombre_completo ?? 'Docente sin perfil';
                $nombreMateria = $materiaGrupo->materia->nombre ?? 'Materia desconocida';
                $nombreGrupo   = $materiaGrupo->grupo->nombre ?? 'Grupo desconocido';

                Bitacora::registrar(
                    'ASIGNAR_DOCENTE',
                    "Docente $nombreDocente asignado a $nombreMateria - Grupo $nombreGrupo ({$request->hrs_asignadas} hrs)",
                    Auth::id()
                );

            DB::commit();

            // 11. Cargar relaciones
            $asignacion->loadMissing([
                'docente',
                'materiaGrupo'
            ]);
            return response()->json([
                'success' => true,
                'message' => '✓ Docente asignado exitosamente. Lista para definir horarios',
                'data' => $asignacion
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error("ERROR EN ASIGNAR DOCENTE", [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al asignar el docente',
                'error' => $e->getMessage()
            ], 500);
      }
    }
    public function paraSelect() {
    try {
        $items = AsignacionDocente::with([
            'docente.perfil',
            'materiaGrupo.materia',
            'materiaGrupo.grupo'
        ])
        ->where('activo', true)
        ->get()
        ->map(function ($a) {
            return [
                'id_asignacion_docente' => $a->id_asignacion_docente,
                'docente' => $a->docente->perfil->nombre_completo ?? 'Sin nombre',
                'materia' => $a->materiaGrupo->materia->nombre ?? 'Sin materia',
                'grupo'   => $a->materiaGrupo->grupo->nombre ?? 'Sin grupo',
                'label' => ($a->docente->perfil->nombre_completo ?? '')
                            . ' - '
                            . ($a->materiaGrupo->materia->nombre ?? '')
                            . ' ('
                            . ($a->materiaGrupo->grupo->nombre ?? '')
                            . ')'
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items
        ]);

    } catch (\Exception $e) {
        \Log::error("ERROR EN paraSelect ASIGNACION-DOCENTE", [
            "error" => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error al obtener asignaciones'
        ], 500);
    }
}
}

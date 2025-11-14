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
            // 1. Gestión activa
            $gestionActiva = Gestion::where('activo', true)->first();
            if (!$gestionActiva) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay gestión académica activa.'
                ], 422);
            }

            // 2. materia-grupo
            $materiaGrupo = MateriaGrupo::with(['materia','grupo'])
                ->where('id_materia_grupo', $request->id_materia_grupo)
                ->where('activo', true)
                ->first();

            if (!$materiaGrupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'La relación materia-grupo no existe.'
                ], 422);
            }

            // 3. Docente
            $docente = Docente::with('tipoContrato')->find($request->id_docente);

            if (!$docente) {
                return response()->json([
                    'success' => false,
                    'message' => 'El docente no existe.'
                ], 422);
            }

            if (!$docente->activo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El docente está inactivo.'
                ], 422);
            }

            // 4. Duplicados
            if (AsignacionDocente::existeAsignacion($request->id_docente, $request->id_materia_grupo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe esta asignación.'
                ], 422);
            }

            // 5. Grupo ya tiene docente?
            if (AsignacionDocente::materiaGrupoTieneDocente($request->id_materia_grupo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este grupo ya tiene un docente asignado.'
                ], 422);
            }

            // 6. Validar carga horaria
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

            // 7. Crear asignación
            $asignacion = AsignacionDocente::create([
                'id_docente' => $request->id_docente,
                'id_materia_grupo' => $request->id_materia_grupo,
                'id_estado' => 1,
                'hrs_asignadas' => $request->hrs_asignadas,
                'activo' => true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Docente asignado correctamente.',
                'data' => $asignacion
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error interno al asignar.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

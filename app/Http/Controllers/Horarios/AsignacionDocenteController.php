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

            // Registrar en bitácora
            Bitacora::registrar(
                'ASIGNAR_DOCENTE',
                sprintf(
                    'Docente %s asignado a %s - Grupo %s (%d hrs)',
                    $docente->perfil->nombre_completo ?? 'N/A',
                    $materiaGrupo->materia->nombre,
                    $materiaGrupo->grupo->nombre,
                    $request->hrs_asignadas
                ),
                Auth::id()
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error interno al asignar.',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Mostrar una asignación específica
     */
    public function show($id)
    {
        try {
            $asignacion = AsignacionDocente::with([
                'docente.perfil',
                'docente.tipoContrato',
                'materiaGrupo.materia',
                'materiaGrupo.grupo',
                'materiaGrupo.gestion',
                'estado'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $asignacion
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Asignación no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Actualizar horas asignadas
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'hrs_asignadas' => 'required|integer|min:1|max:40',
            ]);

            $asignacion = AsignacionDocente::findOrFail($id);

            // Validar que no exceda carga máxima con las nuevas horas
            $materiaGrupo = MateriaGrupo::find($asignacion->id_materia_grupo);
            $hrsActuales = AsignacionDocente::obtenerHorasAsignadasDocente(
                $asignacion->id_docente,  // id_docente contiene el código del docente
                $materiaGrupo->id_gestion
            );

            // Restar las horas actuales de esta asignación y sumar las nuevas
            $hrsActualesSinEsta = $hrsActuales - $asignacion->hrs_asignadas;
            $hrsNuevasTotal = $hrsActualesSinEsta + $request->hrs_asignadas;

            $docente = Docente::with('tipoContrato')->find($asignacion->id_docente);  // id_docente contiene el código
            $hrsMaximas = $docente->tipoContrato->hrs_maximas ?? 40;

            if ($hrsNuevasTotal > $hrsMaximas) {
                return response()->json([
                    'success' => false,
                    'message' => "Las nuevas horas excederían la carga máxima del docente. Máximo: {$hrsMaximas}hrs"
                ], 422);
            }

            $asignacion->update([
                'hrs_asignadas' => $request->hrs_asignadas
            ]);

            Bitacora::registrar(
                'ACTUALIZAR_ASIGNACION',
                sprintf('Horas actualizadas de asignación ID %d a %d hrs', $id, $request->hrs_asignadas),
                Auth::id()
            );

            DB::commit();

            $asignacion->load([
                'docente.perfil',
                'materiaGrupo.materia',
                'materiaGrupo.grupo'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Horas actualizadas exitosamente',
                'data' => $asignacion
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la asignación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desactivar asignación
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $asignacion = AsignacionDocente::findOrFail($id);

            // TODO: Verificar si tiene horarios asignados antes de desactivar
            
            $asignacion->update(['activo' => false]);

            Bitacora::registrar(
                'DESACTIVAR_ASIGNACION',
                sprintf('Asignación ID %d desactivada', $id),
                Auth::id()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asignación desactivada exitosamente'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar la asignación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener carga horaria de un docente
     */
    public function cargaHoraria($codDocente)
    {
        try {
            $gestionActiva = Gestion::where('activo', true)->first();

            if (!$gestionActiva) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay gestión activa'
                ], 422);
            }

            $docente = Docente::with('tipoContrato')->findOrFail($codDocente);
            
            $hrsAsignadas = AsignacionDocente::obtenerHorasAsignadasDocente(
                $codDocente,
                $gestionActiva->id_gestion
            );

            $hrsMaximas = $docente->tipoContrato->hrs_maximas ?? 40;
            $hrsDisponibles = $hrsMaximas - $hrsAsignadas;

            return response()->json([
                'success' => true,
                'data' => [
                    'docente' => $docente->perfil->nombre_completo ?? 'N/A',
                    'tipo_contrato' => $docente->tipoContrato->nombre ?? 'N/A',
                    'hrs_asignadas' => $hrsAsignadas,
                    'hrs_maximas' => $hrsMaximas,
                    'hrs_disponibles' => $hrsDisponibles,
                    'porcentaje_carga' => round(($hrsAsignadas / $hrsMaximas) * 100, 2)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la carga horaria',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Maestros;

use App\Http\Controllers\Controller;
use App\Models\Maestros\Aula;
use App\Models\Sistema\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AulaController extends Controller
{
    /**
     * Obtener todas las aulas activas
     */
    public function index(Request $request)
    {
        try {
            $query = Aula::query()->with('tipoAula:id_tipo_aula,nombre');

            // Filtros
            if ($request->boolean('disponibles', false)) {
                $query->disponibles();
            } elseif ($request->boolean('en_mantenimiento', false)) {
                $query->enMantenimiento();
            } elseif ($request->has('incluir_inactivas') && $request->boolean('incluir_inactivas')) {
                $query = Aula::withInactive()->with('tipoAula:id_tipo_aula,nombre');
            } else {
                $query->activas();
            }

            $aulas = $query->orderBy('piso')->orderBy('nombre')->get();

            return response()->json([
                'success' => true,
                'data' => $aulas,
                'message' => 'Aulas obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las aulas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un aula específica
     */
    public function show($id)
    {
        try {
            $aula = Aula::withInactive()->with('tipoAula')->find($id);

            if (!$aula) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aula no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $aula,
                'message' => 'Aula obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el aula',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva aula
     */
    public function store(Request $request)
    {
        try {
            // Validar datos de entrada según CU5
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100|unique:aula,nombre',
                'capacidad' => 'required|integer|min:1',
                'piso' => 'nullable|integer',
                'id_tipo_aula' => 'required|integer|exists:tipo_aula,id_tipo_aula',
                'mantenimiento' => 'nullable|boolean',
            ], [
                'nombre.required' => 'El nombre del aula es obligatorio',
                'nombre.unique' => 'Ya existe un aula con el nombre ingresado',
                'capacidad.required' => 'La capacidad del aula es obligatoria',
                'capacidad.min' => 'La capacidad del aula debe ser mayor a 0',
                'id_tipo_aula.required' => 'El tipo de aula es obligatorio',
                'id_tipo_aula.exists' => 'El tipo de aula seleccionado no existe',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Crear el aula
            $aula = Aula::create([
                'nombre' => $request->nombre,
                'capacidad' => $request->capacidad,
                'piso' => $request->piso ?? 0,
                'id_tipo_aula' => $request->id_tipo_aula,
                'mantenimiento' => $request->mantenimiento ?? false,
                'activo' => true,
            ]);

            Bitacora::registrar(
                'CREAR',
                "Aula creada: {$aula->nombre} (Capacidad: {$aula->capacidad}) - ID: {$aula->id_aula}"
            );

            DB::commit();

            // Cargar la relación para la respuesta
            $aula->load('tipoAula');

            return response()->json([
                'success' => true,
                'data' => $aula,
                'message' => 'Aula creada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el aula',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un aula existente
     */
    public function update(Request $request, $id)
    {
        try {
            $aula = Aula::withInactive()->find($id);

            if (!$aula) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aula no encontrada'
                ], 404);
            }

            // Validar datos de entrada (ignorar unique para el mismo registro)
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100|unique:aula,nombre,' . $id . ',id_aula',
                'capacidad' => 'required|integer|min:1',
                'piso' => 'nullable|integer',
                'id_tipo_aula' => 'required|integer|exists:tipo_aula,id_tipo_aula',
                'mantenimiento' => 'nullable|boolean',
            ], [
                'nombre.required' => 'El nombre del aula es obligatorio',
                'nombre.unique' => 'Ya existe un aula con el nombre ingresado',
                'capacidad.required' => 'La capacidad del aula es obligatoria',
                'capacidad.min' => 'La capacidad del aula debe ser mayor a 0',
                'id_tipo_aula.required' => 'El tipo de aula es obligatorio',
                'id_tipo_aula.exists' => 'El tipo de aula seleccionado no existe',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Actualizar el aula
            $aula->update([
                'nombre' => $request->nombre,
                'capacidad' => $request->capacidad,
                'piso' => $request->piso ?? $aula->piso,
                'id_tipo_aula' => $request->id_tipo_aula,
                'mantenimiento' => $request->mantenimiento ?? $aula->mantenimiento,
            ]);

            Bitacora::registrar(
                'ACTUALIZAR',
                "Aula actualizada: {$aula->nombre} (Capacidad: {$aula->capacidad}) - ID: {$aula->id_aula}"
            );

            DB::commit();

            // Cargar la relación para la respuesta
            $aula->load('tipoAula');

            return response()->json([
                'success' => true,
                'data' => $aula,
                'message' => 'Aula actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el aula',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desactivar un aula (eliminación lógica)
     */
    public function destroy($id)
    {
        try {
            $aula = Aula::activas()->find($id);

            if (!$aula) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aula no encontrada o ya está desactivada'
                ], 404);
            }

            DB::beginTransaction();

            // Verificar si el aula puede ser desactivada
            if (!$aula->puedeDesactivarse()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede desactivar el aula porque tiene horarios asignados'
                ], 400);
            }

            // Desactivar el aula (eliminación lógica)
            $aula->update(['activo' => false]);

            Bitacora::registrar(
                'DESACTIVAR',
                "Aula desactivada: {$aula->nombre} - ID: {$aula->id_aula}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Aula desactivada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar el aula',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reactivar un aula previamente desactivada
     */
    public function reactivar($id)
    {
        try {
            $aula = Aula::withInactive()->where('activo', false)->find($id);

            if (!$aula) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aula no encontrada o ya está activa'
                ], 404);
            }

            DB::beginTransaction();

            // Reactivar el aula
            $aula->update(['activo' => true]);

            Bitacora::registrar(
                'REACTIVAR',
                "Aula reactivada: {$aula->nombre} - ID: {$aula->id_aula}"
            );

            DB::commit();

            // Cargar la relación para la respuesta
            $aula->load('tipoAula');

            return response()->json([
                'success' => true,
                'data' => $aula,
                'message' => 'Aula reactivada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al reactivar el aula',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener aulas para select/combobox (solo activas y disponibles)
     */
    public function getAulasForSelect(Request $request)
    {
        try {
            $query = Aula::disponibles()
                ->select('id_aula', 'nombre', 'capacidad', 'piso')
                ->orderBy('piso')
                ->orderBy('nombre');

            // Filtrar por tipo si se proporciona
            if ($request->has('id_tipo_aula')) {
                $query->where('id_tipo_aula', $request->id_tipo_aula);
            }

            $aulas = $query->get()->map(function ($aula) {
                return [
                    'value' => $aula->id_aula,
                    'label' => $aula->nombre . ' (Piso ' . $aula->piso . ', Cap: ' . $aula->capacidad . ')'
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $aulas,
                'message' => 'Aulas para select obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener aulas para select',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado de mantenimiento del aula
     */
    public function toggleMantenimiento($id)
    {
        try {
            $aula = Aula::activas()->find($id);

            if (!$aula) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aula no encontrada'
                ], 404);
            }

            DB::beginTransaction();

            $nuevoEstado = !$aula->mantenimiento;
            $aula->update(['mantenimiento' => $nuevoEstado]);

            $accion = $nuevoEstado ? 'MANTENIMIENTO_ACTIVADO' : 'MANTENIMIENTO_DESACTIVADO';
            $descripcion = $nuevoEstado 
                ? "Aula puesta en mantenimiento: {$aula->nombre}" 
                : "Aula sacada de mantenimiento: {$aula->nombre}";
            
            Bitacora::registrar($accion, $descripcion);

            DB::commit();

            $aula->load('tipoAula');

            return response()->json([
                'success' => true,
                'data' => $aula,
                'message' => ($nuevoEstado ? 'Aula puesta en mantenimiento' : 'Aula sacada de mantenimiento') . ' exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado de mantenimiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * CU8: Consultar disponibilidad de aulas
     * Permite al Coordinador Académico consultar qué aulas están
     * disponibles u ocupadas en un día y bloque horario específico.
     */
    public function consultarDisponibilidad(Request $request)
    {
        try {
            // Validar parámetros de entrada
            $validator = Validator::make($request->all(), [
                'id_dia' => 'required|integer|exists:dia,id_dia',
                'id_bloque_horario' => 'required|integer|exists:bloque_horario,id_bloque_horario',
            ], [
                'id_dia.required' => 'Debe seleccionar un día.',
                'id_dia.exists' => 'El día seleccionado no es válido.',
                'id_bloque_horario.required' => 'Debe seleccionar un bloque horario.',
                'id_bloque_horario.exists' => 'El bloque horario seleccionado no es válido.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $idDia = $request->id_dia;
            $idBloque = $request->id_bloque_horario;

            // Obtener aulas activas
            $aulas = Aula::activas()
                ->with('tipoAula:id_tipo_aula,nombre')
                ->get(['id_aula', 'nombre', 'capacidad', 'piso', 'id_tipo_aula', 'mantenimiento']);

            if ($aulas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existen aulas activas registradas.',
                    'data' => []
                ]);
            }

            // Consultar aulas ocupadas en ese día y bloque
            $aulasOcupadas = \App\Models\Horarios\HorarioClase::where('id_dia', $idDia)
                ->where('id_bloque_horario', $idBloque)
                ->where('activo', true)
                ->pluck('id_aula')
                ->toArray();

            // Construir resultado con estado
            $resultado = $aulas->map(function ($aula) use ($aulasOcupadas) {
                // Determinar estado según prioridad
                if ($aula->mantenimiento) {
                    $estado = 'NO DISPONIBLE';
                    $motivo = 'En mantenimiento';
                } elseif (in_array($aula->id_aula, $aulasOcupadas)) {
                    $estado = 'OCUPADA';
                    $motivo = 'Clase asignada';
                } else {
                    $estado = 'DISPONIBLE';
                    $motivo = null;
                }
                
                return [
                    'id_aula' => $aula->id_aula,
                    'nombre' => $aula->nombre,
                    'capacidad' => $aula->capacidad,
                    'piso' => $aula->piso,
                    'tipo_aula' => $aula->tipoAula ? $aula->tipoAula->nombre : null,
                    'estado' => $estado,
                    'motivo' => $motivo
                ];
            });

            // Registrar en bitácora
            Bitacora::registrar(
                'CONSULTA',
                "Consulta de disponibilidad de aulas para día ID {$idDia}, bloque ID {$idBloque}"
            );

            // Calcular resumen
            $resumen = [
                'total' => $resultado->count(),
                'disponibles' => $resultado->where('estado', 'DISPONIBLE')->count(),
                'ocupadas' => $resultado->where('estado', 'OCUPADA')->count(),
                'no_disponibles' => $resultado->where('estado', 'NO DISPONIBLE')->count(),
            ];

            // Respuesta exitosa
            return response()->json([
                'success' => true,
                'data' => $resultado->values(),
                'resumen' => $resumen,
                'message' => 'Consulta de disponibilidad realizada correctamente.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar la disponibilidad de aulas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}

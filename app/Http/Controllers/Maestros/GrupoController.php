<?php

namespace App\Http\Controllers\Maestros;

use App\Http\Controllers\Controller;
use App\Models\Maestros\Grupo;
use App\Models\Sistema\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GrupoController extends Controller
{
    /**
     * Listar grupos
     */
    public function index(Request $request)
    {
        try {
            $query = Grupo::with(['materiaGrupos.materia']);

            if (!$request->boolean('incluir_inactivos')) {
                $query->where('activo', true);
            }

            if ($request->has('nombre')) {
                $query->where('nombre', 'ILIKE', '%' . $request->nombre . '%');
            }

            $grupos = $query->orderBy('id_grupo')->get();

            return response()->json([
                'success' => true,
                'data' => $grupos
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos',
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Crear grupo
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:50',
                'descripcion' => 'nullable|string',
                'capacidad_maxima' => 'required|integer|min:1',
                'cupos' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            if (($request->cupos ?? 0) > $request->capacidad_maxima) {
                return response()->json([
                    'success' => false,
                    'message' => 'Los cupos no pueden exceder la capacidad máxima'
                ], 422);
            }

            $data = [
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'capacidad_maxima' => $request->capacidad_maxima,
                'cupos' => $request->cupos ?? 0,
                'activo' => true,
                'creado_por' => auth()->check() ? auth()->user()->id_perfil_usuario : null
            ];

            $grupo = Grupo::create($data);

            Bitacora::registrar("CREAR", "Grupo creado: {$grupo->nombre}");

            return response()->json([
                'success' => true,
                'message' => 'Grupo creado correctamente',
                'data' => $grupo
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar grupo
     */
    public function update(Request $request, $id)
    {
        try {
            $grupo = Grupo::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nombre' => 'sometimes|required|string|max:50',
                'descripcion' => 'nullable|string',
                'capacidad_maxima' => 'sometimes|required|integer|min:1',
                'cupos' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $capacidadMaxima = $request->capacidad_maxima ?? $grupo->capacidad_maxima;
            $cupos = $request->cupos ?? $grupo->cupos;

            if ($cupos > $capacidadMaxima) {
                return response()->json([
                    'success' => false,
                    'message' => 'Los cupos no pueden exceder la capacidad máxima'
                ], 422);
            }

            $grupo->update($request->only(['nombre', 'descripcion', 'capacidad_maxima', 'cupos']));

            Bitacora::registrar("ACTUALIZAR", "Grupo actualizado: {$grupo->nombre}");

            return response()->json([
                'success' => true,
                'message' => 'Grupo actualizado correctamente',
                'data' => $grupo
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desactivar grupo
     */
    public function destroy($id)
    {
        try {
            $grupo = Grupo::findOrFail($id);

            if (!$grupo->puedeDesactivarse()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede desactivar: tiene asignaciones activas'
                ], 422);
            }

            $grupo->update(['activo' => false]);

            Bitacora::registrar("DESACTIVAR", "Grupo desactivado: {$grupo->nombre}");

            return response()->json([
                'success' => true,
                'message' => 'Grupo desactivado correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reactivar grupo
     */
    public function reactivar($id)
    {
        try {
            $grupo = Grupo::findOrFail($id);

            $grupo->update(['activo' => true]);

            Bitacora::registrar("REACTIVAR", "Grupo reactivado: {$grupo->nombre}");

            return response()->json([
                'success' => true,
                'message' => 'Grupo reactivado correctamente',
                'data' => $grupo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reactivar grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // MÉTODO PARA EL SELECT (SOLICITADO POR EL FRONT)
    // ============================================================
    /**
     * Obtener grupos activos para SELECT
     */
    public function getGruposForSelect()
    {
        try {
            $grupos = Grupo::where('activo', true)
                ->orderBy('nombre')
                ->get()
                ->map(fn($g) => [
                    'value' => $g->id_grupo,
                    'label' => $g->nombre
                ]);

            return response()->json([
                'success' => true,
                'data' => $grupos
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos para select',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}


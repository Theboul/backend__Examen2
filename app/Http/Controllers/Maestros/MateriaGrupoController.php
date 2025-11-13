<?php

namespace App\Http\Controllers\Maestros;

use App\Http\Controllers\Controller;
use App\Models\Maestros\MateriaGrupo;
use App\Models\Sistema\Gestion;
use Illuminate\Http\Request;

class MateriaGrupoController extends Controller
{
    /**
     * GET /api/materia-grupos
     * Listar todos los MateriaGrupo de la gestión activa
     */
    public function index(Request $request)
    {
        try {
            $gestionActiva = Gestion::getActiva();
            if (!$gestionActiva) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay gestión activa'
                ], 422);
            }

            $materiaGrupos = MateriaGrupo::with([
                    'materia:id_materia,sigla,nombre',
                    'grupo:id_grupo,nombre',
                    'gestion:id_gestion,anio,semestre',
                    'asignacionDocenteActiva.docente.usuario.perfil:id_usuario,nombres,apellidos'
                ])
                ->where('id_gestion', $gestionActiva->id_gestion)
                ->orderBy('fecha_creacion', 'desc')
                ->get()
                ->map(function ($mg) {
                    $docente = $mg->asignacionDocenteActiva?->docente;
                    $perfil = $docente?->usuario?->perfil;
                    
                    return [
                        'id_materia_grupo' => $mg->id_materia_grupo,
                        'materia' => [
                            'sigla' => $mg->materia->sigla ?? 'N/A',
                            'nombre' => $mg->materia->nombre ?? 'N/A'
                        ],
                        'grupo' => [
                            'nombre' => $mg->grupo->nombre ?? 'N/A'
                        ],
                        'gestion' => sprintf('%s/%d', $mg->gestion->semestre ?? 'N/A', $mg->gestion->anio ?? 0),
                        'docente_asignado' => $perfil ? $perfil->nombre_completo : 'Sin asignar',
                        'observacion' => $mg->observacion,
                        'activo' => $mg->activo,
                        'fecha_creacion' => $mg->fecha_creacion?->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $materiaGrupos
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener materia-grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/materia-grupos
     * Crear un nuevo MateriaGrupo
     */
    public function store(Request $request)
    {
        try {
            \Log::info('POST /materia-grupos - Datos recibidos:', $request->all());
            
            $validated = $request->validate([
                'id_materia' => 'required|exists:materia,id_materia',
                'id_grupo' => 'required|exists:grupo,id_grupo',
                'observacion' => 'nullable|string|max:500'
            ]);
            
            \Log::info('Validación exitosa:', $validated);

            // Obtener gestión activa
            $gestionActiva = Gestion::getActiva();
            if (!$gestionActiva) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay gestión activa'
                ], 422);
            }
            
            \Log::info('Gestión activa:', ['id' => $gestionActiva->id_gestion]);

            // Validar que no exista la combinación
            $existe = MateriaGrupo::where('id_materia', $validated['id_materia'])
                ->where('id_grupo', $validated['id_grupo'])
                ->where('id_gestion', $gestionActiva->id_gestion)
                ->where('activo', true)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta combinación de Materia y Grupo ya existe para la gestión activa'
                ], 422);
            }
            
            \Log::info('Intentando crear materia-grupo...');

            $materiaGrupo = MateriaGrupo::create([
                'id_materia' => $validated['id_materia'],
                'id_grupo' => $validated['id_grupo'],
                'id_gestion' => $gestionActiva->id_gestion,
                'observacion' => $validated['observacion'] ?? null,
                'activo' => true
            ]);
            
            \Log::info('Materia-grupo creado exitosamente:', ['id' => $materiaGrupo->id_materia_grupo]);

            return response()->json([
                'success' => true,
                'message' => 'Materia-Grupo creado exitosamente',
                'data' => $materiaGrupo->load(['materia', 'grupo', 'gestion'])
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Error de validación:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error al crear materia-grupo:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear materia-grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/materia-grupos/{id}
     * Ver detalle de un MateriaGrupo
     */
    public function show($id)
    {
        try {
            $materiaGrupo = MateriaGrupo::with([
                'materia',
                'grupo',
                'gestion',
                'asignacionDocenteActiva.docente.user'
            ])->find($id);

            if (!$materiaGrupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materia-Grupo no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $materiaGrupo
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener materia-grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/materia-grupos/{id}
     * Actualizar un MateriaGrupo
     */
    public function update(Request $request, $id)
    {
        try {
            $materiaGrupo = MateriaGrupo::find($id);
            if (!$materiaGrupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materia-Grupo no encontrado'
                ], 404);
            }

            $validated = $request->validate([
                'id_materia' => 'sometimes|exists:materias,id_materia',
                'id_grupo' => 'sometimes|exists:grupos,id_grupo',
                'observacion' => 'nullable|string|max:500'
            ]);

            // Si se cambia materia o grupo, validar que no exista duplicado
            if (isset($validated['id_materia']) || isset($validated['id_grupo'])) {
                $idMateria = $validated['id_materia'] ?? $materiaGrupo->id_materia;
                $idGrupo = $validated['id_grupo'] ?? $materiaGrupo->id_grupo;

                $existe = MateriaGrupo::where('id_materia', $idMateria)
                    ->where('id_grupo', $idGrupo)
                    ->where('id_gestion', $materiaGrupo->id_gestion)
                    ->where('id_materia_grupo', '!=', $id)
                    ->where('activo', true)
                    ->exists();

                if ($existe) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Esta combinación de Materia y Grupo ya existe'
                    ], 422);
                }
            }

            $materiaGrupo->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Materia-Grupo actualizado exitosamente',
                'data' => $materiaGrupo->load(['materia', 'grupo', 'gestion'])
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar materia-grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/materia-grupos/{id}
     * Desactivar un MateriaGrupo (soft delete)
     */
    public function destroy($id)
    {
        try {
            $materiaGrupo = MateriaGrupo::find($id);
            if (!$materiaGrupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materia-Grupo no encontrado'
                ], 404);
            }

            // Verificar si tiene docente asignado activo
            if ($materiaGrupo->asignacionDocenteActiva()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede desactivar: tiene un docente asignado. Primero desactive la asignación.'
                ], 422);
            }

            $materiaGrupo->update(['activo' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Materia-Grupo desactivado exitosamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar materia-grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/materia-grupos/{id}/reactivar
     * Reactivar un MateriaGrupo
     */
    public function reactivar($id)
    {
        try {
            $materiaGrupo = MateriaGrupo::find($id);
            if (!$materiaGrupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materia-Grupo no encontrado'
                ], 404);
            }

            if ($materiaGrupo->activo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El Materia-Grupo ya está activo'
                ], 422);
            }

            $materiaGrupo->update(['activo' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Materia-Grupo reactivado exitosamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reactivar materia-grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/materia-grupos/select
     * * Obtiene una lista de Materia-Grupos para un <select> del frontend.
     * Filtra automáticamente por la gestión activa y
     * EXCLUYE los grupos que ya tienen un docente asignado.
     */
    public function paraSelectActivos(Request $request)
    {
        try {
            // 1. Obtener la Gestión Activa
            $gestionActiva = Gestion::getActiva();
            if (!$gestionActiva) {
                return response()->json([
                    'success' => false, 
                    'message' => 'No hay gestión activa configurada'
                ], 422);
            }
            $idGestion = $gestionActiva->id_gestion;

            // 2. Construir la consulta
            $materiaGrupos = MateriaGrupo::with([
                    'materia:id_materia,sigla,nombre', 
                    'grupo:id_grupo,nombre'             
                ])
                ->where('id_gestion', $idGestion)
                ->where('activo', true)
                
                // 3.
                // Gracias a la relación que añadimos en el Paso 1,
                // podemos filtrar los que NO TIENEN una asignación activa.
                ->whereDoesntHave('asignacionDocenteActiva') 
                
                ->get();

            // 4. Formatear la salida para el <select> del frontend
            $opciones = $materiaGrupos->map(function ($mg) {
                return [
                    'value' => $mg->id_materia_grupo,
                    'label' => sprintf(
                        '[%s] %s (Grupo: %s)',
                        $mg->materia->sigla ?? 'N/A',
                        $mg->materia->nombre ?? 'Materia desconocida',
                        $mg->grupo->nombre ?? 'Grupo desconocido'
                    )
                ];
            })
            // Opcional: Ordenar por el label
            ->sortBy('label')
            ->values(); // Resetear keys del array

            return response()->json([
                'success' => true,
                'data' => $opciones
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener materia-grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
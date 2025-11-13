<?php

namespace App\Http\Controllers\Maestros;

use App\Http\Controllers\Controller;
use App\Models\Maestros\Semestre;
use Illuminate\Http\Request;

class SemestreController extends Controller
{
    /**
     * GET /api/semestres/select
     * Obtener semestres (para dropdowns)
     */
    public function paraSelect()
    {
        try {
            $semestres = Semestre::orderBy('id_semestre')
                ->get(['id_semestre as value', 'nombre as label']);

            return response()->json([
                'success' => true,
                'data' => $semestres
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener semestres',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
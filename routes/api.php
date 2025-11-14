<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Sistema\GestionController;
use App\Http\Controllers\Maestros\CarreraController;
use App\Http\Controllers\Maestros\MateriaController;
use App\Http\Controllers\Maestros\AulaController;
use App\Http\Controllers\Maestros\GrupoController;
use App\Http\Controllers\Maestros\MateriaGrupoController;
use App\Http\Controllers\Usuarios\DocenteController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Maestros\TipoAulaController;
use App\Http\Controllers\Usuarios\CargaMasivaController;
use App\Http\Controllers\Horarios\AsignacionDocenteController;
use App\Http\Controllers\Horarios\DiaController;
use App\Http\Controllers\Horarios\BloqueHorarioController;
use App\Http\Controllers\Maestros\TipoClaseController;
use App\Http\Controllers\Horarios\HorarioClaseController;
use App\Http\Controllers\Horarios\AsistenciaController;
use App\Http\Controllers\Horarios\JustificacionController;
use App\Http\Controllers\Horarios\RevisionJustificacionController;
use App\Http\Controllers\Sistema\BitacoraController;

// ============================================
//  RUTAS PÚBLICAS (SIN TOKEN)
// ============================================
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/cambiar-password', [AuthController::class, 'cambiarPasswordPrimerIngreso']);

// ============================================
//  RUTAS PROTEGIDAS POR SANCTUM
// ============================================
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', fn(Request $req) => $req->user());

    // ==============================
    // ADMINISTRADOR
    // ==============================
    Route::middleware('role:Administrador')->group(function () {

        // ===== SELECTS =====
        Route::get('/docentes/select', [DocenteController::class, 'getDocentesForSelect']);
        Route::get('/grupos/select', [GrupoController::class, 'getGruposForSelect']); 
        Route::get('/aulas/select', [AulaController::class, 'select']);

        // ===== SELECT materia-grupos disponibles =====
        Route::get('/materia-grupos/select', [MateriaGrupoController::class, 'paraSelectActivos']);

        // ===== CRUD =====
        Route::apiResource('usuarios', UsuarioController::class);
        Route::post('/usuarios/carga-masiva', [UsuarioController::class, 'cargaMasiva']);

        Route::apiResource('docentes', DocenteController::class);
        Route::apiResource('materias', MateriaController::class);
        Route::apiResource('grupos', GrupoController::class);
        Route::apiResource('aulas', AulaController::class);

        // ===== CRUD COMPLETO Materia-Grupo =====
        Route::apiResource('materia-grupos', MateriaGrupoController::class);
        Route::post('/materia-grupos/{id}/reactivar', [MateriaGrupoController::class, 'reactivar']);

        Route::get('/bitacora', [BitacoraController::class, 'index']);
        Route::get('/reportes/asistencia', [ReporteController::class, 'asistencia']);
    });

    // ==============================
    // COORDINADOR
    // ==============================
    Route::middleware('role:Coordinador')->group(function () {

        // ===== SELECTS =====
        Route::get('/docentes/select', [DocenteController::class, 'getDocentesForSelect']);
        Route::get('/grupos/select', [GrupoController::class, 'getGruposForSelect']);
        Route::get('/aulas/select', [AulaController::class, 'select']);

        // ===== SELECT materia-grupos disponibles =====
        Route::get('/materia-grupos/select', [MateriaGrupoController::class, 'paraSelectActivos']);

        // ===========================
        // CRUDs (solo lectura)
        // ===========================
        Route::apiResource('docentes', DocenteController::class)->only(['index']);
        Route::apiResource('materias', MateriaController::class)->only(['index']);
        Route::apiResource('grupos', GrupoController::class)->only(['index']);
        Route::apiResource('aulas', AulaController::class);

        // ===========================
        // CRUD Materia-Grupo (limitado)
        // ===========================
        Route::apiResource('materia-grupos', MateriaGrupoController::class)->only([
            'index', 'store', 'update'
        ]);
        Route::post('/materia-grupos/{id}/reactivar', [MateriaGrupoController::class, 'reactivar']);

        // ===========================
        // ASIGNACIONES DOCENTE
        // ===========================
        Route::get('/asignaciones-docente', [AsignacionDocenteController::class, 'index']);
        Route::post('/asignaciones-docente', [AsignacionDocenteController::class, 'store']);

        // ===========================
        // HORARIOS
        // ===========================
        Route::post('/horarios/manual', [HorarioController::class, 'asignarManual']);
        Route::post('/horarios/automatico', [HorarioController::class, 'asignarAutomatico']);

        // SELECTS AUXILIARES
        Route::get('/dias/select', [HorarioController::class, 'getDias']);
        Route::get('/bloques-horario/select', [HorarioController::class, 'getBloques']);
        Route::get('/tipos-clase/select', [HorarioController::class, 'getTiposClase']);

        // DISPONIBILIDAD AULAS
        Route::get('/disponibilidad-aulas', [AulaController::class, 'consultarDisponibilidad']);
    });

    // ==============================
    // AUTORIDAD
    // ==============================
    Route::middleware('role:Autoridad')->group(function () {
        Route::get('/visualizar-horarios', [HorarioController::class, 'visualizar']);
        Route::post('/publicar-horarios', [HorarioController::class, 'publicar']);

        Route::get('/reportes/asistencia', [ReporteController::class, 'asistencia']);
        Route::get('/bitacora', [BitacoraController::class, 'index']);
    });

    // ==============================
    // DOCENTE
    // ==============================
    Route::middleware('role:Docente')->group(function () {

        Route::get('/docente/horario', [HorarioController::class, 'horarioDocente']);

        Route::post('/docente/asistencia', [AsistenciaController::class, 'registrar']);
        Route::post('/docente/justificacion', [AsistenciaController::class, 'justificar']);
    });
});

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Sistema\GestionController;
use App\Http\Controllers\Maestros\CarreraController;
use App\Http\Controllers\Maestros\MateriaController;
use App\Http\Controllers\Maestros\SemestreController;
use App\Http\Controllers\Maestros\AulaController;
use App\Http\Controllers\Maestros\GrupoController;
use App\Http\Controllers\Usuarios\DocenteController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Maestros\TipoAulaController;
use App\Http\Controllers\Usuarios\CargaMasivaController;
use App\Http\Controllers\Maestros\MateriaGrupoController;
use App\Http\Controllers\Horarios\AsignacionDocenteController;
use App\Http\Controllers\Horarios\DiaController;
use App\Http\Controllers\Horarios\BloqueHorarioController;
use App\Http\Controllers\Maestros\TipoClaseController;
use App\Http\Controllers\Horarios\HorarioClaseController;
use App\Http\Controllers\Horarios\AsistenciaController;
use App\Http\Controllers\Horarios\JustificacionController;
use App\Http\Controllers\Horarios\RevisionJustificacionController;
use App\Http\Controllers\Sistema\BitacoraController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// RUTAS DE PRUEBA - ELIMINAR DESPUÉS
Route::get('/test', function () {
    return response()->json(['message' => 'API funcionando correctamente']);
});

// ========== AUTENTICACIÓN (Públicas) ==========
Route::prefix('/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    
    // Rutas protegidas con Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/cambiar-password', [AuthController::class, 'cambiarPasswordPrimerIngreso']);
        Route::post('/toggle-activo/{id}', [AuthController::class, 'toggleActivoCuenta'])->middleware('role:Administrador');
    });
});

// ========== RUTAS PARA ADMINISTRADOR Y COORDINADOR (DEBEN IR ANTES DE SOLO ADMIN) ==========
Route::middleware(['auth:sanctum', 'role:Administrador,Coordinador'])->group(function () {
    
    // Dropdowns que Admin y Coordinador necesitan
    Route::get('/materias/select', [MateriaController::class, 'getMateriasForSelect']);
    Route::get('/semestres/select', [SemestreController::class, 'paraSelect']);
    
    // Grupos - CRUD (Admin y Coordinador)
    Route::prefix('/grupos')->group(function () {
        Route::get('/', [GrupoController::class, 'index']);
        Route::post('/', [GrupoController::class, 'store']);
        Route::get('/select', [GrupoController::class, 'getGruposForSelect']);
        Route::get('/{id}', [GrupoController::class, 'show']);
        Route::put('/{id}', [GrupoController::class, 'update']);
        Route::delete('/{id}', [GrupoController::class, 'destroy']);
        Route::post('/{id}/reactivar', [GrupoController::class, 'reactivar']);
    });

    // Asignación de Docentes a Materia-Grupo (Admin y Coordinador)
    Route::prefix('/asignaciones-docente')->group(function () {
        Route::get('/', [AsignacionDocenteController::class, 'index']); // Listar asignaciones
        Route::post('/', [AsignacionDocenteController::class, 'store']); // Crear asignación
        Route::get('/{id}', [AsignacionDocenteController::class, 'show']); // Ver detalle de asignación
        Route::put('/{id}', [AsignacionDocenteController::class, 'update']); // Actualizar horas asignadas
        Route::delete('/{id}', [AsignacionDocenteController::class, 'destroy']); // Desactivar asignación
    });

    // CRUD de Materia-Grupos (Admin y Coordinador)
    Route::prefix('/materia-grupos')->group(function () {
        Route::get('/', [MateriaGrupoController::class, 'index']); // Listar todos
        Route::post('/', [MateriaGrupoController::class, 'store']); // Crear nuevo
        Route::get('/select', [MateriaGrupoController::class, 'paraSelectActivos']); // Para dropdown (sin docente asignado)
        Route::get('/{id}', [MateriaGrupoController::class, 'show']); // Ver detalle
        Route::put('/{id}', [MateriaGrupoController::class, 'update']); // Actualizar
        Route::delete('/{id}', [MateriaGrupoController::class, 'destroy']); // Desactivar
        Route::post('/{id}/reactivar', [MateriaGrupoController::class, 'reactivar']); // Reactivar
    });
    
    // Docentes - Operaciones de Lectura (Admin y Coordinador para asignaciones)
    Route::prefix('/docentes')->group(function () {
        Route::get('/', [DocenteController::class, 'index']);
        Route::get('/select', [DocenteController::class, 'getDocentesForSelect']);
        Route::get('/{id}', [DocenteController::class, 'show']); // /{id} debe ir al final
    });
    
    // Carga horaria de docentes (Admin y Coordinador)
    Route::get('/docentes/{cod_docente}/carga-horaria', [AsignacionDocenteController::class, 'cargaHoraria']);
});

// ========== RUTAS PARA ADMINISTRADOR ==========
Route::middleware(['auth:sanctum', 'role:Administrador'])->group(function () {
    
    // Gestiones - CRUD Completo (Solo Admin)
    Route::prefix('/gestiones')->group(function () {
        Route::get('/', [GestionController::class, 'index']);
        Route::post('/', [GestionController::class, 'store']);
        Route::get('/activa', [GestionController::class, 'getActiva']);
        Route::put('/{id}', [GestionController::class, 'update']);
        Route::post('/{id}/activar', [GestionController::class, 'activar']);
        Route::delete('/{id}', [GestionController::class, 'destroy']);
        Route::post('/{id}/reactivar', [GestionController::class, 'reactivar']);
    });

    // Carreras - CRUD Completo (Solo Admin)
    Route::prefix('/carreras')->group(function () {
        Route::get('/', [CarreraController::class, 'index']);
        Route::post('/', [CarreraController::class, 'store']);
        Route::get('/select', [CarreraController::class, 'getCarrerasForSelect']);
        Route::get('/{id}', [CarreraController::class, 'show']);
        Route::put('/{id}', [CarreraController::class, 'update']);
        Route::delete('/{id}', [CarreraController::class, 'destroy']);
        Route::post('/{id}/reactivar', [CarreraController::class, 'reactivar']);
    });

    // Materias - CRUD Completo (Solo Admin)
    Route::prefix('/materias')->group(function () {
        Route::get('/', [MateriaController::class, 'index']);
        Route::post('/', [MateriaController::class, 'store']);
        // Las rutas con parámetros {id} SIEMPRE al final
        Route::get('/{id}', [MateriaController::class, 'show']);
        Route::put('/{id}', [MateriaController::class, 'update']);
        Route::delete('/{id}', [MateriaController::class, 'destroy']);
        Route::post('/{id}/reactivar', [MateriaController::class, 'reactivar']);
    });

    // Aulas - CRUD Completo (Solo Admin)
    Route::prefix('/aulas')->group(function () {
        Route::get('/', [AulaController::class, 'index']);
        Route::post('/', [AulaController::class, 'store']);
        Route::get('/select', [AulaController::class, 'getAulasForSelect']);
        
        // CU8: Consultar disponibilidad de aulas (requiere middleware propio abajo)
        Route::get('/disponibilidad', [AulaController::class, 'consultarDisponibilidad'])
            ->withoutMiddleware('role:Administrador')
            ->middleware('role:Administrador,Coordinador');
        
        Route::get('/{id}', [AulaController::class, 'show']);
        Route::put('/{id}', [AulaController::class, 'update']);
        Route::delete('/{id}', [AulaController::class, 'destroy']);
        Route::post('/{id}/reactivar', [AulaController::class, 'reactivar']);
        Route::post('/{id}/toggle-mantenimiento', [AulaController::class, 'toggleMantenimiento']);
    });

    Route::prefix('/tipo-aulas')->group(function () {
        Route::get('/', [TipoAulaController::class, 'index']);
        Route::get('/select', [TipoAulaController::class, 'paraSelect']);
        Route::post('/', [TipoAulaController::class, 'store']);
        Route::put('/{id}', [TipoAulaController::class, 'update']);
    });

    // Docentes - Operaciones de Escritura (Solo Admin)
    Route::prefix('/docentes')->group(function () {
        Route::post('/', [DocenteController::class, 'store']);
        Route::put('/{id}', [DocenteController::class, 'update']);
        Route::delete('/{id}', [DocenteController::class, 'destroy']);
        Route::post('/{id}/reactivar', [DocenteController::class, 'reactivar']);
    });

    // Carga Masiva de Usuarios (Solo Admin)
    Route::prefix('/usuarios')->group(function () {
        Route::post('/carga-masiva', [CargaMasivaController::class, 'cargarUsuarios']);
    });

    Route::prefix('/bitacora')->group(function () {
        Route::get('/', [BitacoraController::class, 'index']);
        Route::get('/report', [BitacoraController::class, 'getReport']);
    });
});

// RUTAS DE CATALOGO DIA-BLOQUEHORARIO-TIPOAULA
Route::middleware(['auth:sanctum', 'role:Administrador,Coordinador'])->group(function () {
    // Catálogos para dropdowns
    Route::get('/dias/select', [DiaController::class, 'paraSelect']);
    Route::get('/bloques-horario/select', [BloqueHorarioController::class, 'paraSelect']);
    Route::get('/tipos-clase/select', [TipoClaseController::class, 'paraSelect']);

    // CU6: Asignación Manual de Horarios
    Route::prefix('/horarios-clase')->group(function () {
        Route::get('/', [HorarioClaseController::class, 'index']);      // Listar
        Route::post('/', [HorarioClaseController::class, 'store']);     // Crear (CU6)
        
        // CU7: Generación Automática (DEBE IR ANTES DE LAS RUTAS CON {id})
        Route::post('/generar-automatico', [HorarioClaseController::class, 'generarAutomatico']);
        
        Route::get('/{id}', [HorarioClaseController::class, 'show']);   // Ver detalle
        Route::put('/{id}', [HorarioClaseController::class, 'update']); // Actualizar
        Route::delete('/{id}', [HorarioClaseController::class, 'destroy']); // Eliminar
        Route::post('/{id}/reactivar', [HorarioClaseController::class, 'reactivar']); // Reactivar
    });
    
    // CU12: Visualizar Horarios Semanales (Admin, Coordinador y Autoridad)
    Route::get('/horarios/semanal', [HorarioClaseController::class, 'visualizarSemanal'])
        ->withoutMiddleware('role:Administrador,Coordinador')
        ->middleware('role:Administrador,Coordinador,Autoridad');

    // CU17: Gestión de Estados de Horarios
    Route::put('/horarios/aprobar', [HorarioClaseController::class, 'aprobarHorarios']); // BORRADOR → APROBADA
    Route::put('/horarios/publicar', [HorarioClaseController::class, 'publicarHorarios']) // APROBADA → PUBLICADA
        ->withoutMiddleware('role:Administrador,Coordinador')
        ->middleware('role:Administrador,Coordinador,Autoridad');


    //CU20 (Lado Admin/Coordinador) Revisión de justificaciones
    Route::prefix('/admin/justificaciones')->group(function () {
        // Listar todas las solicitudes pendientes
        Route::get('/pendientes', [RevisionJustificacionController::class, 'indexPendientes']);
        
        // Aprobar o Rechazar una solicitud específica
        Route::post('/{id}/revisar', [RevisionJustificacionController::class, 'revisar']);
    });
});

// ========== RUTAS PARA DOCENTE ==========
Route::middleware(['auth:sanctum', 'role:Docente'])->group(function () {
    // CU10: Consultar Carga Horaria Personal
    Route::get('/docente/horarios-personales', [HorarioClaseController::class, 'cargaHorariaPersonal']);

    Route::prefix('/asistencia')->group(function () {
        
        // CU9 - Método 1: Botón de Asistencia
        Route::post('/registrar', [AsistenciaController::class, 'registrarAsistencia']);
        
        //CU9 - Método 2: Escaneo de QR
        Route::post('/registrar-qr', [AsistenciaController::class, 'registrarAsistenciaQR']);
    });

    //CU20 (Lado Docente) Enviar una justificación para una asistencia específica
    Route::post('/asistencia/{id}/justificar', [JustificacionController::class, 'store']);
});


// ========== RUTAS PARA COORDINADOR Y AUTORIDAD (Solo Lectura) ==========
Route::middleware(['auth:sanctum', 'role:Coordinador,Autoridad'])->group(function () {
    
    // Consulta de Gestiones
    Route::get('/gestiones/consulta', [GestionController::class, 'index']);
    Route::get('/gestiones/activa/consulta', [GestionController::class, 'getActiva']);
    
    // Consulta de Carreras
    Route::get('/carreras/consulta', [CarreraController::class, 'index']);
    Route::get('/carreras/select/consulta', [CarreraController::class, 'getCarrerasForSelect']);
    
    // Consulta de Materias
    Route::get('/materias/consulta', [MateriaController::class, 'index']);
    Route::get('/materias/select/consulta', [MateriaController::class, 'getMateriasForSelect']); // Para Autoridad
    
    // Consulta de Aulas
    Route::get('/aulas/consulta', [AulaController::class, 'index']);
    Route::get('/aulas/select/consulta', [AulaController::class, 'getAulasForSelect']);
    // CU8: Disponibilidad ya definida en el grupo de Admin con permisos extendidos

});
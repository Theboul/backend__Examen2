<?php

namespace App\Models\Horarios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Usuarios\Docente;
use App\Models\Maestros\MateriaGrupo;
use App\Models\Sistema\Estado;

class AsignacionDocente extends Model
{
    use HasFactory;

    protected $table = 'asignacion_docente';
    protected $primaryKey = 'id_asignacion_docente';
    
    const CREATED_AT = 'fecha_asignacion';
    const UPDATED_AT = 'fecha_modificacion';

    protected $fillable = [
        'id_docente',  // FK que referencia a docente.cod_docente
        'id_materia_grupo',
        'id_estado',
        'hrs_asignadas',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'hrs_asignadas' => 'integer',
        'fecha_asignacion' => 'datetime',
        'fecha_modificacion' => 'datetime',
    ];

    // Relaciones
    public function docente()
    {
        return $this->belongsTo(Docente::class, 'id_docente', 'id_docente');
    }

    public function materiaGrupo()
    {
        return $this->belongsTo(MateriaGrupo::class, 'id_materia_grupo');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorGestion($query, $idGestion)
    {
        return $query->whereHas('materiaGrupo', function($q) use ($idGestion) {
            $q->where('id_gestion', $idGestion);
        });
    }

    public function scopePorDocente($query, $codDocente)
    {
        return $query->where('id_docente', $idDocente);  // id_docente es el campo en asignacion_docente
    }

    // Métodos de validación
    
    /**
     * Verificar si ya existe asignación del docente a ese materia-grupo
     */
    public static function existeAsignacion($codDocente, $idMateriaGrupo): bool
    {
        return self::where('id_docente', $idDocente)  // id_docente en tabla asignacion_docente
            ->where('id_materia_grupo', $idMateriaGrupo)
            ->where('activo', true)
            ->exists();
    }

    /**
     * Verificar si el materia-grupo ya tiene un docente asignado
     */
    public static function materiaGrupoTieneDocente($idMateriaGrupo): bool
    {
        return self::where('id_materia_grupo', $idMateriaGrupo)
            ->where('activo', true)
            ->exists();
    }

    /**
     * Obtener horas totales asignadas a un docente en una gestión
     */
    public static function obtenerHorasAsignadasDocente($codDocente, $idGestion): int
    {
        return self::where('id_docente', $idDocente)  // id_docente en tabla asignacion_docente
            ->where('activo', true)
            ->whereHas('materiaGrupo', function($q) use ($idGestion) {
                $q->where('id_gestion', $idGestion);
            })
            ->sum('hrs_asignadas');
    }

    /**
     * Verificar si el docente excedería la carga máxima con esta asignación
     */
    public static function excedeCargarMaxima($codDocente, $idGestion, $hrsNuevas): array
    {
        $docente = Docente::with('tipoContrato')->find($codDocente);
        
        if (!$docente || !$docente->tipoContrato) {
            return ['excede' => false, 'mensaje' => ''];
        }

        $hrsActuales = self::obtenerHorasAsignadasDocente($codDocente, $idGestion);
        $hrsTotal = $hrsActuales + $hrsNuevas;
        $hrsMaximas = $docente->tipoContrato->hrs_maximas;

        if ($hrsTotal > $hrsMaximas) {
            return [
                'excede' => true,
                'mensaje' => "El docente excedería la carga máxima. Actual: {$hrsActuales}hrs, Nueva asignación: {$hrsNuevas}hrs, Total: {$hrsTotal}hrs, Máximo permitido: {$hrsMaximas}hrs"
            ];
        }

        return ['excede' => false, 'mensaje' => ''];
    }
}

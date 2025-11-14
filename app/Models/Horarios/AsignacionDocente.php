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
        'id_docente',
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

    // ===========================
    //   RELACIONES CORREGIDAS
    // ===========================

    public function docente()
    {
        return $this->belongsTo(
            Docente::class,
            'id_docente',   // foreign key en asignacion_docente
            'cod_docente'   // primary key en docente (CORREGIDO)
        );
    }

    public function materiaGrupo()
    {
        return $this->belongsTo(MateriaGrupo::class, 'id_materia_grupo');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }

    // ===========================
    //   SCOPES
    // ===========================

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

    public function scopePorDocente($query, $idDocente)
    {
        return $query->where('id_docente', $idDocente);
    }

    // ===========================
    //   MÉTODOS AUXILIARES
    // ===========================

    public static function existeAsignacion($idDocente, $idMateriaGrupo): bool
    {
        return self::where('id_docente', $idDocente)
            ->where('id_materia_grupo', $idMateriaGrupo)
            ->where('activo', true)
            ->exists();
    }

    public static function materiaGrupoTieneDocente($idMateriaGrupo): bool
    {
        return self::where('id_materia_grupo', $idMateriaGrupo)
            ->where('activo', true)
            ->exists();
    }

    public static function obtenerHorasAsignadasDocente($idDocente, $idGestion): int
    {
        return self::where('id_docente', $idDocente)
            ->where('activo', true)
            ->whereHas('materiaGrupo', function($q) use ($idGestion) {
                $q->where('id_gestion', $idGestion);
            })
            ->sum('hrs_asignadas');
    }

    public static function excedeCargarMaxima($idDocente, $idGestion, $hrsNuevas): array
    {
        $docente = Docente::with('tipoContrato')->find($idDocente);

        if (!$docente || !$docente->tipoContrato) {
            return ['excede' => false, 'mensaje' => ''];
        }

        $hrsActuales = self::obtenerHorasAsignadasDocente($idDocente, $idGestion);
        $hrsTotal = $hrsActuales + $hrsNuevas;
        $hrsMaximas = $docente->tipoContrato->hrs_maximas;

        if ($hrsTotal > $hrsMaximas) {
            return [
                'excede' => true,
                'mensaje' => "El docente excedería la carga máxima. 
                              Actual: {$hrsActuales}hrs, 
                              Nueva: {$hrsNuevas}hrs, 
                              Total: {$hrsTotal}hrs, 
                              Máximo permitido: {$hrsMaximas}hrs"
            ];
        }

        return ['excede' => false, 'mensaje' => ''];
    }
}

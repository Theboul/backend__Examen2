<?php

namespace App\Models\Maestros;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Maestros\Materia;
use App\Models\Maestros\Grupo;
use App\Models\Sistema\Gestion;

class MateriaGrupo extends Model
{
    use HasFactory;

    protected $table = 'materia_grupo';
    protected $primaryKey = 'id_materia_grupo';
    
    // Solo usa fecha_creacion, NO tiene fecha_modificacion
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_materia',
        'id_grupo',
        'id_gestion',
        'observacion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_creacion' => 'datetime',
    ];

    // Relaciones
    public function materia()
    {
        return $this->belongsTo(Materia::class, 'id_materia');
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'id_grupo');
    }

    public function gestion()
    {
        return $this->belongsTo(Gestion::class, 'id_gestion');
    }

    /**
     * Relación con la tabla de asignación (la que usa este modelo).
     */
    public function asignacionDocente()
    {
        // Un MateriaGrupo puede tener UNA asignación (en esta lógica de negocio)
        return $this->hasOne(\App\Models\Horarios\AsignacionDocente::class, 'id_materia_grupo');
    }

    /**
     * Relación con la tabla de asignación, pero SOLO si está activa.
     * Esto es lo que usaremos para filtrar.
     */
    public function asignacionDocenteActiva()
    {
        return $this->hasOne(\App\Models\Horarios\AsignacionDocente::class, 'id_materia_grupo')
                   ->where('activo', true);
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorGestion($query, $idGestion)
    {
        return $query->where('id_gestion', $idGestion);
    }

    // Verificar si ya existe una asignación activa
    public static function existeAsignacion($idMateria, $idGrupo, $idGestion): bool
    {
        return self::where('id_materia', $idMateria)
            ->where('id_grupo', $idGrupo)
            ->where('id_gestion', $idGestion)
            ->where('activo', true)
            ->exists();
    }
}

<?php

namespace App\Models\Maestros;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Maestros\Materia;


class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupo';
    protected $primaryKey = 'id_grupo';
    
    // Usar nombres personalizados para timestamps
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
        'cupos',
        'capacidad_maxima',
        'creado_por',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'cupos' => 'integer',
        'capacidad_maxima' => 'integer',
        'creado_por' => 'integer',
        'fecha_creacion' => 'datetime',
        'fecha_modificacion' => 'datetime',
    ];

    // Relaciones
  
    public function materiaGrupos()
    {
        return $this->hasMany(MateriaGrupo::class, 'id_grupo');
    }

    // Obtener asignaciones activas
    public function asignacionesActivas()
    {
        return $this->hasMany(MateriaGrupo::class, 'id_grupo')->where('activo', true);
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeInactivos($query)
    {
        return $query->where('activo', false);
    }

    public function scopeWithInactive($query)
    {
        return $query;
    }

    // Verificar si tiene cupos disponibles
    public function tieneCuposDisponibles(): bool
    {
        return $this->cupos < $this->capacidad_maxima;
    }

    // Verificar si el grupo puede ser desactivado
    public function puedeDesactivarse(): bool
    {
        // No se puede desactivar si tiene asignaciones activas (materia-grupo)
        return !$this->asignacionesActivas()->exists();
    }
}

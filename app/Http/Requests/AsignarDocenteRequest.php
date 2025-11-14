<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AsignarDocenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El middleware maneja permisos
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $validator->errors()
        ], 422));
    }

    public function rules(): array
    {
        return [
            'id_materia_grupo' => 'required|integer|exists:materia_grupo,id_materia_grupo',

            // 🔥 ESTE ERA EL ERROR
            'id_docente' => 'required|integer|exists:docente,cod_docente',

            'hrs_asignadas' => 'required|integer|min:1|max:40',
        ];
    }

    public function messages(): array
    {
        return [
            'id_materia_grupo.required' => 'Debe seleccionar un materia-grupo',
            'id_materia_grupo.exists' => 'El materia-grupo seleccionado no existe',

            'id_docente.required' => 'Debe seleccionar un docente',
            'id_docente.exists' => 'El docente seleccionado no existe',

            'hrs_asignadas.required' => 'Debe especificar las horas asignadas',
            'hrs_asignadas.integer' => 'Las horas deben ser un número entero',
            'hrs_asignadas.min' => 'Debe asignar al menos 1 hora',
            'hrs_asignadas.max' => 'No puede asignar más de 40 horas',
        ];
    }
}

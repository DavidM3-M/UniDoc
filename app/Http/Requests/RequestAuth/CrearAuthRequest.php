<?php

namespace App\Http\Requests\RequestAuth;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ConstUsuario\Genero;
use App\Constants\ConstUsuario\EstadoCivil;
use App\Constants\ConstUsuario\TipoIdentificacion;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class CrearAuthRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'municipio_id'           => 'required|exists:municipios,id_municipio',
            'tipo_identificacion'    => ['required','string', Rule::in(TipoIdentificacion::all())],// llamo a la constante TipoIdentificacion para obtener los tipos de identificacion
            'numero_identificacion'  => 'required|string|max:50|unique:users',
            'genero'                 => ['nullable','string', Rule::in(Genero::all())],//llamo a la constante genero para obtener los tipos de genero
            'primer_nombre'          => 'required|string|max:100|regex:/^[\pL\pN\s\-]+$/u',
            'segundo_nombre'         => 'nullable|string|max:100|regex:/^[\pL\pN\s\-]+$/u',
            'primer_apellido'        => 'required|string|max:50|regex:/^[\pL\pN\s\-]+$/u',
            'segundo_apellido'       => 'nullable|string|max:50|regex:/^[\pL\pN\s\-]+$/u',
            'fecha_nacimiento'       => 'required|date|before:today',//la fecha de nacimiento no puede ser mayor a la fecha actual
            'estado_civil'           => ['nullable','string', Rule::in(EstadoCivil::all())],//llamo a la constante estadocivil para obtener los tipos de estado civil
            'archivo'                => 'nullable|file|mimes:pdf|max:2048', // Validación del archivo
            'email'                  => 'required|string|email|max:100|unique:users',
            'password'               => ['required', Password::min(8)->mixedCase()->numbers()],
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
           response()->json([
                'success' => false,
                'message' => 'Error en el formulario',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}

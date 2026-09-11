<?php

namespace App\Models\Aspirante;

use Illuminate\Database\Eloquent\Model;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Aspirante\Documento;
use App\Models\Usuario\User;

class ProduccionAcademica extends Model
{
    protected $table = 'produccion_academicas';

    protected $primaryKey = 'id_produccion_academica';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'ambito_divulgacion_id',
        'titulo',
        'numero_autores',
        'medio_divulgacion',
        'fecha_divulgacion',
        // Identificadores con los que `EnlacesConsultaService` arma los enlaces de verificación.
        'doi',
        'issn_isbn',
        'url_publicacion',
    ];

    /**
     * ¿La producción trae algún identificador con el que verificarla?
     *
     * Es la condición del filtro «sin enlace de consulta» de la bandeja del evaluador: sin
     * ninguno de los tres, lo único que queda es buscar por título, que puede no encontrar nada.
     */
    public function tieneIdentificadores(): bool
    {
        return filled($this->doi) || filled($this->issn_isbn) || filled($this->url_publicacion);
    }

    /**
     * Estado del aval de la producción entendida como una unidad.
     *
     * El estado vive en `documentos`, no acá, y una producción puede tener varios archivos.
     * `AvalProduccionService` los decide siempre juntos, así que en los registros nuevos todos
     * comparten estado; los históricos, decididos archivo por archivo desde Apoyo Profesoral,
     * pueden estar mezclados y hay que resolverlos con una precedencia explícita:
     *
     * - `pendiente` gana sobre todo: si algo falta por revisar, la producción no está resuelta.
     * - `aprobado` gana sobre `rechazado`, porque es el que otorga puntaje en
     *   `MotorEscalafonDocenteService::calcularPuntaje()`, que se conforma con un solo documento
     *   aprobado. Reportar «rechazada» algo que sí está sumando puntos sería mentirle al evaluador.
     *
     * @return string pendiente|aprobado|rechazado|sin_documento
     */
    public function estadoAval(): string
    {
        $estados = $this->documentosProduccionAcademica->pluck('estado');

        if ($estados->isEmpty()) {
            return 'sin_documento';
        }

        if ($estados->contains('pendiente')) {
            return 'pendiente';
        }

        return $estados->contains('aprobado') ? 'aprobado' : 'rechazado';
    }

    /**
     * Documento del que se leen el revisor, la fecha y el motivo que se muestran en la ficha.
     *
     * Es el primero que coincide con el estado resuelto por `estadoAval()`: así el motivo del
     * rechazo que ve el evaluador corresponde al estado que le muestra la píldora, y no al de
     * otro archivo de la misma producción.
     */
    public function documentoDecisorio(): ?Documento
    {
        $estado = $this->estadoAval();

        if ($estado === 'sin_documento') {
            return null;
        }

        return $this->documentosProduccionAcademica->firstWhere('estado', $estado);
    }


     // Relación polimórfica con documentos
     public function documentosProduccionAcademica():MorphMany
     {
         return $this->morphMany(Documento::class, 'documentable');
     }

    /**
     * Ámbito de divulgación del catálogo que administra el rol Administrador.
     *
     * La llave foránea es `ambito_divulgacion_id`. Antes apuntaba a `medio_divulgacion`, que es
     * el texto libre que escribe el docente ("Revista UNAM"): la relación nunca resolvía.
     */
    public function ambitoDivulgacionProduccionAcademica():BelongsTo
    {
        return $this->belongsTo(AmbitoDivulgacion::class, 'ambito_divulgacion_id', 'id_ambito_divulgacion');
    }

    // Relación uno a uno con la tabla usuarios
    public function usuarioProduccionAcademica(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}

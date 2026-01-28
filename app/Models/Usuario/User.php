<?php

namespace App\Models\Usuario;

// creamos un modelo llamado User para mediar entre la tabla users y la base de datos
// en este modelo se definen las relaciones con otras tablas
// en este caso la tabla users tiene una relacion de uno a uno con la tabla informacion_contacto

use App\Models\Docente\EvaluacionDocente;
use App\Models\Aspirante\Aptitud;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Aspirante\InformacionContacto;
use App\Models\Ubicacion\Municipio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Aspirante\Eps;
use App\Models\Aspirante\Rut;
use App\Models\Aspirante\Idioma;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Aspirante\Experiencia;
use App\Models\Aspirante\Documento;
use App\Models\Aspirante\Estudio;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\Aspirante\FotoPerfil;
use App\Models\Docente\Puntaje;
use App\Models\TalentoHumano\Contratacion;
use App\Models\TalentoHumano\Postulacion;
use App\Models\Aspirante\CertificacionBancaria;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Aspirante\Pension;

class User extends Authenticatable implements JWTSubject
{
    // utilizamos el trait HasFactory para definir la fabrica de usuarios
    // utilizamos el trait Notifiable para definir las notificaciones de usuarios
    // utilizamos el trait HasRoles para definir los roles de usuarios
    use HasFactory, Notifiable, HasRoles;

    protected $table = 'users';


    /**
     * The attributes that are mass assignable.
     *
     * @var array&lt;int, string>
     */
    // definimos los campos de la tabla users que se pueden llenar
    protected $fillable = [
        'municipio_id',
        'tipo_identificacion',
        'numero_identificacion',
        'genero',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'fecha_nacimiento',
        'estado_civil',
        'email',
        'password',

        // Campos de aval
        'aval_rectoria',
        'aval_rectoria_by',
        'aval_rectoria_at',
        'aval_vicerrectoria',
        'aval_vicerrectoria_by',
        'aval_vicerrectoria_at',
        'aval_coordinador',
        'aval_coordinador_by',
        'aval_coordinador_at',
        'aval_talento_humano',
        'aval_talento_humano_by',
        'aval_talento_humano_at',

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array&lt;int, string>
     */
    // definimos los campos de la tabla users que no se pueden mostrar
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array&lt;string, string>
     */
    // definimos los campos de la tabla users que se deben convertir a un tipo de dato especifico
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aval_rectoria' => 'boolean',
            'aval_vicerrectoria' => 'boolean',
            'aval_coordinador' => 'boolean',
            'aval_talento_humano' => 'boolean',
            'aval_rectoria_at' => 'datetime',
            'aval_vicerrectoria_at' => 'datetime',
            'aval_coordinador_at' => 'datetime',
            'aval_talento_humano_at' => 'datetime',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    // definimos el identificador que se almacenara en la reclamacion del sujeto del JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    // devolver un array de valores clave, que contenga cualquier reclamacion personalizada que se agregara al JWT
    public function getJWTCustomClaims()
    {
        return [
            'rol' => $this->getRoleNames()->first()
        ];
    }

    // relacion de uno a uno con la tabla municipios
    public function municipioUsuarios(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_id', 'id_municipio');
    }




    //relaciones que solo tienen un documento
    // relacion de uno a uno con la tabla eps
    public function epsUsuario(): HasOne
    {
        return $this->hasOne(Eps::class, 'user_id', 'id');
    }

    // relacion de uno a uno con la tabla ruts
    public function rutUsuario(): HasOne
    {
        return $this->hasOne(Rut::class, 'user_id', 'id');
    }

    // relacion de uno a uno con la tabla informacion_contacto
    public function informacionContactoUsuario(): HasOne
    {
        return $this->hasOne(InformacionContacto::class, 'user_id', 'id');
    }

    // relacion de uno a uno con la tabla certificacion_bancarias
    public function certificacionesBancariasUsuario(): HasOne
    {
        return $this->hasOne(CertificacionBancaria::class, 'user_id', 'id');
    }

    // relacion de uno a uno con la tabla pension
    public function pensionUsuario(): HasOne
    {
        return $this->hasOne(Pension::class, 'user_id', 'id');
    }




    // relaciones que tienen varios documentos
    // relacion de uno a muchos con la tabla idiomas
    public function idiomasUsuario(): HasMany
    {
        return $this->hasMany(Idioma::class, 'user_id', 'id');
    }

    // relacion de uno a muchos con la tabla experiencias
    public function experienciasUsuario(): HasMany
    {
        return $this->hasMany(Experiencia::class, 'user_id', 'id');
    }

    // relacion de uno a muchos con la tabla estudios
    public function estudiosUsuario(): HasMany
    {
        return $this->hasMany(Estudio::class, 'user_id', 'id');
    }

    // relacion de uno a muchos con la tabla produccion_academica
    public function produccionAcademicaUsuario(): HasMany
    {
        return $this->hasMany(ProduccionAcademica::class, 'user_id', 'id');
    }





    //relacion polimorfica con la tabla documentos
    public function documentosUser(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    // relacion con la tabla puntajes
    public function puntajeUsuario(): HasOne
    {
        return $this->hasOne(Puntaje::class, 'user_id', 'id');
    }

    // relacion de uno a muchos con la tabla postulaciones
    public function postulacionesUsuario(): HasMany
    {
        return $this->hasMany(Postulacion::class, 'user_id', 'id');
    }

    //relacion de uno a muchos con la tabla contrataciones
    public function contratacionUsuario(): HasOne
    {
        return $this->hasone(Contratacion::class, 'user_id', 'id');
    }

    //relacion de uno a uno con la tabla foto_perfils
    public function fotoPerfilUsuario(): HasOne
    {
        return $this->hasOne(FotoPerfil::class, 'user_id', 'id');
    }

    //relacion de uno a muchos con la tabla aptitudes
    public function aptitudesUsuario(): HasMany
    {
        return $this->hasMany(Aptitud::class, 'user_id', 'id');
    }

    //relacion de uno a uno con la tabla evaluacion_docentes
    public function evaluacionDocenteUsuario(): HasOne
    {
        return $this->hasOne(EvaluacionDocente::class, 'user_id', 'id');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Aspirante\Documento;
use App\Models\Aspirante\Estudio;
use App\Models\Aspirante\Experiencia;
use App\Models\Aspirante\Idioma;
use App\Constants\ConstTalentoHumano\AreasContratacion;
use App\Constants\ConstTalentoHumano\TipoContratacion;
use App\Constants\ConstTalentoHumano\TipoProceso;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\Docente\EvaluacionDocente;
use App\Models\EscalonDocente;
use App\Models\ExamenIdioma;
use App\Models\HistorialEscalonDocente;
use App\Models\Idioma as IdiomaCatalogo;
use App\Models\NivelFormacionAcademica;
use App\Models\PeriodoAscenso;
use App\Models\TalentoHumano\Contratacion;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use App\Models\Usuario\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Trayectoria completa de un docente de demostración, con **todos los documentos aprobados**,
 * para poder recorrer el flujo del escalafón de punta a punta.
 *
 * Sin esto no había forma de probar el escalafón: el motor exige evaluación docente y
 * estudios/idiomas/experiencia/producción **con documento en estado `aprobado`**, y la base de
 * demo no traía ninguno de esos elementos.
 *
 * ## Qué deja montado
 *
 * Con el reglamento nuevo un docente no está en el escalafón hasta que Apoyo Profesoral registra
 * su tramo, así que este seeder también siembra `historial_escalon_docente` y
 * `periodos_ascenso`. Sin ellos la bandeja de ascensos y la hoja de vida salen vacías por más
 * documentos aprobados que haya, que es exactamente lo que le pasaría al front al levantar el
 * proyecto.
 *
 * | Qué                | Valor                                                        |
 * |--------------------|--------------------------------------------------------------|
 * | Tramo cerrado      | Auxiliar, 2015-01-15 → 2020-01-15                            |
 * | Tramo vigente      | Asistente, desde 2020-01-15                                  |
 * | Periodo cerrado    | hace ~2 meses — es contra el que se puede **ejecutar** un ascenso |
 * | Periodo vigente    | en ~4 meses — es contra el que se **proyecta** en la bandeja  |
 *
 * El docente queda **elegible para Asociado por la regla de excepción** (tiene Doctorado
 * aprobado), no por requisitos: como Asistente lleva unos 79 meses contra los 120 que exige
 * Asociado. Se calibró así a propósito, porque es el único estado en el que se puede recorrer el
 * ascenso completo sin inventar una antigüedad de 18 años. Para ver la lista de faltantes basta
 * con rechazarle el documento del Doctorado desde Apoyo Profesoral.
 *
 * Depende de `AmbitoDivulgacionPuntajeSeeder`: sin él todos los ámbitos valen 0 puntos.
 *
 * Es idempotente: se apoya en las claves únicas de cada tabla, así que volver a ejecutarlo no
 * duplica nada.
 */
class DemoTrayectoriaDocenteSeeder extends Seeder
{
    private const EMAIL_DOCENTE = 'docente@universidad.com';
    private const UNIAUTONOMA = 'Corporación Universitaria Autónoma del Cauca';

    public function run(): void
    {
        $docente = User::where('email', self::EMAIL_DOCENTE)->first();

        if (!$docente) {
            $this->command?->warn('No existe ' . self::EMAIL_DOCENTE . '. Ejecuta DocenteSeeder primero.');
            return;
        }

        $this->contratoDePlanta($docente);
        $this->evaluacionDocente($docente);
        $this->estudios($docente);
        $this->idiomas($docente);
        $this->experiencias($docente);
        $this->producciones($docente);
        $this->periodosDeAscenso();
        $this->historialDeEscalafon($docente);

        $this->command?->info('Trayectoria de demostración lista para ' . self::EMAIL_DOCENTE . ' (todos los documentos aprobados).');
    }

    /**
     * Dos periodos de ascenso, porque cada uno sirve para algo distinto y sin los dos no se puede
     * recorrer el flujo:
     *
     * - El **cerrado** es el único contra el que `AscensoEscalafonService::ascender()` deja
     *   ejecutar un ascenso; con el periodo todavía abierto responde 409.
     * - El **vigente** es contra el que se proyecta la bandeja y la consulta del docente
     *   ("así quedaría el expediente al cierre").
     *
     * Las fechas son relativas a hoy a propósito: fijarlas en el calendario haría que el seeder
     * dejara de servir en cuanto pasara ese día.
     */
    private function periodosDeAscenso(): void
    {
        $cerrado = Carbon::now()->subMonths(2)->startOfDay();

        PeriodoAscenso::updateOrCreate(
            ['nombre' => 'Ascensos de demostración (cerrado)'],
            ['fecha_cierre' => $cerrado->toDateString(), 'cerrado_en' => $cerrado]
        );

        PeriodoAscenso::updateOrCreate(
            ['nombre' => 'Ascensos de demostración (vigente)'],
            ['fecha_cierre' => Carbon::now()->addMonths(4)->toDateString(), 'cerrado_en' => null]
        );
    }

    /**
     * Los tramos del docente en el escalafón.
     *
     * Es lo que convierte a un usuario con documentos aprobados en alguien que existe para el
     * escalafón: sin un tramo abierto el motor no tiene contra qué medir la antigüedad ni desde
     * cuándo contar la producción, y devuelve "no ha ingresado al escalafón" con todo en cero.
     *
     * El tramo de Auxiliar arranca el mismo día que la experiencia UniAutónoma para que los meses
     * queden respaldados: el motor solo acredita el tiempo que el certificado cubre.
     */
    private function historialDeEscalafon(User $docente): void
    {
        $tramos = [
            ['escalon' => 'Auxiliar',  'desde' => '2015-01-15', 'hasta' => '2020-01-15'],
            ['escalon' => 'Asistente', 'desde' => '2020-01-15', 'hasta' => null],
        ];

        foreach ($tramos as $datos) {
            $escalon = EscalonDocente::where('nombre', $datos['escalon'])->first();

            if (!$escalon) {
                $this->command?->warn("No se encontró el escalón «{$datos['escalon']}»; se omite ese tramo.");
                continue;
            }

            HistorialEscalonDocente::updateOrCreate(
                [
                    'user_id' => $docente->id,
                    'escalon_id' => $escalon->id_escalon,
                    'desde' => $datos['desde'],
                ],
                [
                    'hasta' => $datos['hasta'],
                    'via' => HistorialEscalonDocente::VIA_INGRESO,
                    'motivo' => 'Tramo de demostración generado por DemoTrayectoriaDocenteSeeder.',
                ]
            );
        }

        // Caché denormalizada que normalmente escribe `AscensoEscalafonService`. Los listados la
        // leen, así que dejarla desalineada haría ver al docente en una categoría que no tiene.
        $docente->puntajeUsuario()->updateOrCreate(
            ['user_id' => $docente->id],
            ['categoria_lograda' => 'Asistente', 'puntaje_total' => 0]
        );
    }

    /**
     * Contrato de planta, que es lo que mete al docente al escalafón: `ContratacionObserver` escucha
     * esta escritura y le crea el tramo de ingreso. El motor ya no mira el contrato para la
     * antigüedad —esa sale de las experiencias `es_uniautonoma` aprobadas—, pero sin él este docente
     * no estaría en el escalafón y las pantallas de Talento Humano saldrían vacías.
     *
     * Los valores salen de las constantes y no de literales. Escribiéndolos a mano se desviaron dos
     * veces de lo que acepta `CrearContratacionRequest` (`Rule::in`): `tipo_contrato` quedó en
     * minúscula y `area` con tilde, dos valores que la API habría rechazado y que este seeder colaba
     * por escribir directo al modelo, saltándose la validación.
     */
    private function contratoDePlanta(User $docente): void
    {
        Contratacion::updateOrCreate(
            ['user_id' => $docente->id],
            [
                'tipo_contrato' => TipoContratacion::PLANTA,
                'tipo_proceso' => TipoProceso::CONTRATACION,
                'area' => AreasContratacion::FACULTAD_DE_INGENIERIA,
                'fecha_inicio' => '2015-01-15',
                'fecha_fin' => '2030-12-31',
                'valor_contrato' => 6500000,
                'observaciones' => 'Contrato de demostración generado por DemoTrayectoriaDocenteSeeder.',
            ]
        );
    }

    /** Los tres escalones con requisitos exigen `evaluacion_minima` = 4.0. */
    private function evaluacionDocente(User $docente): void
    {
        EvaluacionDocente::updateOrCreate(
            ['user_id' => $docente->id],
            [
                'promedio_evaluacion_docente' => 4.5,
                'estado_evaluacion_docente' => 'asignada',
                'fecha_asignacion' => Carbon::parse('2026-02-10'),
            ]
        );
    }

    /**
     * Pregrado, Maestría y Doctorado. El Doctorado es el que habilita Asociado y Titular; los
     * otros dos están para que la lista del docente se vea poblada y realista.
     */
    private function estudios(User $docente): void
    {
        $estudios = [
            [
                'nivel' => 'Universitario',
                'titulo_estudio' => 'Ingeniero de Sistemas',
                'institucion' => 'Universidad del Cauca',
                'fecha_inicio' => '2003-02-03',
                'fecha_fin' => '2008-11-28',
                'fecha_graduacion' => '2008-12-12',
            ],
            [
                'nivel' => 'Maestría',
                'titulo_estudio' => 'Magíster en Ingeniería de Sistemas y Computación',
                'institucion' => 'Universidad de los Andes',
                'fecha_inicio' => '2010-01-18',
                'fecha_fin' => '2012-06-15',
                'fecha_graduacion' => '2012-07-20',
            ],
            [
                'nivel' => 'Doctorado',
                'titulo_estudio' => 'Doctor en Ingeniería Telemática',
                'institucion' => 'Universidad Politécnica de Valencia',
                'fecha_inicio' => '2013-09-02',
                'fecha_fin' => '2017-05-30',
                'fecha_graduacion' => '2017-06-23',
            ],
        ];

        foreach ($estudios as $datos) {
            // `tipo_estudio` guarda el nombre y `nivel_formacion_academica_id` la FK: el motor
            // compara por orden de catálogo y cae al nombre solo si no hay FK.
            $nivel = NivelFormacionAcademica::where('nivel_formacion', $datos['nivel'])->first();

            $estudio = Estudio::firstOrCreate(
                [
                    'user_id' => $docente->id,
                    'tipo_estudio' => $datos['nivel'],
                    'titulo_estudio' => $datos['titulo_estudio'],
                    'institucion' => $datos['institucion'],
                ],
                [
                    'nivel_formacion_academica_id' => $nivel?->id_nivel_formacion_academica,
                    'graduado' => 'Si',
                    'titulo_convalidado' => 'No',
                    'fecha_inicio' => $datos['fecha_inicio'],
                    'fecha_fin' => $datos['fecha_fin'],
                    'fecha_graduacion' => $datos['fecha_graduacion'],
                ]
            );

            $this->documentoAprobado($estudio, 'Estudios');
        }
    }

    /**
     * Inglés B2 acreditado con IELTS 6.5.
     *
     * El nivel no se declara a mano: se guarda el puntaje y se toma el nivel del rango que el
     * Administrador configuró para ese examen, igual que hace el formulario del docente.
     */
    private function idiomas(User $docente): void
    {
        $ingles = IdiomaCatalogo::firstOrCreate(['nombre_idioma' => 'Inglés'], ['activo' => true]);

        $examen = ExamenIdioma::where('idioma_catalogo_id', $ingles->id_idioma_catalogo)
            ->whereHas('rangos')
            ->orderBy('id_examen_idioma')
            ->first();

        if (!$examen) {
            $this->command?->warn('El catálogo de Inglés no tiene ningún examen con rangos. Ejecuta CatalogoIdiomaSeeder o cárgalo desde Catálogos → Idiomas.');
            return;
        }

        $puntaje = 6.5;
        $nivel = $examen->rangos()
            ->where('puntaje_min', '<=', $puntaje)
            ->where('puntaje_max', '>=', $puntaje)
            ->value('nivel_mcer');

        if ($nivel === null) {
            // El examen del catálogo no cubre 6.5: se toma el nivel del rango más alto para no
            // dejar el idioma sin nivel (el motor lo necesita para el requisito MCER).
            $nivel = $examen->rangos()->orderByDesc('puntaje_max')->value('nivel_mcer') ?? 'B2';
            $puntaje = null;
        }

        $idioma = Idioma::firstOrCreate(
            [
                'user_id' => $docente->id,
                'idioma' => $ingles->nombre_idioma,
                'institucion_idioma' => $examen->nombre_examen,
                'fecha_certificado' => '2025-03-20',
            ],
            [
                'idioma_catalogo_id' => $ingles->id_idioma_catalogo,
                'examen_idioma_id' => $examen->id_examen_idioma,
                'puntaje_obtenido' => $puntaje,
                'nivel' => $nivel,
            ]
        );

        $this->documentoAprobado($idioma, 'Idiomas');
    }

    /**
     * Once años en la Uniautónoma (132 meses) más una experiencia externa.
     *
     * Solo la marcada `es_uniautonoma` con documento aprobado cuenta para la antigüedad del
     * escalafón; la externa está para que se vea la diferencia en la tarjeta.
     */
    private function experiencias(User $docente): void
    {
        $experiencias = [
            [
                'tipo_experiencia' => 'Docencia universitaria',
                'institucion_experiencia' => self::UNIAUTONOMA,
                'cargo' => 'Docente de planta',
                'es_uniautonoma' => true,
                'trabajo_actual' => 'Si',
                'intensidad_horaria' => 40,
                'meses_trabajados' => 132,
                'fecha_inicio' => '2015-01-15',
                'fecha_finalizacion' => null,
                'fecha_expedicion_certificado' => '2026-01-20',
            ],
            [
                'tipo_experiencia' => 'Experiencia profesional',
                'institucion_experiencia' => 'Sistemas del Cauca S.A.S.',
                'cargo' => 'Ingeniero de software',
                'es_uniautonoma' => false,
                'trabajo_actual' => 'No',
                'intensidad_horaria' => 48,
                'meses_trabajados' => 30,
                'fecha_inicio' => '2009-02-01',
                'fecha_finalizacion' => '2011-08-31',
                'fecha_expedicion_certificado' => '2011-09-15',
            ],
        ];

        foreach ($experiencias as $datos) {
            $experiencia = Experiencia::firstOrCreate(
                [
                    'user_id' => $docente->id,
                    'tipo_experiencia' => $datos['tipo_experiencia'],
                    'institucion_experiencia' => $datos['institucion_experiencia'],
                    'cargo' => $datos['cargo'],
                    'fecha_inicio' => $datos['fecha_inicio'],
                ],
                collect($datos)->except(['tipo_experiencia', 'institucion_experiencia', 'cargo', 'fecha_inicio'])->all()
            );

            $this->documentoAprobado($experiencia, 'Experiencias');
        }
    }

    /**
     * Cinco producciones, cada una con el ámbito que de verdad le corresponde.
     *
     * El ámbito no se elige por puntaje sino por nombre (producto + ámbito), porque es lo que se
     * lee en la tarjeta: un artículo en revista indexada tiene que decir "Ensayo o artículo /
     * Revista tipo A1", no el ámbito mejor puntuado que hubiera disponible.
     *
     * Con el puntaje que trae `AmbitoDivulgacionPuntajeSeeder` esto suma 35 puntos: por encima
     * del mínimo de Asociado (30) y por debajo del de Titular (60), de modo que la pantalla
     * muestra a la vez la categoría lograda y lo que falta para la siguiente.
     */
    private function producciones(User $docente): void
    {
        $plantillas = [
            [
                'titulo' => 'Un modelo de enrutamiento adaptativo para redes de sensores',
                'medio' => 'Revista Iberoamericana de Telemática',
                'autores' => 2,
                'fecha' => '2019-04-18',
                'producto' => 'Ensayo o artículo',
                'ambito' => 'Revista tipo A1',
            ],
            [
                'titulo' => 'Evaluación de algoritmos de consenso en blockchain permisionada',
                'medio' => 'IEEE Latin America Transactions',
                'autores' => 3,
                'fecha' => '2021-08-05',
                'producto' => 'Ensayo o artículo',
                'ambito' => 'Revista tipo A2',
            ],
            [
                'titulo' => 'Arquitectura de microservicios para plataformas académicas',
                'medio' => 'Revista Colombiana de Computación',
                'autores' => 1,
                'fecha' => '2022-11-30',
                'producto' => 'Ensayo o artículo',
                'ambito' => 'Revista tipo B',
            ],
            [
                'titulo' => 'Detección de anomalías en tráfico de red con aprendizaje profundo',
                'medio' => 'Congreso Colombiano de Computación',
                'autores' => 4,
                'fecha' => '2023-10-12',
                'producto' => 'Ponencia o plenaria',
                'ambito' => 'Congreso nacional',
            ],
            [
                'titulo' => 'Programación orientada a objetos aplicada a la ingeniería',
                'medio' => 'Editorial Uniautónoma',
                'autores' => 2,
                'fecha' => '2024-06-21',
                'producto' => 'Libro',
                'ambito' => 'De texto - Difusión nacional',
            ],
        ];

        $sembradas = 0;
        $puntos = 0;

        foreach ($plantillas as $plantilla) {
            // Comparación con TRIM: varios nombres del catálogo traen espacios al final
            // ("Ensayo o artículo ") heredados del CSV de importación.
            $ambito = AmbitoDivulgacion::whereRaw('TRIM(nombre_ambito_divulgacion) = ?', [$plantilla['ambito']])
                ->whereHas(
                    'productoAcademicoAmbitoDivulgacion',
                    fn ($q) => $q->whereRaw('TRIM(nombre_producto_academico) = ?', [$plantilla['producto']])
                )
                ->first();

            if (!$ambito) {
                $this->command?->warn("No se encontró el ámbito «{$plantilla['producto']} / {$plantilla['ambito']}»; se omite esa producción.");
                continue;
            }

            $produccion = ProduccionAcademica::firstOrCreate(
                [
                    'user_id' => $docente->id,
                    'titulo' => $plantilla['titulo'],
                    'medio_divulgacion' => $plantilla['medio'],
                    'fecha_divulgacion' => $plantilla['fecha'],
                ],
                [
                    'ambito_divulgacion_id' => $ambito->id_ambito_divulgacion,
                    'numero_autores' => $plantilla['autores'],
                ]
            );

            // Un registro anterior pudo quedar con otro ámbito (por ejemplo, el que asignaba la
            // primera versión de este seeder por puntaje): se corrige.
            if ($produccion->ambito_divulgacion_id !== $ambito->id_ambito_divulgacion) {
                $produccion->update(['ambito_divulgacion_id' => $ambito->id_ambito_divulgacion]);
            }

            $this->documentoAprobado($produccion, 'ProduccionAcademica');

            $sembradas++;
            $puntos += (int) $ambito->puntaje;
        }

        $this->command?->info("Producción académica sembrada: {$sembradas} productos, {$puntos} puntos.");
    }

    /**
     * Crea (o aprueba) el documento del registro y escribe un PDF real en el disco `public`.
     *
     * El archivo tiene que existir de verdad: la hoja de vida y las pantallas de verificación de
     * Apoyo Profesoral enlazan a `storage/...` y sin archivo el enlace da 404.
     */
    private function documentoAprobado($modelo, string $carpeta): Documento
    {
        $documento = Documento::firstOrNew([
            'documentable_id' => $modelo->getKey(),
            'documentable_type' => get_class($modelo),
        ]);

        if (!$documento->archivo || !Storage::disk('public')->exists($documento->archivo)) {
            $ruta = "documentos/{$carpeta}/" . Str::uuid() . '.pdf';
            Storage::disk('public')->put($ruta, $this->pdfDeMuestra($carpeta));
            $documento->archivo = $ruta;
        }

        $documento->estado = 'aprobado';
        $documento->motivo_rechazo = null;
        $documento->save();

        return $documento;
    }

    /**
     * PDF mínimo válido de una página con un texto. Se genera a mano para no depender de
     * ninguna librería en el seeder.
     */
    private function pdfDeMuestra(string $carpeta): string
    {
        $texto = "Documento de demostracion - {$carpeta}";

        $objetos = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $flujo = "BT /F1 14 Tf 72 700 Td ({$texto}) Tj ET";
        $objetos[] = "5 0 obj\n<< /Length " . strlen($flujo) . " >>\nstream\n{$flujo}\nendstream\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $posiciones = [];

        foreach ($objetos as $objeto) {
            $posiciones[] = strlen($pdf);
            $pdf .= $objeto;
        }

        $inicioXref = strlen($pdf);
        $total = count($objetos) + 1;

        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";
        foreach ($posiciones as $posicion) {
            $pdf .= sprintf("%010d 00000 n \n", $posicion);
        }

        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$inicioXref}\n%%EOF";

        return $pdf;
    }
}

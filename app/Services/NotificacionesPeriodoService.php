<?php

namespace App\Services;

use App\Jobs\EnviarNotificacionJob;
use App\Models\NotificacionEnviada;
use App\Models\PeriodoAscenso;
use App\Models\Usuario\User;

class NotificacionesPeriodoService
{
    private const MAX_FALTANTES_PARA_AVISAR = 2;

    public function __construct(private readonly MotorEscalafonDocenteService $motor) {}

    /**
     * C7 — le dice al docente que no ascendio y exactamente que le falto.
     *
     * **Solo a quien estuvo cerca**: uno o dos criterios sin cumplir. A quien le faltaban cuatro,
     * este correo no le aporta nada accionable y se lee como una mala noticia masiva. El limite es
     * la unica parte de esta notificacion que es criterio y no calculo, y por eso esta aqui a la
     * vista en vez de escondido en el motor.
     *
     * El desglose ya lo produce `evaluarAscenso()`; hasta ahora solo se veia si el docente entraba
     * a mirar la pantalla.
     */
    private function avisarNoAlcanzado(User $docente, PeriodoAscenso $periodo, array $evaluacion): void
    {
        $faltantes = $evaluacion['faltantes'] ?? [];

        if (count($faltantes) === 0 || count($faltantes) > self::MAX_FALTANTES_PARA_AVISAR) {
            return;
        }

        $lineas = array_map(fn ($f) => [
            'criterio' => ucfirst(str_replace('_', ' ', (string) ($f['campo'] ?? 'Requisito'))),
            'detalle'  => trim(
                (($f['actual'] ?? null) !== null ? "Tienes {$f['actual']} de {$f['requerido']}. " : '')
                . (string) ($f['mensaje'] ?? '')
            ),
        ], $faltantes);

        $this->encolar(
            "escalafon.no-ascendio:periodo:{$periodo->id_periodo_ascenso}:user:{$docente->id}",
            'escalafon.no-ascendio',
            'ascensoNoAlcanzado',
            [
                $periodo->nombre,
                $periodo->fecha_cierre->format('d/m/Y'),
                $evaluacion['escalon_vigente'] ?? 'tu categoria actual',
                $evaluacion['escalon_objetivo'] ?? null,
                $lineas,
                [],
            ],
            $docente->id
        );
    }

    /**
     * C11 — avisa a Apoyo Profesoral de que hay elegibles esperando.
     *
     * El conteo se hace aqui y no en el correo para que el mensaje llegue con el numero ya resuelto:
     * calcularlo dentro del envio obligaria a repetir la evaluacion por cada destinatario.
     */
    public function avisarCierre(PeriodoAscenso $periodo): void
    {
        if (!$periodo->fecha_cierre->copy()->endOfDay()->isPast()) {
            return;
        }
        $porCategoria = [];
        $elegibles = 0;
        $noElegibles = 0;

        // El desglose por nombre. `avisarNoAlcanzado` ya calculaba los motivos, pero solo para el
        // correo del docente: Apoyo Profesoral recibia el numero y nada mas.
        $detalleElegibles = [];
        $detalleNoElegibles = [];

        $docentes = User::role('Docente')
            ->with(['estudiosUsuario.documentosEstudio', 'idiomasUsuario.documentosIdioma', 'experienciasUsuario.documentosExperiencia', 'produccionAcademicaUsuario.documentosProduccionAcademica', 'evaluacionDocenteUsuario', 'historialEscalonUsuario.escalon'])
            ->whereHas('historialEscalonUsuario', fn ($q) => $q->whereNull('hasta')->whereNull('revertido_en'))
            ->get();

        foreach ($docentes as $docente) {
            $evaluacion = $this->motor->evaluarAscenso($docente, $periodo);

            $nombre = trim(($docente->primer_nombre ?? '') . ' ' . ($docente->primer_apellido ?? ''));
            $nombre = $nombre !== '' ? $nombre : ($docente->email ?? "Docente {$docente->id}");

            if (!empty($evaluacion['elegible'])) {
                $elegibles++;
                $objetivo = $evaluacion['escalon_objetivo'] ?? 'sin categoria';
                $porCategoria[$objetivo] = ($porCategoria[$objetivo] ?? 0) + 1;

                $detalleElegibles[] = ['docente' => $nombre, 'objetivo' => $objetivo];
            } else {
                $noElegibles++;

                // Aqui van **todos** los faltantes, sin el limite de dos que aplica al correo del
                // docente: ese limite existe para no darle una mala noticia inaccionable a quien
                // estaba lejos, y quien administra el periodo si necesita el cuadro completo.
                $detalleNoElegibles[] = [
                    'docente' => $nombre,
                    'motivos' => $this->motivosLegibles($evaluacion),
                ];

                $this->avisarNoAlcanzado($docente, $periodo, $evaluacion);
            }
        }

        foreach (User::role('Apoyo Profesoral')->get() as $responsable) {
            $this->encolar(
                "escalafon.periodo-cerrado:periodo:{$periodo->id_periodo_ascenso}:user:{$responsable->id}",
                'escalafon.periodo-cerrado',
                'periodoCerradoConElegibles',
                [
                    $periodo->nombre, $periodo->fecha_cierre->format('d/m/Y'),
                    $elegibles, $porCategoria, $noElegibles,
                    $detalleElegibles, $detalleNoElegibles,
                ],
                $responsable->id
            );
        }
    }


    /**
     * Convierte el desglose del motor en frases cortas para el correo.
     *
     * `evaluarAscenso()` devuelve el criterio, lo que el docente tiene y lo que se le exige. Se
     * arma «Puntaje: 21 de 30» en vez de volcar el arreglo crudo.
     */
    private function motivosLegibles(array $evaluacion): array
    {
        return array_values(array_map(function ($f) {
            $criterio = ucfirst(str_replace('_', ' ', (string) ($f['campo'] ?? 'Requisito')));

            if (($f['actual'] ?? null) !== null && ($f['requerido'] ?? null) !== null) {
                return "{$criterio}: {$f['actual']} de {$f['requerido']}";
            }

            $mensaje = trim((string) ($f['mensaje'] ?? ''));

            return $mensaje !== '' ? "{$criterio}: {$mensaje}" : $criterio;
        }, $evaluacion['faltantes'] ?? []));
    }

    private function encolar(string $clave, string $tipo, string $metodo, array $args, int $userId): void
    {
        if (NotificacionEnviada::where('clave', $clave)->whereNotNull('enviado_en')->exists()) {
            return;
        }
        EnviarNotificacionJob::dispatch($clave, $tipo, $metodo, $args, $userId)->afterCommit();
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de producción académica: completa las modalidades que faltaban y fija el puntaje de
 * cada ámbito de divulgación.
 *
 * ## Por qué se reescribió
 *
 * La versión anterior identificaba los ámbitos por su **id numérico** —`[1, 20, 21, 25, 27...]`—,
 * una lista escrita a mano. Basta con que el CSV cambie de orden para que los puntajes se peguen
 * al ámbito equivocado, y eso fue exactamente lo que pasó: «Artículo corto → Revista A1» quedó en
 * 0 mientras A2 valía 6, con A1 siendo la categoría más alta de Publindex. Además solo cubría 40
 * de los 82 ámbitos: los otros 42 se quedaban en 0, y un ámbito en 0 no aporta nada al escalafón.
 *
 * Aquí todo se referencia por **nombre de producto + nombre de ámbito**, que es lo único
 * inequívoco: «Revista tipo A1» existe bajo cinco productos distintos y significa algo distinto
 * en cada uno.
 *
 * ## La escala
 *
 * Se conserva el techo de **10 puntos** que ya usaba el catálogo, porque los mínimos de
 * `escalones_docente` (20 para Asistente, 30 para Asociado, 60 para Titular) están calibrados
 * contra él. Lo que se toma del Decreto 1279 de 2002 son las **proporciones** del artículo 10,
 * no las cifras absolutas —el decreto llega hasta 25 puntos por patente—:
 *
 *   - Artículo completo («full paper»): el valor pleno del nivel de la revista.
 *   - Comunicación corta: **60%** de ese valor.
 *   - Reporte de caso, revisión de tema, carta al editor y editorial: **30%**.
 *
 * Esa regla del 30% es la que corrige el otro error que había: «Carta al editor → Revista A1»
 * valía 10, lo mismo que un artículo de investigación completo.
 *
 * ## Qué se añadió
 *
 * Cotejando con la enumeración del decreto faltaban tres modalidades que sí nombra —editorial,
 * publicaciones impresas universitarias y dirección de tesis— y dos figuras de propiedad
 * industrial que Minciencias cuenta aparte de la patente: modelo de utilidad y diseño industrial.
 *
 * ## Esto es dato, no regla
 *
 * Ningún puntaje de aquí es una decisión institucional cerrada: el Administrador los edita desde
 * Catálogos → Producción académica. El seeder solo escribe sobre los ámbitos que siguen en 0, así
 * que volver a ejecutarlo nunca pisa un ajuste ya hecho a mano.
 */
class AmbitoDivulgacionPuntajeSeeder extends Seeder
{
    /**
     * Modalidades que el Decreto 1279 nombra y que no estaban en el catálogo.
     *
     * Estructura: producto => [ámbito => puntaje].
     */
    private const MODALIDADES_NUEVAS = [
        // Artículo 10, literal a): el decreto agrupa «cartas al editor y editoriales» en la misma
        // categoría del 30%. La carta ya existía; la editorial no.
        'Editorial' => [
            'Revista tipo A1' => 3,
            'Revista tipo A2' => 2,
            'Revista tipo B'  => 1,
            'Revista tipo C'  => 1,
        ],

        // Artículo 20, literal d).
        'Publicaciones impresas universitarias' => [
            'Difusión internacional' => 6,
            'Difusión nacional'      => 3,
            'Difusión regional'      => 1,
        ],

        // Artículo 20, literal h). El nivel del programa marca la diferencia de exigencia.
        'Dirección de tesis' => [
            'Doctorado' => 6,
            'Maestría'  => 3,
            'Pregrado'  => 1,
        ],

        // Propiedad industrial que Minciencias reconoce aparte de la patente de invención. Van por
        // debajo de ella porque exigen menos altura inventiva.
        'Modelo de utilidad' => [
            'Registro concedido' => 6,
        ],
        'Diseño industrial' => [
            'Registro concedido' => 3,
        ],
    ];

    /**
     * Puntaje de cada ámbito ya existente, por nombre de producto y de ámbito.
     *
     * Los productos llevan el nombre exacto que tienen en la tabla, espacios finales incluidos:
     * «Ensayo o artículo » los trae del CSV original y cambiarlos aquí rompería la coincidencia.
     */
    private const PUNTAJES = [
        // --- Artículo 10, literal a): revistas indexadas u homologadas -----------------------
        'Ensayo o artículo ' => [
            'Revista tipo A1' => 10,
            'Revista tipo A2' => 6,
            'Revista tipo B'  => 3,
            'Revista tipo C'  => 1,
        ],
        'Artículo corto' => [   // comunicación corta: 60%
            'Revista tipo A1' => 6,
            'Revista tipo A2' => 4,
            'Revista tipo B'  => 2,
            'Revista tipo C'  => 1,
        ],
        'Reportes de caso' => [  // 30%
            'Revista tipo A1' => 3,
            'Revista tipo A2' => 2,
            'Revista tipo B'  => 1,
            'Revista tipo C'  => 1,
        ],
        'Revisión de tema' => [  // 30%
            'Revista tipo A1' => 3,
            'Revista tipo A2' => 2,
            'Revista tipo B'  => 1,
            'Revista tipo C'  => 1,
        ],
        'Carta al editor' => [   // 30%
            'Revista tipo A1' => 3,
            'Revista tipo A2' => 2,
            'Revista tipo B'  => 1,
            'Revista tipo C'  => 1,
        ],

        // --- Literales c), d) y e): libros ---------------------------------------------------
        // El decreto da más peso al libro de investigación (20) que al de texto y al de ensayo
        // (15 cada uno); la difusión modula dentro de cada tipo.
        'Libro' => [
            'De investigación - Difusión internacional' => 10,
            'De investigación - Difusión nacional'      => 8,
            'De investigación - Difusión regional'      => 6,
            'De texto - Difusión internacional'         => 8,
            'De texto - Difusión nacional'              => 6,
            'De texto - Difusión regional'              => 4,
            'De ensayo - Difusión internacional'        => 8,
            'De ensayo - Difusión nacional'             => 6,
            'De ensayo - Difusión regional'             => 4,
        ],
        // Un capítulo no es un libro: se le da el 60%, igual que a la comunicación corta.
        'Capítulo de libro' => [
            'Libro de texto - Difusión internacional' => 6,
            'Libro de texto - Difusión nacional'      => 4,
            'Libro de texto - Difusión regional'      => 2,
        ],

        // --- Literales f), g), h), j), k) ----------------------------------------------------
        'Premio' => [
            'Internacional'     => 10,
            'Nacional'          => 6,
            'Regional - Local'  => 3,
            'Premios'           => 3,
        ],
        'Patente de invención' => [
            'Patente' => 10,
        ],
        'Traducción' => [
            'De libro'              => 6,
            'Publicada en artículo' => 3,
        ],
        'Producción técnica' => [
            'Producción técnica de innovación tecnológica' => 10,
            'Producción técnica de adaptación tecnológica' => 6,
        ],
        'Producción de software' => [
            'Producción software científica' => 10,
            'Producción software - Técnica'  => 6,
        ],

        // --- Literal b): audiovisuales -------------------------------------------------------
        // El decreto pone el documental por debajo del científico (80% de su valor).
        'Video, cinematográfico o fonográfico' => [
            'Carácter científico - Difusión internacional'       => 10,
            'Carácter científico - Difusión nacional'            => 6,
            'Carácter científico - Difusión regional o local'    => 3,
            'Carácter documental - Difusión internacional'       => 8,
            'Carácter documental - Difusión nacional'            => 4,
            'Carácter documental - Difusión regional o local'    => 2,
        ],

        // --- Literal i): obras artísticas ----------------------------------------------------
        // Original > interpretación > complementaria, que es el orden del decreto.
        'Obra de creación artística' => [
            'Obra de creación original artística internacional' => 10,
            'Obra de creación original artística nacional'      => 7,
            'Interpretación impacto internacional'              => 7,
            'Interpretación impacto nacional'                   => 4,
            'Obra de creación complementaria internacional'     => 6,
            'Obra de creación complementaria nacional'          => 4,
            'Publicada en artículo'                             => 3,
        ],

        // --- Artículo 20: bonificaciones -----------------------------------------------------
        'Estudios Pos-Doctoral' => [
            'Estudio posdoctoral' => 10,
        ],
        'Ponencia o plenaria' => [
            'Congreso internacional' => 6,
            'Congreso nacional'      => 3,
            'Congreso regional'      => 1,
        ],
        'Reseña critica' => [
            'Reseña crítica' => 2,
        ],
        // Evaluar la producción de otro es un servicio a la comunidad académica, no producción
        // propia: por eso queda muy por debajo de un artículo.
        'Evaluación como par' => [
            'Evaluación como par' => 2,
        ],

        // --- Creación artística y audiovisual propia de los programas de la institución -------
        'Obra de creación en diseño o comunicación visual' => [
            'Obra de creación en diseño o comunicación visual' => 6,
        ],
        'Curaduría artística' => [
            'Nivel internacional' => 6,
            'Nivel nacional'      => 3,
            'Nivel local'         => 1,
        ],
        'Residencia artística' => [
            'Residencia artística' => 3,
        ],
        'Creación o composición musical' => [
            'Creación o composición musical' => 6,
        ],
        'Arreglo musical' => [
            'Arreglo musical' => 3,
        ],
        'Concierto' => [
            'Solista' => 3,
        ],
        'Recital' => [
            'Recital' => 3,
        ],
        'Comunicación y procesos organizacionales - comunicacion social' => [
            'Por producto de impacto internacional'    => 6,
            'Por producto de impacto nacional'         => 3,
            'Por producto de impacto regional - local' => 1,
        ],
        'Investigación-creación en comunicación social' => [
            'De impacto y circulación internacional'    => 6,
            'De impacto y circulación nacional'         => 3,
            'De impacto y circulación regional - local' => 1,
        ],

        // --- Distinciones de trabajo de grado -------------------------------------------------
        'Distinción de trabajo de grado' => [
            'De posgrado laureado'             => 6,
            'De posgrado - Mención honorífica' => 3,
            'De pregrado laureado'             => 3,
            'De pregrado - Mención honorífica' => 1,
        ],
    ];

    public function run(): void
    {
        $creados = $this->crearModalidadesNuevas();
        $asignados = $this->asignarPuntajes();

        $enCero = DB::table('ambito_divulgacions')->where('puntaje', 0)->count();

        $this->command?->info(
            "Producción académica: {$creados} ámbitos nuevos, {$asignados} puntajes asignados. "
            . "Siguen en 0: {$enCero}."
        );

        if ($enCero > 0) {
            // Un ámbito en 0 no suma nada al escalafón, así que conviene que se vea.
            $nombres = DB::table('ambito_divulgacions')
                ->where('puntaje', 0)
                ->pluck('nombre_ambito_divulgacion')
                ->implode(', ');

            $this->command?->warn("  Ámbitos sin puntaje: {$nombres}");
        }
    }

    /** Crea los productos y ámbitos que el decreto nombra y no estaban en el catálogo. */
    private function crearModalidadesNuevas(): int
    {
        $creados = 0;

        foreach (self::MODALIDADES_NUEVAS as $producto => $ambitos) {
            $productoId = DB::table('producto_academicos')
                ->where('nombre_producto_academico', $producto)
                ->value('id_producto_academico');

            if ($productoId === null) {
                $productoId = DB::table('producto_academicos')->insertGetId([
                    'nombre_producto_academico' => $producto,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'id_producto_academico');
            }

            foreach ($ambitos as $ambito => $puntaje) {
                $existe = DB::table('ambito_divulgacions')
                    ->where('producto_academico_id', $productoId)
                    ->where('nombre_ambito_divulgacion', $ambito)
                    ->exists();

                if ($existe) {
                    continue;
                }

                DB::table('ambito_divulgacions')->insert([
                    'producto_academico_id'     => $productoId,
                    'nombre_ambito_divulgacion' => $ambito,
                    'puntaje'                   => $puntaje,
                    'activo'                    => true,
                    'created_at'                => now(),
                    'updated_at'                => now(),
                ]);

                $creados++;
            }
        }

        return $creados;
    }

    /**
     * Aplica el puntaje a cada ámbito por nombre.
     *
     * Solo toca los que están en 0: si el Administrador ya ajustó uno desde la interfaz, se
     * respeta su decisión.
     */
    private function asignarPuntajes(): int
    {
        $asignados = 0;

        foreach (self::PUNTAJES as $producto => $ambitos) {
            $productoId = DB::table('producto_academicos')
                ->where('nombre_producto_academico', $producto)
                ->value('id_producto_academico');

            if ($productoId === null) {
                $this->command?->warn("  No existe el producto «{$producto}»; se omite.");
                continue;
            }

            foreach ($ambitos as $ambito => $puntaje) {
                $afectados = DB::table('ambito_divulgacions')
                    ->where('producto_academico_id', $productoId)
                    ->where('nombre_ambito_divulgacion', $ambito)
                    ->where('puntaje', 0)
                    ->update(['puntaje' => $puntaje, 'updated_at' => now()]);

                $asignados += $afectados;
            }
        }

        return $asignados;
    }
}

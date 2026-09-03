<?php

namespace Tests\Feature;

use App\Constants\ConstTalentoHumano\AreasContratacion;
use App\Constants\ConstTalentoHumano\TipoContratacion;
use App\Models\Aspirante\Documento;
use App\Models\Aspirante\Experiencia;
use App\Models\EscalonDocente;
use App\Models\HistorialEscalonBitacora;
use App\Models\HistorialEscalonDocente;
use App\Models\PeriodoAscenso;
use App\Models\TalentoHumano\Contratacion;
use App\Models\Usuario\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas de la sección de escalafón del Administrador.
 *
 * Cubre dos cosas distintas. La primera es que el Administrador alcanza los actos que hasta ahora
 * eran solo de Apoyo Profesoral —periodos, bandeja, ascenso, reversión— sin que eso abra la puerta
 * en sentido contrario: las rutas de `/admin` siguen cerradas a Apoyo Profesoral. La segunda son
 * las dos capacidades que solo tiene el Administrador y que antes no tenía nadie, el ingreso manual
 * y la corrección de tramos.
 *
 * Lo que se protege en la corrección no es el CRUD: es que la cadena de tramos de un docente siga
 * teniendo sentido después. Un solapamiento, dos tramos abiertos o un cierre que saca al docente del
 * escalafón sin dejar rastro son estados que el motor no sabe leer y que degradan al docente en
 * silencio, sin que nada falle.
 */
class EscalafonAdminApiTest extends TestCase
{
    use DatabaseTransactions;

    private const PREFIJO = '/api/admin/escalafon';
    private const PREFIJO_APOYO = '/api/apoyoProfesoral/escalafon';

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function crearUsuario(string $rol, string $prefijo): User
    {
        $uid = uniqid();

        $user = User::create([
            'municipio_id'          => 703,
            'tipo_identificacion'   => 'Cédula de ciudadanía',
            'numero_identificacion' => '79' . substr($uid, -8),
            'primer_nombre'         => 'Test' . $prefijo,
            'primer_apellido'       => 'EscalafonAdmin',
            'fecha_nacimiento'      => '1981-07-09',
            'email'                 => $prefijo . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);

        $user->assignRole($rol);

        return $user;
    }

    private function crearAdmin(): User
    {
        return $this->crearUsuario('Administrador', 'adminesc');
    }

    private function crearDocente(): User
    {
        return $this->crearUsuario('Docente', 'docadmesc');
    }

    /** Contratación de planta vigente: es lo que acredita el ingreso al escalafón. */
    private function contratar(
        User $docente,
        string $tipo = TipoContratacion::PLANTA,
        ?string $fechaInicio = null,
        ?string $fechaFin = null
    ): Contratacion {
        return Contratacion::create([
            'user_id' => $docente->id,
            'tipo_contrato' => $tipo,
            'area' => AreasContratacion::FACULTAD_DE_INGENIERIA,
            'fecha_inicio' => $fechaInicio ?? now()->subYears(5)->toDateString(),
            'fecha_fin' => $fechaFin ?? now()->addYear()->toDateString(),
            'valor_contrato' => 5000000,
        ]);
    }

    private function escalon(string $nombre): EscalonDocente
    {
        return EscalonDocente::where('nombre', $nombre)->firstOrFail();
    }

    private function documento(string $tipo, int $id, string $estado = 'aprobado', string $subidoEn = '2020-01-01'): Documento
    {
        $documento = Documento::create([
            'archivo'           => 'documentos/escadm-' . uniqid() . '.pdf',
            'estado'            => $estado,
            'documentable_id'   => $id,
            'documentable_type' => $tipo,
        ]);

        $documento->forceFill(['created_at' => Carbon::parse($subidoEn)])->save();

        return $documento;
    }

    /**
     * Experiencia UniAutónoma aprobada que respalda la antigüedad.
     *
     * Hace falta explícitamente porque `mesesEnEscalon()` **intersecta** los tramos del historial
     * con los periodos de experiencia documentada: sin ella, corregir el `desde` hacia atrás no da
     * ni un mes y los tests de impacto medirían siempre cero.
     */
    private function experienciaUniautonomaDesde(User $docente, string $desde): Experiencia
    {
        $experiencia = Experiencia::create([
            'user_id'                 => $docente->id,
            'tipo_experiencia'        => 'Docencia universitaria',
            'institucion_experiencia' => 'Universidad Autónoma',
            'es_uniautonoma'          => true,
            'cargo'                   => 'Docente',
            'trabajo_actual'          => 'Si',
            'fecha_inicio'            => $desde,
            'fecha_finalizacion'      => null,
        ]);

        $this->documento(Experiencia::class, $experiencia->id_experiencia);

        return $experiencia;
    }

    /** Crea un tramo directamente, para montar cadenas que los actos no producen por sí solos. */
    private function tramo(User $docente, string $escalon, string $desde, ?string $hasta = null): HistorialEscalonDocente
    {
        return HistorialEscalonDocente::create([
            'user_id' => $docente->id,
            'escalon_id' => $this->escalon($escalon)->id_escalon,
            'desde' => $desde,
            'hasta' => $hasta,
            'via' => HistorialEscalonDocente::VIA_INGRESO,
            'otorgado_por' => null,
        ]);
    }

    // ---------------------------------------------------------------
    // Autorización y acceso a la superficie compartida
    // ---------------------------------------------------------------

    /** El Administrador alcanza los periodos de ascenso, que eran solo de Apoyo Profesoral. */
    public function test_admin_lista_periodos_retorna_200(): void
    {
        $this->actingAs($this->crearAdmin(), 'api')
             ->getJson(self::PREFIJO . '/periodos')
             ->assertStatus(200)
             ->assertJsonPath('status', 'success');
    }

    /** Y puede crearlos. */
    public function test_admin_crea_periodo_retorna_201(): void
    {
        $this->actingAs($this->crearAdmin(), 'api')
             ->postJson(self::PREFIJO . '/periodos', [
                 'nombre' => 'Periodo admin ' . uniqid(),
                 'fecha_cierre' => now()->addMonths(6)->toDateString(),
             ])
             ->assertStatus(201)
             ->assertJsonPath('status', 'success');
    }

    /** La bandeja: es de donde sale el id de tramo que necesitan revertir y corregir. */
    public function test_admin_ve_la_bandeja_de_docentes_retorna_200(): void
    {
        $this->actingAs($this->crearAdmin(), 'api')
             ->getJson(self::PREFIJO . '/docentes')
             ->assertStatus(200)
             ->assertJsonPath('status', 'success');
    }

    /**
     * El test que protege la separación de las dos clases de controlador: compartir las rutas de
     * ascenso con el Admin no abre las de Apoyo Profesoral al Admin ni al revés.
     */
    public function test_apoyo_profesoral_en_rutas_de_admin_retorna_403(): void
    {
        $apoyo = $this->crearUsuario('Apoyo Profesoral', 'apoyoadm');

        $this->actingAs($apoyo, 'api')
             ->getJson(self::PREFIJO . '/docentes')
             ->assertStatus(403);
    }

    /** Las correcciones no existen bajo el prefijo de Apoyo Profesoral. */
    public function test_las_correcciones_no_estan_expuestas_en_apoyo_profesoral(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO_APOYO . "/docentes/{$docente->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
                 'desde' => '2020-01-01',
                 'motivo' => 'No debería existir esta ruta.',
             ])
             ->assertStatus(404);
    }

    public function test_docente_en_rutas_de_admin_retorna_403(): void
    {
        $this->actingAs($this->crearDocente(), 'api')
             ->getJson(self::PREFIJO . '/docentes')
             ->assertStatus(403);
    }

    public function test_sin_autenticar_retorna_401(): void
    {
        $this->getJson(self::PREFIJO . '/docentes')->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Ingreso manual
    // ---------------------------------------------------------------

    /** El caso central: el docente que llega con una categoría ya reconocida. */
    public function test_ingreso_manual_entra_en_el_escalon_indicado_y_no_en_el_inicial(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);

        // La contratación ya disparó el ingreso automático a Auxiliar: hay que deshacerlo para
        // poder registrar el ingreso que corresponde.
        HistorialEscalonDocente::where('user_id', $docente->id)->delete();

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Asociado')->id_escalon,
                 'desde' => '2018-02-01',
                 'motivo' => 'Categoría homologada al vincularse desde otra universidad.',
             ])
             ->assertStatus(201)
             ->assertJsonPath('data.escalon', 'Asociado')
             ->assertJsonPath('data.desde', '2018-02-01');

        $tramo = HistorialEscalonDocente::where('user_id', $docente->id)->firstOrFail();
        $this->assertSame($this->escalon('Asociado')->id_escalon, $tramo->escalon_id);
    }

    /** La firma es lo que distingue este ingreso del automático, que deja `otorgado_por` en null. */
    public function test_ingreso_manual_queda_firmado_por_el_admin(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);
        HistorialEscalonDocente::where('user_id', $docente->id)->delete();

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
                 'desde' => '2019-01-01',
                 'motivo' => 'El ingreso automático no se ejecutó.',
             ])
             ->assertStatus(201);

        $tramo = HistorialEscalonDocente::where('user_id', $docente->id)->firstOrFail();

        $this->assertSame($admin->id, $tramo->otorgado_por);
        $this->assertSame(HistorialEscalonDocente::VIA_INGRESO, $tramo->via);
    }

    /** Sigue exigiendo contratación de planta: el escalafón es de los docentes de planta. */
    public function test_ingreso_manual_sin_contratacion_de_planta_retorna_409(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
                 'desde' => '2019-01-01',
                 'motivo' => 'Sin contrato cargado.',
             ])
             ->assertStatus(409)
             ->assertJsonPath('message', fn ($m) => str_contains($m, 'planta'));
    }

    /** Una contratación de cátedra tampoco acredita el ingreso. */
    public function test_ingreso_manual_con_contratacion_de_catedra_retorna_409(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente, TipoContratacion::CATEDRA);

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
                 'desde' => '2019-01-01',
                 'motivo' => 'Cátedra.',
             ])
             ->assertStatus(409);
    }

    public function test_ingreso_manual_con_tramo_abierto_retorna_409(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente); // el observer ya lo metió en Auxiliar

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Asociado')->id_escalon,
                 'desde' => '2019-01-01',
                 'motivo' => 'Ya está dentro.',
             ])
             ->assertStatus(409);
    }

    /** Un tramo sobre alguien sin rol Docente no aparecería en ninguna bandeja. */
    public function test_ingreso_manual_sobre_usuario_sin_rol_docente_retorna_409(): void
    {
        $admin = $this->crearAdmin();
        $otro = $this->crearUsuario('Aspirante', 'aspadmesc');
        $this->contratar($otro);

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO . "/docentes/{$otro->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
                 'desde' => '2019-01-01',
                 'motivo' => 'No es docente.',
             ])
             ->assertStatus(409);
    }

    public function test_ingreso_manual_sin_motivo_retorna_422(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
                 'desde' => '2019-01-01',
             ])
             ->assertStatus(422)
             ->assertJsonPath('errors.motivo', fn ($v) => !empty($v));
    }

    /** Un ingreso siempre ocurrió ya: no hay calendario que justifique una fecha futura. */
    public function test_ingreso_manual_con_fecha_futura_retorna_422(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
                 'desde' => now()->addMonth()->toDateString(),
                 'motivo' => 'Fecha futura.',
             ])
             ->assertStatus(422)
             ->assertJsonPath('errors.desde', fn ($v) => !empty($v));
    }

    public function test_ingreso_manual_de_docente_inexistente_retorna_404(): void
    {
        $this->actingAs($this->crearAdmin(), 'api')
             ->postJson(self::PREFIJO . '/docentes/99999999/ingreso-manual', [
                 'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
                 'desde' => '2019-01-01',
                 'motivo' => 'No existe.',
             ])
             ->assertStatus(404);
    }

    /** La caché de `puntajes` es lo que leen los listados: tiene que quedar al día. */
    public function test_ingreso_manual_sincroniza_la_categoria_en_puntajes(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);
        HistorialEscalonDocente::where('user_id', $docente->id)->delete();

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Asociado')->id_escalon,
                 'desde' => '2018-02-01',
                 'motivo' => 'Homologación.',
             ])
             ->assertStatus(201);

        $this->assertSame('Asociado', $docente->fresh()->puntajeUsuario()->value('categoria_lograda'));
    }

    /**
     * Un ingreso manual bloquea al automático: el observer se dispara en cada edición de contrato y
     * no debe volver a meter al docente en Auxiliar por debajo.
     */
    public function test_ingreso_manual_bloquea_el_ingreso_automatico_posterior(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $contrato = $this->contratar($docente);
        HistorialEscalonDocente::where('user_id', $docente->id)->delete();

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Asociado')->id_escalon,
                 'desde' => '2018-02-01',
                 'motivo' => 'Homologación.',
             ])
             ->assertStatus(201);

        // Cualquier edición del contrato vuelve a disparar `ContratacionObserver`.
        $contrato->update(['valor_contrato' => 6000000]);

        $tramos = HistorialEscalonDocente::where('user_id', $docente->id)->get();

        $this->assertCount(1, $tramos);
        $this->assertSame($this->escalon('Asociado')->id_escalon, $tramos->first()->escalon_id);
    }

    public function test_ingreso_manual_escribe_fila_de_creacion_en_la_bitacora(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);
        HistorialEscalonDocente::where('user_id', $docente->id)->delete();

        $this->actingAs($admin, 'api')
             ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ingreso-manual", [
                 'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
                 'desde' => '2019-01-01',
                 'motivo' => 'Motivo de auditoría.',
             ])
             ->assertStatus(201);

        $fila = HistorialEscalonBitacora::where('docente_id', $docente->id)->firstOrFail();

        $this->assertSame(HistorialEscalonBitacora::TIPO_CREACION, $fila->tipo_modificacion);
        $this->assertNull($fila->datos_anteriores);
        $this->assertSame('Auxiliar', $fila->datos_nuevos['escalon']);
        $this->assertSame($admin->id, $fila->user_modifico_id);
        $this->assertSame('Motivo de auditoría.', $fila->motivo);
    }

    // ---------------------------------------------------------------
    // Corrección de tramos
    // ---------------------------------------------------------------

    /** Mover el `desde` mueve la antigüedad, que es el efecto que la respuesta debe explicar. */
    public function test_corregir_desde_cambia_los_meses_en_escalon(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);
        $this->experienciaUniautonomaDesde($docente, '2015-01-01');

        $tramo = HistorialEscalonDocente::where('user_id', $docente->id)->firstOrFail();
        $tramo->update(['desde' => '2024-01-01']);

        $respuesta = $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'desde' => '2016-01-01',
                 'motivo' => 'La contratación se cargó con la fecha del contrato renovado.',
             ])
             ->assertStatus(200);

        $antes = $respuesta->json('data.impacto.meses_en_escalon.antes');
        $despues = $respuesta->json('data.impacto.meses_en_escalon.despues');

        $this->assertGreaterThan($antes, $despues);
        $this->assertSame('2016-01-01', $tramo->fresh()->desde->toDateString());
    }

    /** Corregir el escalón del tramo vigente cambia la categoría, y la respuesta lo dice. */
    public function test_corregir_escalon_del_tramo_vigente_cambia_la_categoria(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);

        $tramo = HistorialEscalonDocente::where('user_id', $docente->id)->firstOrFail();

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'escalon_id' => $this->escalon('Asociado')->id_escalon,
                 'motivo' => 'Se cargó el escalón equivocado.',
             ])
             ->assertStatus(200)
             ->assertJsonPath('data.escalon_vigente', 'Asociado')
             ->assertJsonPath('data.impacto.escalon_vigente.antes', 'Auxiliar')
             ->assertJsonPath('message', fn ($m) => str_contains($m, 'sin evaluación del motor'));

        $this->assertSame('Asociado', $docente->fresh()->puntajeUsuario()->value('categoria_lograda'));
    }

    /**
     * La invariante más fácil de implementar mal: `ascender()` cierra un tramo y abre el siguiente
     * **el mismo día**, así que compartir la fecha de frontera no es un solapamiento.
     */
    public function test_corregir_con_frontera_compartida_retorna_200(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();

        $viejo = $this->tramo($docente, 'Auxiliar', '2015-01-01', '2019-01-01');
        $this->tramo($docente, 'Asistente', '2019-01-01', null);

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$viejo->id_historial_escalon}", [
                 'desde' => '2014-06-01',
                 'motivo' => 'Ajuste de la fecha de entrada, sin tocar la frontera.',
             ])
             ->assertStatus(200);

        $this->assertSame('2014-06-01', $viejo->fresh()->desde->toDateString());
    }

    /** Un hueco entre tramos es legal: el docente pudo retirarse y volver. */
    public function test_corregir_dejando_hueco_entre_tramos_retorna_200(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();

        $viejo = $this->tramo($docente, 'Auxiliar', '2015-01-01', '2019-01-01');
        $this->tramo($docente, 'Asistente', '2019-01-01', null);

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$viejo->id_historial_escalon}", [
                 'hasta' => '2018-06-01',
                 'motivo' => 'El docente se retiró antes de lo registrado.',
             ])
             ->assertStatus(200);

        $this->assertSame('2018-06-01', $viejo->fresh()->hasta->toDateString());
    }

    public function test_corregir_generando_solape_retorna_409(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();

        $viejo = $this->tramo($docente, 'Auxiliar', '2015-01-01', '2019-01-01');
        $this->tramo($docente, 'Asistente', '2019-01-01', null);

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$viejo->id_historial_escalon}", [
                 'hasta' => '2020-01-01',
                 'motivo' => 'Esto se pisa con el tramo siguiente.',
             ])
             ->assertStatus(409)
             ->assertJsonPath('message', fn ($m) => str_contains($m, 'solapa'));
    }

    public function test_corregir_reabriendo_con_otro_tramo_abierto_retorna_409(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();

        $viejo = $this->tramo($docente, 'Auxiliar', '2015-01-01', '2019-01-01');
        $this->tramo($docente, 'Asistente', '2019-01-01', null);

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$viejo->id_historial_escalon}", [
                 'hasta' => null,
                 'motivo' => 'Intento de reabrir.',
             ])
             ->assertStatus(409);
    }

    /** Cerrar el único tramo abierto sacaría al docente del escalafón sin dejar rastro. */
    public function test_corregir_cerrando_el_unico_tramo_abierto_retorna_409(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);

        $tramo = HistorialEscalonDocente::where('user_id', $docente->id)->firstOrFail();

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'hasta' => now()->toDateString(),
                 'motivo' => 'Debería obligarme a usar la reversión.',
             ])
             ->assertStatus(409)
             ->assertJsonPath('message', fn ($m) => str_contains($m, 'reversión'));
    }

    /** Un tramo revertido es el registro de algo que se deshizo: no se reescribe. */
    public function test_corregir_tramo_revertido_retorna_409(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();

        $tramo = $this->tramo($docente, 'Auxiliar', '2015-01-01');
        $tramo->update(['revertido_en' => now(), 'revertido_por' => $admin->id, 'motivo_reversion' => 'x']);

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'desde' => '2014-01-01',
                 'motivo' => 'No debería dejarme.',
             ])
             ->assertStatus(409);
    }

    /** Un escalón inactivo vale para un tramo histórico, no para el vigente. */
    public function test_corregir_tramo_vigente_a_escalon_inactivo_retorna_409(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);

        $tramo = HistorialEscalonDocente::where('user_id', $docente->id)->firstOrFail();
        $asociado = $this->escalon('Asociado');
        $asociado->update(['activo' => false]);

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'escalon_id' => $asociado->id_escalon,
                 'motivo' => 'Escalón retirado del catálogo.',
             ])
             ->assertStatus(409)
             ->assertJsonPath('message', fn ($m) => str_contains($m, 'inactivo'));
    }

    /** El mismo escalón inactivo sí se acepta en un tramo ya cerrado. */
    public function test_corregir_tramo_cerrado_a_escalon_inactivo_retorna_200(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();

        $viejo = $this->tramo($docente, 'Auxiliar', '2015-01-01', '2019-01-01');
        $this->tramo($docente, 'Asistente', '2019-01-01', null);

        $asociado = $this->escalon('Asociado');
        $asociado->update(['activo' => false]);

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$viejo->id_historial_escalon}", [
                 'escalon_id' => $asociado->id_escalon,
                 'motivo' => 'El tramo histórico sí puede apuntar a un escalón retirado.',
             ])
             ->assertStatus(200);
    }

    /** La firma del acto original y su ancla de auditoría no son corregibles. */
    public function test_corregir_no_altera_otorgado_por_ni_periodo_ascenso_id(): void
    {
        $admin = $this->crearAdmin();
        $apoyo = $this->crearUsuario('Apoyo Profesoral', 'apoyofirma');
        $docente = $this->crearDocente();
        $periodo = PeriodoAscenso::create([
            'nombre' => 'Periodo firma ' . uniqid(),
            'fecha_cierre' => '2026-06-30',
            'cerrado_en' => now(),
        ]);

        $tramo = $this->tramo($docente, 'Auxiliar', '2015-01-01');
        $tramo->update([
            'otorgado_por' => $apoyo->id,
            'periodo_ascenso_id' => $periodo->id_periodo_ascenso,
            'via' => HistorialEscalonDocente::VIA_REQUISITOS,
        ]);

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'escalon_id' => $this->escalon('Asistente')->id_escalon,
                 'otorgado_por' => $admin->id,
                 'periodo_ascenso_id' => null,
                 'via' => HistorialEscalonDocente::VIA_INGRESO,
                 'user_id' => $admin->id,
                 'motivo' => 'Solo debe cambiar el escalón.',
             ])
             ->assertStatus(200);

        $fresco = $tramo->fresh();

        $this->assertSame($apoyo->id, $fresco->otorgado_por);
        $this->assertSame($periodo->id_periodo_ascenso, $fresco->periodo_ascenso_id);
        $this->assertSame(HistorialEscalonDocente::VIA_REQUISITOS, $fresco->via);
        $this->assertSame($docente->id, $fresco->user_id);
    }

    public function test_corregir_sin_ningun_campo_retorna_422(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $tramo = $this->tramo($docente, 'Auxiliar', '2015-01-01');

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'motivo' => 'No indico qué cambiar.',
             ])
             ->assertStatus(422);
    }

    public function test_corregir_sin_motivo_retorna_422(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $tramo = $this->tramo($docente, 'Auxiliar', '2015-01-01');

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'desde' => '2014-01-01',
             ])
             ->assertStatus(422)
             ->assertJsonPath('errors.motivo', fn ($v) => !empty($v));
    }

    public function test_corregir_con_hasta_anterior_a_desde_retorna_422(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $tramo = $this->tramo($docente, 'Auxiliar', '2015-01-01', '2019-01-01');

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'desde' => '2018-01-01',
                 'hasta' => '2016-01-01',
                 'motivo' => 'Fechas al revés.',
             ])
             ->assertStatus(422)
             ->assertJsonPath('errors.hasta', fn ($v) => !empty($v));
    }

    /**
     * Cuando solo viaja `hasta`, `after_or_equal:desde` no se evalúa —no hay `desde` en la
     * petición— y la comparación contra el valor guardado la tiene que hacer el servicio.
     */
    public function test_corregir_solo_hasta_valida_contra_el_desde_guardado_retorna_409(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $tramo = $this->tramo($docente, 'Auxiliar', '2015-01-01', '2019-01-01');

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'hasta' => '2014-01-01',
                 'motivo' => 'Anterior al desde guardado.',
             ])
             ->assertStatus(409);
    }

    public function test_corregir_tramo_inexistente_retorna_404(): void
    {
        $this->actingAs($this->crearAdmin(), 'api')
             ->putJson(self::PREFIJO . '/historial/99999999', [
                 'desde' => '2015-01-01',
                 'motivo' => 'No existe.',
             ])
             ->assertStatus(404);
    }

    public function test_corregir_con_id_no_numerico_retorna_404(): void
    {
        $this->actingAs($this->crearAdmin(), 'api')
             ->putJson(self::PREFIJO . '/historial/abc', [
                 'desde' => '2015-01-01',
                 'motivo' => 'Id inválido.',
             ])
             ->assertStatus(404);
    }

    public function test_corregir_escribe_bitacora_con_snapshot_antes_y_despues(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);

        $tramo = HistorialEscalonDocente::where('user_id', $docente->id)->firstOrFail();

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'escalon_id' => $this->escalon('Asistente')->id_escalon,
                 'motivo' => 'Corrección auditada.',
             ])
             ->assertStatus(200);

        $fila = HistorialEscalonBitacora::where('historial_escalon_id', $tramo->id_historial_escalon)
            ->where('tipo_modificacion', HistorialEscalonBitacora::TIPO_ACTUALIZACION)
            ->firstOrFail();

        $this->assertSame('Auxiliar', $fila->datos_anteriores['escalon']);
        $this->assertSame('Asistente', $fila->datos_nuevos['escalon']);
        $this->assertSame($admin->id, $fila->user_modifico_id);
    }

    /** Un tramo corregido a mano ya no es lo que produjo el acto original, y la bandeja lo marca. */
    public function test_un_tramo_corregido_se_marca_en_el_detalle_del_docente(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);

        $tramo = HistorialEscalonDocente::where('user_id', $docente->id)->firstOrFail();

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'desde' => '2017-01-01',
                 'motivo' => 'Fecha corregida.',
             ])
             ->assertStatus(200);

        $this->actingAs($admin, 'api')
             ->getJson(self::PREFIJO . "/docentes/{$docente->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.historial.0.corregido', true);
    }

    // ---------------------------------------------------------------
    // Bitácora
    // ---------------------------------------------------------------

    public function test_bitacora_lista_las_correcciones_del_docente(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);

        $tramo = HistorialEscalonDocente::where('user_id', $docente->id)->firstOrFail();

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}", [
                 'desde' => '2017-01-01',
                 'motivo' => 'Primera corrección.',
             ])
             ->assertStatus(200);

        $this->actingAs($admin, 'api')
             ->getJson(self::PREFIJO . "/docentes/{$docente->id}/bitacora")
             ->assertStatus(200)
             ->assertJsonPath('status', 'success')
             ->assertJsonPath('data.0.motivo', 'Primera corrección.')
             ->assertJsonPath('data.0.modificado_por', $admin->email);
    }

    public function test_bitacora_de_docente_sin_correcciones_retorna_vacia(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);

        $this->actingAs($admin, 'api')
             ->getJson(self::PREFIJO . "/docentes/{$docente->id}/bitacora")
             ->assertStatus(200)
             ->assertJsonCount(0, 'data');
    }

    // ---------------------------------------------------------------
    // Invariante estructural
    // ---------------------------------------------------------------

    /** Ninguna combinación de actos puede dejar a un docente con dos escalones vigentes. */
    public function test_un_docente_nunca_queda_con_dos_tramos_abiertos(): void
    {
        $admin = $this->crearAdmin();
        $docente = $this->crearDocente();
        $this->contratar($docente);

        $abierto = HistorialEscalonDocente::where('user_id', $docente->id)->firstOrFail();
        $cerrado = $this->tramo($docente, 'Auxiliar', '2010-01-01', '2014-01-01');

        $this->actingAs($admin, 'api')
             ->putJson(self::PREFIJO . "/historial/{$cerrado->id_historial_escalon}", [
                 'hasta' => null,
                 'motivo' => 'Intento de dejar dos vigentes.',
             ])
             ->assertStatus(409);

        $vigentes = HistorialEscalonDocente::where('user_id', $docente->id)->vigentes()->count();

        $this->assertSame(1, $vigentes);
        $this->assertNull($abierto->fresh()->hasta);
    }
}

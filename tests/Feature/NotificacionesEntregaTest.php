<?php

namespace Tests\Feature;

use App\Http\Controllers\TalentoHumano\NotificacionController;
use App\Jobs\EnviarNotificacionJob;
use App\Mail\NotificacionMail;
use App\Mail\ResetPasswordMail;
use App\Models\MovimientoExpediente;
use App\Models\NotificacionEnviada;
use App\Models\PeriodoAscenso;
use App\Models\Usuario\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificacionesEntregaTest extends TestCase
{
    use DatabaseTransactions;

    private function docente(): User
    {
        $id = uniqid();
        $user = User::create([
            'municipio_id' => 703, 'tipo_identificacion' => 'Cédula de ciudadanía',
            'numero_identificacion' => $id, 'primer_nombre' => 'Prueba',
            'primer_apellido' => 'Correo', 'fecha_nacimiento' => '1990-01-01',
            'email' => $id . '@example.invalid', 'password' => Hash::make('Password123'),
        ]);
        $user->assignRole('Docente');
        return $user;
    }

    public function test_todos_los_avisos_encolan_y_se_entregan_sin_reencolar(): void
    {
        Queue::fake();
        Mail::fake();
        Notification::fake();
        $user = $this->docente();
        $metodos = new \ReflectionClass(NotificacionController::class);
        $avisos = 0;
        foreach ($metodos->getMethods(\ReflectionMethod::IS_PUBLIC) as $metodo) {
            $parametros = $metodo->getParameters();
            if (!$metodo->isStatic() || $metodo->name === 'enviarNotificacion'
                || !in_array('encolar', array_map(fn ($p) => $p->name, $parametros), true)) {
                continue;
            }
            $args = [];
            foreach ($parametros as $p) {
                $args[$p->name] = match (true) {
                    $p->name === 'encolar' => true,
                    $p->name === 'aprobados' => [['categoria' => 'Estudio', 'descripcion' => 'Maestría']],
                    in_array($p->name, ['usuarios', 'docentes', 'admins', 'coordinadores', 'vicerrectores', 'rectores']) => collect([$user]),
                    (string) $p->getType() === User::class => $user,
                    $p->isDefaultValueAvailable() => $p->getDefaultValue(),
                    (string) $p->getType() === 'array' => [],
                    (string) $p->getType() === 'int' => 7,
                    default => 'Prueba',
                };
            }
            $metodo->invokeArgs(null, $args);
            $avisos++;
        }
        $this->assertSame(27, $avisos);
        Mail::assertNothingSent();
        Queue::assertPushed(EnviarNotificacionJob::class, 27);
        foreach (Queue::pushed(EnviarNotificacionJob::class) as $job) {
            $job->handle();
            $job->handle();
        }
        Mail::assertSent(NotificacionMail::class, 27);
        Queue::assertPushed(EnviarNotificacionJob::class, 27);
    }

    public function test_cierre_natural_recupera_aviso_y_no_repite_entregados(): void
    {
        Queue::fake();
        Mail::fake();
        Notification::fake();
        $apoyo = $this->docente();
        $apoyo->syncRoles(['Apoyo Profesoral']);
        $periodo = PeriodoAscenso::create([
            'nombre' => 'Cierre de prueba', 'fecha_cierre' => today()->subDays(2),
            'creado_por' => $apoyo->id,
        ]);
        $clave = "escalafon.periodo-cerrado:periodo:{$periodo->id_periodo_ascenso}:user:{$apoyo->id}";
        Artisan::call('escalafon:notificar-cierres', ['--simular' => true]);
        Queue::assertNothingPushed();
        Artisan::call('escalafon:notificar-cierres');
        $jobs = Queue::pushed(EnviarNotificacionJob::class)->filter(
            fn ($job) => (new \ReflectionProperty($job, 'clave'))->getValue($job) === $clave
        );
        $this->assertCount(1, $jobs);
        $jobs->first()->handle();
        Artisan::call('escalafon:notificar-cierres');
        $this->assertCount(1, Queue::pushed(EnviarNotificacionJob::class)->filter(
            fn ($job) => (new \ReflectionProperty($job, 'clave'))->getValue($job) === $clave
        ));
        $this->assertNull($periodo->fresh()->cerrado_en);
        Mail::assertSent(NotificacionMail::class, 1);
    }

    public function test_asignar_y_actualizar_evaluacion_deja_novedades_para_el_resumen(): void
    {
        Queue::fake();
        Mail::fake();
        $apoyo = $this->docente();
        $apoyo->syncRoles(['Apoyo Profesoral']);
        $docente = $this->docente();
        $this->actingAs($apoyo, 'api');
        $this->postJson("/api/apoyoProfesoral/asignar-evaluacion/{$docente->id}", [
            'promedio_evaluacion_docente' => 4.0,
        ])->assertCreated();
        $this->putJson("/api/apoyoProfesoral/actualizar-evaluacion/{$docente->id}", [
            'promedio_evaluacion_docente' => 4.5,
        ])->assertOk();
        $movimientos = MovimientoExpediente::where('user_id', $docente->id)->sinNotificar()->get();
        $this->assertCount(2, $movimientos);
        $this->assertSame(['actualizado'], $movimientos->pluck('accion')->unique()->values()->all());
        $this->assertStringContainsString('4.5', $movimientos->last()->descripcion);
        Mail::assertNothingSent();
    }

    public function test_error_smtp_se_registra_y_un_reintento_entrega_una_sola_vez(): void
    {
        $user = $this->docente();
        Notification::fake();
        $manager = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP no disponible'));
        $clave = 'test:' . uniqid();
        $job = new EnviarNotificacionJob($clave, 'test', 'escalonOtorgado', ['Asistente'], $user->id);
        try {
            $job->handle();
            $this->fail('El fallo SMTP debe llegar al worker.');
        } catch (\RuntimeException $e) {
            $this->assertSame('SMTP no disponible', $e->getMessage());
        }
        $registro = NotificacionEnviada::where('clave', $clave)->firstOrFail();
        $this->assertNull($registro->enviado_en);
        $this->assertSame('SMTP no disponible', $registro->ultimo_error);
        Mail::swap($manager);
        Mail::fake();
        $job->handle();
        $job->handle();
        Mail::assertSent(NotificacionMail::class, 1);
        $this->assertNotNull($registro->fresh()->enviado_en);
        $this->assertSame(2, $registro->fresh()->intentos);
    }

    public function test_notificar_desde_http_encola_sin_contactar_smtp(): void
    {
        Queue::fake();
        Mail::fake();
        NotificacionController::nuevaContratacion($this->docente());
        Queue::assertPushed(EnviarNotificacionJob::class, 1);
        Mail::assertNothingSent();
    }

    public function test_resumen_se_marca_solo_al_entregar_y_conserva_lotes_del_mismo_dia(): void
    {
        Queue::fake();
        Mail::fake();
        Notification::fake();
        $user = $this->docente();
        MovimientoExpediente::registrar($user->id, 'aprobado', 'Estudio', 'Maestría');
        Artisan::call('expediente:resumen-diario');
        $primero = MovimientoExpediente::where('user_id', $user->id)->firstOrFail();
        $this->assertNull($primero->notificado_en);
        $clave = $primero->lote_notificacion;
        $this->assertNotNull($clave);
        MovimientoExpediente::registrar($user->id, 'actualizado', 'Evaluación docente', 'Promedio actualizado: 4.5');
        Artisan::call('expediente:resumen-diario');
        $lotes = MovimientoExpediente::where('user_id', $user->id)->pluck('lote_notificacion');
        $this->assertCount(2, $lotes->unique());
        $this->assertSame($clave, $primero->fresh()->lote_notificacion);
        foreach (Queue::pushed(EnviarNotificacionJob::class) as $job) {
            $id = (new \ReflectionProperty($job, 'destinatarioId'))->getValue($job);
            if ($id === $user->id) {
                $job->handle();
            }
        }
        Mail::assertSent(NotificacionMail::class, 2);
        $this->assertSame(0, MovimientoExpediente::where('user_id', $user->id)->sinNotificar()->count());
    }

    public function test_plantillas_escapan_contenido_y_recuerdan_la_regla_real(): void
    {
        $mail = new NotificacionMail('Prueba', '<a href="https://example.invalid">Enlace</a>', '<b>Nombre</b>', ['<i>Clave</i>' => '<b>Valor</b>']);
        $html = $mail->content()->htmlString;
        $this->assertStringNotContainsString('<b>Nombre</b>', $html);
        $this->assertStringContainsString('&lt;i&gt;Clave&lt;/i&gt;', $html);
        $this->assertStringNotContainsString('<a href=', $html);
        Mail::fake();
        Notification::fake();
        NotificacionController::cierrePeriodoProximo($this->docente(), 'Periodo', '30/12/2026', 7, encolar: false);
        Mail::assertSent(NotificacionMail::class, fn ($m) => str_contains($m->mensaje, 'incluso si la revisión termina después'));
    }

    public function test_periodo_permanece_abierto_hasta_final_del_dia_colombiano(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 9)->setTime(23, 59, 59));
        $periodo = new PeriodoAscenso(['fecha_cierre' => '2026-09-09']);
        $this->assertFalse($periodo->estaCerrado());
        $this->travel(1)->seconds();
        $this->assertTrue($periodo->estaCerrado());
        $this->travelBack();
    }

    public function test_recuperacion_valida_contrasena_expiracion_y_uso_unico(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://campus.example.invalid']);
        $user = $this->docente();
        $this->postJson('/api/auth/restablecer-contrasena', ['email' => 'invalido'])->assertStatus(422);
        $this->postJson('/api/auth/restablecer-contrasena', ['email' => $user->email])->assertOk();
        $mail = Mail::sent(ResetPasswordMail::class)->first();
        $this->assertStringStartsWith('https://campus.example.invalid/restablecer-contrasena2?', $mail->resetLink);
        parse_str(parse_url($mail->resetLink, PHP_URL_QUERY), $query);
        $this->assertSame(hash('sha256', $query['token']), DB::table('password_reset_tokens')->where('email', $user->email)->value('token'));
        $data = ['email' => $user->email, 'token' => $query['token'], 'password' => 'aaaaaaaa', 'password_confirmation' => 'aaaaaaaa'];
        $this->postJson('/api/auth/restablecer-contrasena-token', $data)->assertStatus(422)->assertJsonValidationErrors('password');
        $data['password'] = $data['password_confirmation'] = 'NuevaClave123';
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(6)]);
        $this->postJson('/api/auth/restablecer-contrasena-token', $data)->assertStatus(410);
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()]);
        $this->postJson('/api/auth/restablecer-contrasena-token', $data)->assertOk();
        $this->assertTrue(Hash::check('NuevaClave123', $user->fresh()->password));
        $this->travel(1)->minutes();
        $this->postJson('/api/auth/restablecer-contrasena-token', $data)->assertStatus(404);
        $this->travelBack();
    }
}

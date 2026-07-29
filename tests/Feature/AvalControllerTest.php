<?php

namespace Tests\Feature;

use App\Http\Controllers\Convocatoria\AvalController;
use App\Http\Controllers\TalentoHumano\ConvocatoriaAvalController;
use App\Models\Usuario\User;
use App\Services\PuntajeAspiranteService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class AvalControllerTest extends TestCase
{
    public function test_bloquea_el_registro_de_avales_cuando_la_postulacion_esta_rechazada(): void
    {
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['postulacionesUsuario'])
            ->disableOriginalConstructor()
            ->getMock();

        $relacion = new class {
            public function where(...$args)
            {
                return $this;
            }

            public function exists(): bool
            {
                return true;
            }
        };

        $user->expects($this->once())
            ->method('postulacionesUsuario')
            ->willReturn($relacion);

        $controller = new AvalController(new PuntajeAspiranteService());
        $method = new \ReflectionMethod(AvalController::class, 'validarPostulacionNoRechazada');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No se pueden registrar avales porque la postulación fue rechazada para esta convocatoria.');

        $method->invoke($controller, $user, 1);
    }

    public function test_bloquea_la_modificacion_de_avales_en_el_controlador_talento_humano_cuando_la_postulacion_esta_rechazada(): void
    {
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['postulacionesUsuario'])
            ->disableOriginalConstructor()
            ->getMock();

        $relacion = new class {
            public function where(...$args)
            {
                return $this;
            }

            public function exists(): bool
            {
                return true;
            }
        };

        $user->expects($this->once())
            ->method('postulacionesUsuario')
            ->willReturn($relacion);

        $controller = new ConvocatoriaAvalController();
        $method = new \ReflectionMethod(ConvocatoriaAvalController::class, 'validarPostulacionNoRechazada');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No se pueden registrar avales porque la postulación fue rechazada para esta convocatoria.');

        $method->invoke($controller, $user, 1);
    }
}

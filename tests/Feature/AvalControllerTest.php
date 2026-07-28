<?php

namespace Tests\Feature;

use App\Http\Controllers\Convocatoria\AvalController;
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

        $hasMany = $this->getMockBuilder(HasMany::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where', 'exists'])
            ->getMock();

        $hasMany->expects($this->once())
            ->method('where')
            ->willReturnSelf();

        $hasMany->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $user->expects($this->once())
            ->method('postulacionesUsuario')
            ->willReturn($hasMany);

        $controller = new AvalController(new PuntajeAspiranteService());
        $method = new \ReflectionMethod(AvalController::class, 'validarPostulacionNoRechazada');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No se pueden registrar avales porque la postulación fue rechazada para esta convocatoria.');

        $method->invoke($controller, $user, 1);
    }
}

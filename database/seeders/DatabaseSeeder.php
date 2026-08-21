<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call(PaisesTableSeeder::class);
        $this->call(DepartamentosTableSeeder::class);
        $this->call(MunicipiosTableSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(TipoCargoSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(ApoyoProfesoralSeeder::class);
        $this->call(DocenteSeeder::class);
        $this->call(TalentoHumanoSeeder::class);
        $this->call(EvaluadorProduccionSeeder::class);
        $this->call(ProductoAcademicoSeeder::class);
        $this->call(AmbitoDivulgacionSeeder::class);
        // Va DESPUÉS del CSV: el relleno de puntajes de la migración corrió sobre la tabla
        // vacía (el entrypoint migra antes de sembrar) y todos los ámbitos quedaban en 0, lo
        // que dejaba el puntaje de producción de cualquier docente en 0.
        $this->call(AmbitoDivulgacionPuntajeSeeder::class);
        // Debe ir antes de DemoAspirantesSeeder: ese seeder inserta experiencias
        // cuyo tipo se valida contra este catálogo.
        $this->call(TipoExperienciaSeeder::class);
        // Catálogo de idiomas → exámenes → rangos MCER. Es estrictamente aditivo
        // (`firstOrCreate`): nunca pisa lo que el Administrador haya creado o ajustado.
        $this->call(CatalogoIdiomaSeeder::class);
        $this->call(NivelFormacionAcademicaSeeder::class);
        $this->call(EscalonDocenteSeeder::class);
        // Debe ir después: referencia el escalón "Asociado" que siembra el anterior.
        $this->call(ReglaExcepcionEscalonSeeder::class);
        // Trayectoria completa del docente de demo (estudios, idioma, experiencia y producción)
        // con TODOS los documentos aprobados, para poder ver puntaje y categoría del escalafón.
        // Va después de los escalones y de los puntajes de ámbito: depende de ambos.
        $this->call(DemoTrayectoriaDocenteSeeder::class);
        $this->call(AspiranteSeeder::class);
        $this->call(CoordinadorSeeder::class);
        // Los tres roles que tenían pantalla propia pero ningún usuario con el que entrar:
        // sin ellos la cadena de avales no se podía recorrer completa en pruebas.
        $this->call(VicerrectoriaSeeder::class);
        $this->call(RectoriaSeeder::class);
        $this->call(AdministrativoSeeder::class);
        $this->call(DemoAspirantesSeeder::class);
        $this->call(DemoConvocatoriasSeeder::class);
        $this->call(PostulacionesDemoSeeder::class);


    }
}

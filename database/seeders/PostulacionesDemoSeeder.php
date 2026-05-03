<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PostulacionesDemoSeeder extends Seeder
{
    /**
     * Postula a los aspirantes demo (emails @demo.com + aspirante@universidad.com)
     * a la convocatoria cuyo nombre_convocatoria contenga 'CONV-CONT-2026'.
     * Útil para pruebas en el módulo de Talento Humano.
     */
    public function run(): void
    {
        // ── 1. Buscar la convocatoria destino ─────────────────────────────────
        $convocatoria = DB::table('convocatorias')
            ->where('nombre_convocatoria', 'LIKE', '%CONV-CONT-2026%')
            ->first();

        if (!$convocatoria) {
            echo "⚠  No se encontró ninguna convocatoria con nombre LIKE '%CONV-CONT-2026%'.\n";
            echo "   Verifica el nombre exacto en la tabla 'convocatorias' y ajusta este seeder.\n";
            return;
        }

        $convId = $convocatoria->id_convocatoria;
        echo "✓ Convocatoria encontrada: [{$convId}] {$convocatoria->nombre_convocatoria}\n";

        // ── 2. Obtener usuarios demo aspirantes ───────────────────────────────
        $demoEmails = [
            'laura.gomez@demo.com',
            'carlos.martinez@demo.com',
            'maria.rodriguez@demo.com',
            'andres.torres@demo.com',
            'sofia.perez@demo.com',
            'jorge.sanchez@demo.com',
            'diana.vargas@demo.com',
            'hernan.ospina@demo.com',
            'valentina.cruz@demo.com',
            'miguel.restrepo@demo.com',
            'aspirante@universidad.com',
        ];

        $users = DB::table('users')
            ->whereIn('email', $demoEmails)
            ->pluck('id', 'email');

        if ($users->isEmpty()) {
            echo "⚠  No se encontraron usuarios demo. Ejecuta DemoAspirantesSeeder y AspiranteSeeder primero.\n";
            return;
        }

        // ── 3. Crear postulaciones (evitar duplicados) ────────────────────────
        $creadas  = 0;
        $omitidas = 0;

        foreach ($users as $email => $userId) {
            $existe = DB::table('postulacions')
                ->where('convocatoria_id', $convId)
                ->where('user_id', $userId)
                ->exists();

            if ($existe) {
                echo "  · Omitida (ya existe): {$email}\n";
                $omitidas++;
                continue;
            }

            DB::table('postulacions')->insert([
                'convocatoria_id'   => $convId,
                'user_id'           => $userId,
                'estado_postulacion' => 'Enviada',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            echo "  + Postulación creada: {$email}\n";
            $creadas++;
        }

        echo "\n✓ Proceso completado: {$creadas} creada(s), {$omitidas} omitida(s).\n";
    }
}

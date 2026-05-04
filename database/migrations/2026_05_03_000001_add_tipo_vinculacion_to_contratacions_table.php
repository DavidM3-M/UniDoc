<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratacions', function (Blueprint $table) {
            if (! Schema::hasColumn('contratacions', 'tipo_vinculacion')) {
                $table->string('tipo_vinculacion')
                    ->default('Docente')
                    ->after('tipo_proceso')
                    ->comment('Docente | Administrativo — determina el rol asignado en la primera contratación');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratacions', function (Blueprint $table) {
            $table->dropColumn('tipo_vinculacion');
        });
    }
};

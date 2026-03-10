<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulacions', function (Blueprint $table) {
            $table->text('motivo_rechazo')->nullable()->after('estado_postulacion');
            $table->string('rechazado_por')->nullable()->after('motivo_rechazo');
        });
    }

    public function down(): void
    {
        Schema::table('postulacions', function (Blueprint $table) {
            $table->dropColumn(['motivo_rechazo', 'rechazado_por']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->string('facultad_otro')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropColumn('facultad_otro');
        });
    }
};

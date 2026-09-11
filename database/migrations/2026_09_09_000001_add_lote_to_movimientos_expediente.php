<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('movimientos_expediente', function (Blueprint $table) {
            $table->string('lote_notificacion', 100)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_expediente', function (Blueprint $table) {
            $table->dropColumn('lote_notificacion');
        });
    }
};

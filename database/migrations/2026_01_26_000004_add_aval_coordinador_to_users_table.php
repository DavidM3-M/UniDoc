<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('aval_coordinador')->default(false)->after('aval_talento_humano');
            $table->unsignedBigInteger('aval_coordinador_by')->nullable()->after('aval_coordinador');
            $table->timestamp('aval_coordinador_at')->nullable()->after('aval_coordinador_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['aval_coordinador', 'aval_coordinador_by', 'aval_coordinador_at']);
        });
    }
};

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
            $table->boolean('aval_talento_humano_2')->default(false)->nullable()->after('aval_talento_humano');
            $table->boolean('aval_coordinador_2')->default(false)->nullable()->after('aval_coordinador');
            $table->boolean('aval_vicerrectoria_2')->default(false)->nullable()->after('aval_vicerrectoria');
            $table->boolean('aval_rectoria_2')->default(false)->nullable()->after('aval_rectoria');
            $table->timestamp('aval_talento_humano_2_at')->nullable()->after('aval_talento_humano_2');
            $table->timestamp('aval_coordinador_2_at')->nullable()->after('aval_coordinador_2');
            $table->timestamp('aval_vicerrectoria_2_at')->nullable()->after('aval_vicerrectoria_2');
            $table->timestamp('aval_rectoria_2_at')->nullable()->after('aval_rectoria_2');
            $table->unsignedBigInteger('aval_talento_humano_2_by')->nullable()->after('aval_talento_humano_2_at');
            $table->unsignedBigInteger('aval_coordinador_2_by')->nullable()->after('aval_coordinador_2_at');
            $table->unsignedBigInteger('aval_vicerrectoria_2_by')->nullable()->after('aval_vicerrectoria_2_at');
            $table->unsignedBigInteger('aval_rectoria_2_by')->nullable()->after('aval_rectoria_2_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'aval_talento_humano_2',
                'aval_coordinador_2',
                'aval_vicerrectoria_2',
                'aval_rectoria_2',
            ]);
        });
    }
};

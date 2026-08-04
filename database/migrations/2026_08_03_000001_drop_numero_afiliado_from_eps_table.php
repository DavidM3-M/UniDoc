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
        if (Schema::hasColumn('eps', 'numero_afiliado')) {
            Schema::table('eps', function (Blueprint $table) {
                $table->dropColumn('numero_afiliado');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('eps', 'numero_afiliado')) {
            Schema::table('eps', function (Blueprint $table) {
                $table->string('numero_afiliado')->nullable();
            });
        }
    }
};

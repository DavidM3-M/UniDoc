<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Cambiar de integer/tinyint a string
            $table->string('aval_rectoria')->nullable()->change();
            $table->string('aval_vicerrectoria')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('aval_rectoria')->nullable()->change();
            $table->tinyInteger('aval_vicerrectoria')->nullable()->change();
        });
    }
};

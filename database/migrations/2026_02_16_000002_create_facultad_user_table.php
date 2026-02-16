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
        if (! Schema::hasTable('facultad_user')) {
            Schema::create('facultad_user', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id');
                $table->unsignedInteger('facultad_id');
                $table->boolean('is_active')->default(true);

                $table->primary(['user_id', 'facultad_id']);

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('facultad_id')->references('id_facultad')->on('facultades')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facultad_user');
    }
};

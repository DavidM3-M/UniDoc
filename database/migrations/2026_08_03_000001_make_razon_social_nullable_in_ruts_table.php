<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La razón social solo aplica a personas jurídicas; las personas naturales
     * no deben estar obligadas a diligenciarla.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE ruts ALTER COLUMN razon_social DROP NOT NULL');
    }

    public function down(): void
    {
        DB::table('ruts')->whereNull('razon_social')->update(['razon_social' => '']);
        DB::statement('ALTER TABLE ruts ALTER COLUMN razon_social SET NOT NULL');
    }
};

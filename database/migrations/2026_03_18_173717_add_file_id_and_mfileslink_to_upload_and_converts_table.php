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
        Schema::table('upload_and_converts', function (Blueprint $table) {
            $table->string('file_id');
            $table->string('mfiles_view_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upload_and_converts', function (Blueprint $table) {
            $table->dropColumn('file_id');
            $table->dropColumn('mfiles_view_link');
        });
    }
};

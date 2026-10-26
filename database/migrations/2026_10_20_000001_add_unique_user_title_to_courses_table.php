<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 適用前に (user_id, title) の重複データがないことを確認すること。
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unique(['user_id', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'title']);
        });
    }
};

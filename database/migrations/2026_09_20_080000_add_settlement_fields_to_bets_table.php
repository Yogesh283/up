<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->string('board', 20)->nullable()->after('draw_name');
            $table->string('market_slug')->nullable()->after('board');
            $table->string('result_value', 20)->nullable()->after('prize');
            $table->timestamp('settled_at')->nullable()->after('result_value');
        });
    }

    public function down(): void
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->dropColumn(['board', 'market_slug', 'result_value', 'settled_at']);
        });
    }
};

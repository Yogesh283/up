<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('draw_id');
            $table->string('draw_name');
            $table->json('numbers');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->decimal('prize', 12, 2)->default(0);
            $table->timestamp('draw_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};

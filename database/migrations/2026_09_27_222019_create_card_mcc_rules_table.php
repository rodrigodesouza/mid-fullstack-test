<?php

declare(strict_types=1);

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
        Schema::create('card_mcc_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->string('mcc', 4);
            $table->string('rule');
            $table->unsignedSmallInteger('tolerance_percent')->default(0);
            $table->timestamps();

            $table->unique(['card_id', 'mcc']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_mcc_rules');
    }
};

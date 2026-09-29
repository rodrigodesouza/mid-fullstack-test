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
        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('external_id')->unique();

            $table->foreignUuid('authorization_id')
                ->constrained('authorizations');

            $table->string('type');

            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->boolean('final')->nullable();

            $table->timestampTz('occurred_at');
            $table->string('status');

            $table->timestamps();

            $table->index(['authorization_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

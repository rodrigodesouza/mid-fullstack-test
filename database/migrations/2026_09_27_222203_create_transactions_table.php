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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('card_id')->nullable()->constrained('cards');

            $table->foreignUuid('authorization_id')
                ->nullable()
                ->constrained('authorizations');

            $table->foreignUuid('event_id')
                ->nullable()
                ->constrained('events');

            $table->string('type');
            $table->unsignedBigInteger('amount_cents');

            $table->timestampTz('occurred_at');
            $table->date('limit_month')->nullable();
            $table->string('reference');

            $table->timestamps();

            $table->index(['company_id', 'occurred_at']);
            $table->index(['card_id', 'limit_month']);
            $table->index(['authorization_id']);
            $table->index(['event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

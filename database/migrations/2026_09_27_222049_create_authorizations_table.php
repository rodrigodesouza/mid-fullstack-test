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
        Schema::create('authorizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('external_id')->unique();

            $table->foreignId('card_id')->constrained('cards');
            $table->foreignId('company_id')->constrained('companies');

            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('mcc', 4);

            $table->string('decision');
            $table->string('reason')->nullable();

            $table->string('merchant_name');
            $table->string('merchant_city');
            $table->string('merchant_country', 2);

            $table->timestampTz('occurred_at');

            $table->timestamps();

            $table->index(['card_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorizations');
    }
};

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
        Schema::table('events', function (Blueprint $table): void {
            $table->dropForeign(['authorization_id']);

            $table->uuid('authorization_id')->nullable()->change();

            $table->foreign('authorization_id')
                ->references('id')
                ->on('authorizations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropForeign(['authorization_id']);

            $table->uuid('authorization_id')->nullable(false)->change();

            $table->foreign('authorization_id')
                ->references('id')
                ->on('authorizations');
        });
    }
};

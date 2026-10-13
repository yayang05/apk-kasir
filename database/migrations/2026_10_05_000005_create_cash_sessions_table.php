<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('opening_cash', 14, 2);
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('closing_cash', 14, 2)->nullable();
            $table->decimal('difference', 14, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'closed_at']);
            $table->index(['branch_id', 'opened_at']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_session_id')
                ->nullable()
                ->after('user_id')
                ->constrained('cash_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
        });

        Schema::dropIfExists('cash_sessions');
    }
};
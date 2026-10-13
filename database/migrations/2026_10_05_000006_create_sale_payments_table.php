<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('paid')->after('payment_method');
            $table->string('customer_name')->nullable()->after('payment_status');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method', 20);
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_tendered', 14, 2)->nullable();
            $table->decimal('change_amount', 14, 2)->default(0);
            $table->string('reference', 100)->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['payment_method', 'paid_at']);
            $table->index(['cash_session_id', 'payment_method']);
        });

        DB::table('sales')->orderBy('id')->chunkById(500, function ($sales) {
            $payments = $sales->map(fn ($sale) => [
                'sale_id' => $sale->id,
                'user_id' => $sale->user_id,
                'cash_session_id' => $sale->cash_session_id,
                'payment_method' => $sale->payment_method === 'transfer' ? 'transfer' : 'cash',
                'amount' => $sale->grand_total,
                'amount_tendered' => $sale->paid_amount,
                'change_amount' => $sale->change_amount,
                'reference' => null,
                'paid_at' => $sale->created_at,
                'created_at' => $sale->created_at,
                'updated_at' => $sale->updated_at,
            ])->all();

            if ($payments !== []) {
                DB::table('sale_payments')->insert($payments);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'customer_name', 'customer_phone']);
        });
    }
};
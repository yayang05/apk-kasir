<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('member_no', 30)->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['name', 'phone']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('order_name')->nullable()->after('customer_phone');
            $table->text('notes')->nullable()->after('order_name');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'order_name', 'notes']);
        });
        Schema::dropIfExists('customers');
    }
};

<?php

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
        Schema::create('merchant_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants');
            $table->integer('type_transactions');
            $table->integer('status');
            $table->string('network');
            $table->string('wallet_from')->nullable();
            $table->string('wallet_to');
            $table->string('merchant_system_user_id')->nullable();
            $table->string('merchant_system_transaction_id')->nullable();
            $table->decimal('sum',16,8);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_transactions');
    }
};

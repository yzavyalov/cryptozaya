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
        Schema::table('merchant_wallets', function (Blueprint $table) {
            $table->string('merchant_user_id')->after('id')->nullable();
            $table->string('merchant_transaction_id')->after('merchant_user_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_wallets', function (Blueprint $table) {
            $table->dropColumn('merchant_user_id');
            $table->dropColumn('merchant_transaction_id');
        });
    }
};

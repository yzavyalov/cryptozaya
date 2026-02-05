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
            $table->string('hex')->after('number')->nullable();
            $table->string('public_key')->after('network')->nullable();
            $table->text('private_key')->after('public_key')->nullable();
            $table->integer('status')->after('id')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_wallets', function (Blueprint $table) {
            $table->dropColumn('hex');
            $table->dropColumn('public_key');
            $table->dropColumn('private_key');
            $table->dropColumn('status');
        });
    }
};

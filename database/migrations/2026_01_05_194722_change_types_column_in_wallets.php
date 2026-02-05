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
        Schema::table('wallets', function (Blueprint $table) {
            $table->text('publicKey')->nullable()->change();
            $table->text('privateKey')->nullable()->change();
            $table->string('hex', 128)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->string('publicKey', 255)->nullable()->change();
            $table->string('privateKey', 255)->nullable()->change();
            $table->string('hex', 64)->nullable()->change();
        });
    }
};

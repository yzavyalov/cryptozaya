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
        Schema::table('user_balances', function (Blueprint $table) {
            $table->dropColumn('currency');
            $table->foreignId('currency_id')->nullable()->after('user_id')->constrained('currencies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_balances', function (Blueprint $table) {
            $table->dropColumn('currency_id');
            $table->string('currency')->after('user_id');
        });
    }
};

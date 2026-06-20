<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_state_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('dealer_wallets')->cascadeOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->string('action');
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at');

            $table->index('wallet_id');
            $table->index('from_status');
            $table->index('to_status');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_state_logs');
    }
};

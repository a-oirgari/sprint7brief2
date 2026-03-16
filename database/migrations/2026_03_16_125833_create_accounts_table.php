<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->unique();
            $table->enum('type', ['COURANT', 'EPARGNE', 'MINEUR']);
            $table->enum('status', ['ACTIVE', 'BLOCKED', 'CLOSED'])->default('ACTIVE');
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('overdraft_limit', 15, 2)->default(0);
            $table->decimal('interest_rate', 5, 4)->default(0); 
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->string('blocked_reason')->nullable();
            $table->foreignId('guardian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
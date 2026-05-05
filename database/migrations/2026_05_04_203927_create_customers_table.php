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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->enum('status', ['active', 'blocked', 'guest'])->default('guest');
            $table->boolean('accepts_marketing')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->decimal('average_order_value', 12, 2)->default(0);
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('preferred_currency')->nullable();
            $table->string('preferred_locale')->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['store_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

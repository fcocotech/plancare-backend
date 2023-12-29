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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->string('description')->nullable();
            $table->integer('trans_type');//1-payment,2-withdrawal,3-returns
            $table->integer('payment_method');//1-cash,2-bank transfer,3-ewallet,4-payment center,5-other
            $table->double('amount')->default(0);
            $table->string('proof_url')->nullable();
            $table->integer('processed_by');//user who processed transaction
            $table->integer('created_by');//user who created transaction
            $table->integer('user_id');//if 1: commission for user, if 2: user who request withdrawal
            $table->integer('status');//1 complete - 2 pending
            $table->double('commission_rate')->nullable();
            $table->integer('commission_from')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

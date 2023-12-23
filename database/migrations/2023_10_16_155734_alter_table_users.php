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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('sec_q1')->nullable();
            $table->string('sec_q1_ans')->nullable();
            $table->integer('sec_q2')->nullable();
            $table->string('sec_q2_ans')->nullable();
            $table->integer('sec_q3')->nullable();
            $table->string('sec_q3_ans')->nullable();
            $table->integer('sec_q4')->nullable();
            $table->string('sec_q4_ans')->nullable();
            $table->integer('sec_q5')->nullable();
            $table->string('sec_q5_ans')->nullable();
            $table->integer('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

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
        Schema::create('user_otp', function (Blueprint $table) {
            $table->increments('id');              
            $table->integer('user_id')->nullable()->index();
            $table->string('phone', 200)->default('')->nullable();
            $table->string('email', 200)->nullable();
            $table->text('secret_code_encrypted')->nullable();
            $table->string('trace', 200)->default('')->nullable();
            $table->timestamp('expiry')->nullable();
            $table->string('ip', 200)->default('')->nullable();
            $table->string('user_agent', 200)->default('')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_otp');
    }
};

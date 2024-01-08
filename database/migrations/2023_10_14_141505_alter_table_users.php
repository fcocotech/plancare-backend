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
            $table->timestamp('birthdate')->nullable();
            $table->string('nationality')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('zipcode')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('idtype')->nullable();
            $table->string('idurl')->nullable();
            $table->string('referral_code')->unique();
            $table->string('profile_url')->nullable();
            $table->integer('is_admin')->nullable()->default(0);
            $table->json('security_answers')->nullable();
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

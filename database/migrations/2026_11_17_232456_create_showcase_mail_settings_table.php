<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row for the whole platform: how the demo requests of the home page
     * reach the administrators, and through which SMTP server they leave.
     */
    public function up(): void
    {
        Schema::create('showcase_mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('recipient')->nullable();
            $table->string('mail_host')->nullable();
            $table->unsignedSmallInteger('mail_port')->nullable();
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable();
            $table->string('mail_encryption', 8)->nullable();
            $table->string('mail_from_address')->nullable();
            $table->dateTime('mail_tested_at')->nullable();
            $table->text('mail_error')->nullable();
            $table->dateTime('mail_failed_at')->nullable();
            $table->dateTime('mail_verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('showcase_mail_settings');
    }
};

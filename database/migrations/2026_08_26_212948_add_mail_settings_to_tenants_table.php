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
        Schema::table('tenants', function (Blueprint $table) {
            // Own SMTP credentials. When present the portal sends from its own
            // server and its own address, which is what SPF and DKIM expect.
            // The password is encrypted at rest and never rendered back.
            $table->string('mail_host')->nullable()->after('reply_to_email');
            $table->unsignedSmallInteger('mail_port')->nullable()->after('mail_host');
            $table->string('mail_username')->nullable()->after('mail_port');
            $table->text('mail_password')->nullable()->after('mail_username');
            $table->string('mail_encryption', 8)->nullable()->after('mail_password');
            $table->string('mail_from_address')->nullable()->after('mail_encryption');

            // Outcome of the last connection test, so a broken setup is visible
            // instead of silently swallowing every notification.
            $table->dateTime('mail_tested_at')->nullable()->after('mail_from_address');
            $table->text('mail_error')->nullable()->after('mail_tested_at');

            $table->boolean('notify_manager_on_booking')->default(false)->after('mail_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'mail_host', 'mail_port', 'mail_username', 'mail_password',
                'mail_encryption', 'mail_from_address', 'mail_tested_at',
                'mail_error', 'notify_manager_on_booking',
            ]);
        });
    }
};

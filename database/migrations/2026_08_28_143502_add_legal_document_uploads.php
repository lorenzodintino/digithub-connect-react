<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A portal without a website can upload its documents instead of linking them.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('privacy_policy_path')->nullable()->after('privacy_policy_url');
            $table->string('terms_path')->nullable()->after('terms_url');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['privacy_policy_path', 'terms_path']);
        });
    }
};

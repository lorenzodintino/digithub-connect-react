<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the plan was last aligned with the catalogue of the License Manager.
     * Empty for a plan only defined here.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('synced_at');
        });
    }
};

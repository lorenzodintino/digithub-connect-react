<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The palette a platform administrator picked for themselves (see
     * App\Enums\Palette). Everybody else sees the palette of their business,
     * so for them the column stays empty.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('palette', 20)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('palette');
        });
    }
};

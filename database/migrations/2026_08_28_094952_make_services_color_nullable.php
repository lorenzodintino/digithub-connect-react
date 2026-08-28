<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A null color means "follow the portal brand color".
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->default(null)->change();
        });

        DB::table('services')->update(['color' => null]);
    }

    public function down(): void
    {
        $brandColors = DB::table('tenants')->pluck('brand_color', 'id');

        foreach ($brandColors as $tenantId => $brandColor) {
            DB::table('services')
                ->where('tenant_id', $tenantId)
                ->whereNull('color')
                ->update(['color' => $brandColor]);
        }

        Schema::table('services', function (Blueprint $table) {
            $table->string('color', 7)->nullable(false)->default('#4f46e5')->change();
        });
    }
};

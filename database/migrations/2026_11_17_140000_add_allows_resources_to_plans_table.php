<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a plan includes the management of shared resources (rooms, cabins,
     * equipment, their unavailability and the requirements of a service).
     *
     * Every plan already there keeps resources (default true, so nobody loses a
     * feature by an update), except the one named Base: the entry plan does not
     * include them. A portal that already has resources on a plan without them
     * keeps them working but cannot add or change any (see ResourcePolicy).
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('allows_resources')->default(true)->after('allows_custom_smtp');
        });

        DB::table('plans')->whereRaw('lower(trim(name)) = ?', ['base'])->update(['allows_resources' => false]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('allows_resources');
        });
    }
};

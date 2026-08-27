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
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('default')->after('email');
            $table->string('timezone')->default('Europe/Rome')->after('role');
            $table->string('locale', 5)->default('it')->after('timezone');
            $table->string('phone')->nullable()->after('locale');
            $table->string('job_title')->nullable()->after('phone');
            $table->text('bio')->nullable()->after('job_title');
            $table->string('avatar_path')->nullable()->after('bio');
            $table->boolean('is_bookable')->default(false)->after('avatar_path');
            $table->boolean('is_active')->default(true)->after('is_bookable');

            $table->index(['tenant_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'role']);
            $table->dropColumn([
                'tenant_id', 'role', 'timezone', 'locale', 'phone',
                'job_title', 'bio', 'avatar_path', 'is_bookable', 'is_active',
            ]);
        });
    }
};

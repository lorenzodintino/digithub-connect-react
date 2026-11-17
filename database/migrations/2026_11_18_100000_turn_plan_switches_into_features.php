<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * What a plan includes becomes one set of features plus numeric limits, the
     * shape the License Manager will hand over (see docs/prompt-3-catalogo-piani.md).
     *
     * The two switches that were columns (own SMTP, resources) move into the set.
     * The features that did not exist yet are filled by the name of the plan:
     * Base has none, Pro has the waiting list (5 people per service), packages
     * (3) and calendar and embedding, Studio has everything; a plan with any
     * other name keeps everything it could do before, so nobody loses a
     * feature by an update. `code` is the stable key of the plan.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->unique()->after('id');
            $table->json('features')->nullable()->after('max_bookings_per_month');
            $table->unsignedInteger('max_waiting_list')->nullable()->after('max_bookings_per_month');
            $table->unsignedInteger('max_packages')->nullable()->after('max_waiting_list');
        });

        $codes = [];

        foreach (DB::table('plans')->orderBy('id')->get() as $plan) {
            $key = mb_strtolower(trim($plan->name));
            $code = Str::slug($key) ?: 'plan';

            while (in_array($code, $codes, true)) {
                $code .= '-'.$plan->id;
            }

            $codes[] = $code;

            $features = match ($key) {
                'base' => ['waiting_list' => false, 'packages' => false, 'group_services' => false, 'calendar_buttons' => false, 'embed' => false],
                'pro' => ['waiting_list' => true, 'packages' => true, 'group_services' => false, 'calendar_buttons' => true, 'embed' => true],
                default => ['waiting_list' => true, 'packages' => true, 'group_services' => true, 'calendar_buttons' => true, 'embed' => true],
            };

            DB::table('plans')->where('id', $plan->id)->update([
                'code' => $code,
                'features' => json_encode(['resources' => (bool) $plan->allows_resources, 'custom_smtp' => (bool) $plan->allows_custom_smtp, ...$features]),
                'max_waiting_list' => $key === 'pro' ? 5 : null,
                'max_packages' => $key === 'pro' ? 3 : null,
            ]);
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['allows_custom_smtp', 'allows_resources']);
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('allows_custom_smtp')->default(false);
            $table->boolean('allows_resources')->default(true);
        });

        foreach (DB::table('plans')->get() as $plan) {
            $features = json_decode((string) $plan->features, true) ?: [];

            DB::table('plans')->where('id', $plan->id)->update([
                'allows_custom_smtp' => (bool) ($features['custom_smtp'] ?? false),
                'allows_resources' => (bool) ($features['resources'] ?? false),
            ]);
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'features', 'max_waiting_list', 'max_packages']);
        });
    }
};

<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InstallPlatform extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:install
        {--name= : Name of the platform administrator (asked when missing)}
        {--email= : Email of the platform administrator, used to sign in (asked when missing)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'First-time setup of a live installation: creates the three plans and the first platform administrator';

    /**
     * Replaces `db:seed` on a real server, which would also create a demo portal
     * and an administrator with a known password. The password is never an
     * option: it would stay in the shell history, so it is always asked, hidden.
     *
     * Refuses to run once a platform administrator exists, so it can never be
     * used to add a second one from the server by mistake. Plans already there
     * are left untouched (see PlanSeeder).
     */
    public function handle(): int
    {
        if (User::query()->where('role', UserRole::Admin)->exists()) {
            $this->error('A platform administrator already exists. Nothing was changed.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?? $this->ask('Administrator name'));
        $email = (string) ($this->option('email') ?? $this->ask('Administrator email'));
        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            $this->line('Nothing was created. Run the command again.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password): void {
            (new PlanSeeder)->run();

            (new User)->forceFill([
                'tenant_id' => null,
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => $password,
                'role' => UserRole::Admin,
                'is_bookable' => false,
                'is_active' => true,
            ])->save();
        });

        $this->info("Platform administrator {$email} created, plans ready.");
        $this->line('Sign in with that email and the password you just typed.');

        return self::SUCCESS;
    }
}

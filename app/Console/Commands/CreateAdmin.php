<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use App\Support\Permissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'sudar:create-admin {email} {--name=Administrator}';

    protected $description = 'Create (or promote) a super-admin staff account; prompts for the password';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        $email = strtolower($this->argument('email'));
        $password = $this->secret('Password (min 12 characters, letters and numbers)');

        $v = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(12)->letters()->numbers()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => $email], ['name' => $this->option('name'), 'password' => $password, 'is_active' => true]);
        $user->syncRoles([Permissions::SUPER_ADMIN]);
        Audit::log('staff.super_admin_created', $user, ['email' => $email]);
        $this->info("Super admin ready: {$email}. Sign in at /admin and enrol an authenticator app.");

        return self::SUCCESS;
    }
}

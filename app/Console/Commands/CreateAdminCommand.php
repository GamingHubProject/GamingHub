<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'gaming-hub:admin
        {--name= : Administrator display name}
        {--email= : Administrator email}
        {--password= : Administrator password (omit to be prompted securely)}';

    protected $description = 'Create (or promote) an administrator account for the Filament admin panel';

    public function handle(): int
    {
        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);

        $name = $this->option('name') ?: $this->ask('Administrator name');
        $email = $this->option('email') ?: $this->ask('Administrator email');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email']],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = $this->option('password');

        if (! $password) {
            $password = $this->secret('Administrator password');
            $confirm = $this->secret('Confirm password');

            if ($password !== $confirm) {
                $this->error('Passwords did not match.');

                return self::FAILURE;
            }
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles(['Admin']);

        $this->info("Administrator account ready: {$user->email}");

        return self::SUCCESS;
    }
}

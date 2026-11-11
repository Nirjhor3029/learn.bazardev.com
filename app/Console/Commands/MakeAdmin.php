<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin
        {email : Email of the admin account}
        {--name=Admin : Name used if the account has to be created}
        {--reset-password : Generate a new password even if the account already exists}';

    protected $description = 'Create an admin account (or promote an existing one). The generated password is shown once.';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("'{$email}' is not a valid email address.");

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        $password = null;

        if (! $user) {
            $password = Str::password(20, symbols: false);
            $user = new User(['name' => $this->option('name'), 'email' => $email, 'password' => $password]);
        } elseif ($this->option('reset-password')) {
            $password = Str::password(20, symbols: false);
            $user->password = $password;
        }

        $user->forceFill([
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->info("{$email} is an admin.");

        if ($password) {
            $this->line("Password (shown once, change it after logging in): {$password}");
        } else {
            $this->line('Existing password left unchanged (use --reset-password to generate a new one).');
        }

        return self::SUCCESS;
    }
}

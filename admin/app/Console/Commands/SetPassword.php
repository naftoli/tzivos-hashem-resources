<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetPassword extends Command
{
    protected $signature = 'resources:set-password {email : The account email}';

    protected $description = 'Set a sign-in password for an account. The password is typed hidden, not shown on screen.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if (! $user) {
            $this->error('No account with that email.');
            return self::FAILURE;
        }

        $password = (string) $this->secret("Type a new password for {$user->email} (it will not show on the screen)");
        if (strlen($password) < 8) {
            $this->error('Please use at least 8 characters.');
            return self::FAILURE;
        }
        $confirm = (string) $this->secret('Type it again to confirm');
        if ($password !== $confirm) {
            $this->error('Those two passwords did not match. Nothing was changed.');
            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info('The password is set. You can sign in on the login page with this email and password.');
        return self::SUCCESS;
    }
}
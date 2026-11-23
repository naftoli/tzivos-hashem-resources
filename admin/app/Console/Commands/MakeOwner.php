<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeOwner extends Command
{
    protected $signature = 'resources:make-owner
                            {--email= : Email they will use to sign in}
                            {--first=Sholom : First name}
                            {--last=Chaskind : Last name}
                            {--org=TH HQ : School or organization}
                            {--org-role=Programmer : Role in that place}';

    protected $description = 'Create or update an HQ admin Owner account. Does not set a password; use resources:set-password for that.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please pass a valid email, for example: php artisan resources:make-owner --email=name@example.com');
            return self::FAILURE;
        }

        $first = trim((string) $this->option('first'));
        $last = trim((string) $this->option('last'));
        $org = trim((string) $this->option('org'));
        $orgRole = trim((string) $this->option('org-role'));
        if ($first === '' || $last === '' || $org === '' || $orgRole === '') {
            $this->error('First name, last name, organization and role in that place are required.');
            return self::FAILURE;
        }

        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        $created = false;
        if (! $user) {
            $user = new User;
            $user->email = $email;
            $user->password = Str::random(64);
            $created = true;
        }

        $user->first_name = $first;
        $user->last_name = $last;
        $user->name = trim($first.' '.$last);
        $user->organization = $org;
        $user->organization_role = $orgRole;
        $user->role = 'hq_admin';
        $user->role_locked = true;
        $user->is_owner = true;
        $user->save();

        $this->info(($created ? 'Created' : 'Updated')." {$user->name} ({$user->email}) as HQ admin and Owner.");
        $this->line('No other admin can lower or remove this account.');
        if ($created) {
            $this->line('The password is not set yet. In this folder run:');
            $this->line("  php artisan resources:set-password {$user->email}");
        }

        return self::SUCCESS;
    }
}
<?php

namespace App\Console\Commands;

use App\Actions\Platform\BootstrapSuperadmin;
use App\Models\User;
use DomainException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('platform:bootstrap-superadmin {email : Email of the first platform administrator}')]
#[Description('Create or select the first verified platform administrator')]
class BootstrapSuperadminCommand extends Command
{
    public function handle(BootstrapSuperadmin $bootstrap): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $existingUser = User::query()->where('email', $email)->first();
        $username = null;
        $password = null;

        if (! $existingUser instanceof User) {
            $username = trim((string) $this->ask('Username for the new administrator'));
            $password = (string) $this->secret('Password for the new administrator');
        }

        $validator = Validator::make(
            compact('email', 'username', 'password'),
            [
                'email' => ['required', 'email'],
                'username' => [$existingUser instanceof User ? 'nullable' : 'required', 'string', 'max:255', 'unique:users,username'],
                'password' => [$existingUser instanceof User ? 'nullable' : 'required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $grant = $bootstrap->execute($email, $username, $password);
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Platform Superadmin granted to {$grant->user->email}.");

        return self::SUCCESS;
    }
}

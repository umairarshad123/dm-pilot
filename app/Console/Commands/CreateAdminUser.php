<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('admin:create {email : Admin email} {--name= : Display name} {--password= : Password (prompted securely if omitted)}')]
#[Description('Create an admin user, or promote/reset an existing user by email')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $password = $this->option('password') ?? $this->secret('Password (min 8 characters)');

        if (! $this->option('password')) {
            $confirm = $this->secret('Confirm password');
            if ($confirm !== $password) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            ['email' => $email, 'password' => $password, 'name' => $this->option('name')],
            ['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'min:8'], 'name' => ['nullable', 'string', 'max:255']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $existed = $user->exists;
        $user->forceFill([
            'name' => $this->option('name') ?? $user->name ?? strstr($email, '@', true),
            'password' => $password,
            'is_admin' => true,
        ])->save();

        $this->info(($existed ? 'Updated' : 'Created')." admin user {$email} (id {$user->id}).");

        return self::SUCCESS;
    }
}

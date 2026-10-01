<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Creates an assessor account, or upgrades an existing account to assessor.
 *
 * Registration through the API only ever creates students, so this is how
 * the team gives someone assessor access. Run it from Railway's Console tab:
 *
 *   php artisan assessor:create assessor@example.com "Jane Smith"
 *
 * It asks for the password, so it never ends up in your terminal history.
 */
class CreateAssessor extends Command
{
    protected $signature = 'assessor:create
                            {email : Email address for the assessor}
                            {name? : Full name (only needed for a new account)}';

    protected $description = 'Create an assessor account, or upgrade an existing account to assessor';

    public function handle(): int
    {
        $email = $this->argument('email');

        $existing = User::where('email', $email)->first();

        if ($existing) {
            if ($existing->role === 'assessor') {
                $this->info("{$email} is already an assessor. Nothing changed.");
                return self::SUCCESS;
            }

            if (!$this->confirm("{$email} already exists as a {$existing->role}. Upgrade it to assessor?", true)) {
                $this->warn('Cancelled. Nothing changed.');
                return self::SUCCESS;
            }

            $existing->update(['role' => 'assessor']);
            $this->info("{$email} is now an assessor.");
            return self::SUCCESS;
        }

        $name = $this->argument('name') ?? $this->ask('Full name');
        $password = $this->secret('Password (min 8 characters)');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email'    => 'required|email',
                'name'     => 'required|string|max:255',
                'password' => 'required|string|min:8',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password, // hashed automatically by the User model's cast
            'role'     => 'assessor',
        ]);

        $this->info("Assessor account created for {$email}.");
        return self::SUCCESS;
    }
}

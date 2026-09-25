<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('admin:create {email?} {--name=Admin}')]
#[Description('Create an admin user or reset its password')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email') ?? text('Email', required: true);
        $pass = password('Password (min 8 chars)', required: true, validate: fn ($v) => strlen($v) < 8 ? 'Too short' : null);

        User::updateOrCreate(['email' => $email], ['name' => $this->option('name'), 'password' => $pass]);
        $this->info("Admin {$email} is ready.");

        return self::SUCCESS;
    }
}

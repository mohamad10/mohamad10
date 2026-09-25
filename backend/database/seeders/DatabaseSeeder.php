<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\SiteContent;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(SiteContent $content): void
    {
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            ['name' => 'Admin', 'password' => env('ADMIN_PASSWORD', 'password')],
        );

        $content->sync(json_decode(file_get_contents(__DIR__.'/site.json'), true));
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }
        User::firstOrCreate(['email' => 'demo@example.com'], ['name' => 'デモユーザー', 'password' => 'local-demo-password']);
    }
}

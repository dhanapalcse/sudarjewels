<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Production-safe: roles, permissions and placeholder policy pages.
        $this->call([RolesAndPermissionsSeeder::class, PageSeeder::class]);

        // Sample catalogue, users and orders — local development only.
        if (app()->environment('local')) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}

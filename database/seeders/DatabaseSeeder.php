<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Admin Demo', 'email' => 'admin@relasiku.test', 'role' => 'admin'],
            ['name' => 'Pemilik Demo', 'email' => 'owner@relasiku.test', 'role' => 'owner'],
            ['name' => 'CS Demo', 'email' => 'cs@relasiku.test', 'role' => 'cs'],
        ] as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                $account + ['password' => 'DemoCRM2026!', 'active' => true]
            );
        }

        foreach ([
            ['name' => 'Banner Acara', 'category' => 'Percetakan'],
            ['name' => 'Poster Acara', 'category' => 'Percetakan'],
            ['name' => 'Sertifikat', 'category' => 'Percetakan'],
            ['name' => 'Kaus Acara', 'category' => 'Merchandise'],
            ['name' => 'Merchandise Acara', 'category' => 'Merchandise'],
            ['name' => 'Desain Grafis Acara', 'category' => 'Desain'],
        ] as $service) {
            Service::updateOrCreate(['name' => $service['name']], $service + ['active' => true]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $administrator = User::query()->firstOrCreate(
            ['email' => 'admin@munditoys.com'],
            [
                'name' => 'Administrador MundiToys',
                'password' => Hash::make(env('MUNDITOYS_ADMIN_PASSWORD', 'password')),
            ],
        );

        $administrator->syncRoles('Administrador');
    }
}

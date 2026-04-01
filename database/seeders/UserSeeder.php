<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRoleId = Role::query()->where('name', 'admin')->value('id');
        $userRoleId = Role::query()->where('name', 'user')->value('id');

        User::query()->updateOrCreate(
            ['email' => 'admin@erdcore.test'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRoleId,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'user@erdcore.test'],
            [
                'name' => 'Demo Merchant',
                'password' => Hash::make('password'),
                'role_id' => $userRoleId,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'manager@erdcore.test'],
            [
                'name' => 'Demo Store Manager',
                'password' => Hash::make('password'),
                'role_id' => $userRoleId,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'staff@erdcore.test'],
            [
                'name' => 'Demo Store Staff',
                'password' => Hash::make('password'),
                'role_id' => $userRoleId,
            ]
        );
    }
}

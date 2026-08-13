<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshInstallSeederTest extends TestCase
{
    public function test_migrate_fresh_seed_creates_demo_roles_users_and_stores(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dr04-fresh-install-'.uniqid('', true).'.sqlite';
        touch($path);

        try {
            Config::set('database.default', 'sqlite');
            Config::set('database.connections.sqlite.database', $path);
            DB::purge('sqlite');
            DB::reconnect('sqlite');

            $exit = Artisan::call('migrate:fresh', [
                '--seed' => true,
                '--force' => true,
            ]);

            $this->assertSame(0, $exit);
            $this->assertTrue(Schema::hasTable('users'));
            $this->assertTrue(Schema::hasTable('stores'));
            $this->assertTrue(Schema::hasTable('roles'));

            $this->assertTrue(Role::query()->where('name', 'user')->exists());
            $this->assertTrue(Role::query()->where('name', 'admin')->exists());

            foreach ([
                'user@erdcore.test',
                'manager@erdcore.test',
                'staff@erdcore.test',
                'admin@erdcore.test',
            ] as $email) {
                $user = User::query()->where('email', $email)->first();
                $this->assertNotNull($user, $email.' should be seeded');
                $this->assertTrue(Hash::check('password', $user->password));
            }

            $this->assertTrue(Store::query()->where('slug', 'demo-fashion')->exists());
            $this->assertTrue(Store::query()->where('slug', 'demo-digital')->exists());
        } finally {
            Config::set('database.default', 'sqlite');
            Config::set('database.connections.sqlite.database', ':memory:');
            DB::purge('sqlite');
            DB::reconnect('sqlite');
            @unlink($path);
        }
    }
}

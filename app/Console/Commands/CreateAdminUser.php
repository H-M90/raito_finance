<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CreateAdminUser extends Command
{
    protected $signature = 'finance:create-admin {email?}';
    protected $description = 'Create or reset the primary finance system administrator and guarantee the admin role is assigned.';

    public function handle(): int
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            $this->error('جداول الصلاحيات غير موجودة. شغّل أولًا: php artisan migrate --seed');
            return self::FAILURE;
        }

        $role = Role::where('code', 'admin')->first();
        if (! $role) {
            try {
                $this->call('db:seed', [
                    '--class' => PermissionSeeder::class,
                    '--force' => true,
                ]);
            } catch (Throwable $e) {
                $this->error('تعذر إنشاء أدوار وصلاحيات النظام: '.$e->getMessage());
                return self::FAILURE;
            }
            $role = Role::where('code', 'admin')->first();
        }

        if (! $role) {
            $this->error('لم يتم العثور على دور مدير النظام بعد تشغيل الـSeeder.');
            return self::FAILURE;
        }

        $email = $this->argument('email') ?: $this->ask('Admin email', 'admin@raitotec.com');
        $name = $this->ask('Admin name', 'Raito Admin');
        $password = $this->secret('Password');

        if (! $password || mb_strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password, 'is_active' => true, 'role_id' => $role->id]
        );

        $this->info('Administrator is ready and assigned to the admin role.');
        return self::SUCCESS;
    }
}

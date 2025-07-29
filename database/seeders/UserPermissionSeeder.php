<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * 使用者權限種子資料
 * 
 * 建立測試用的使用者和權限配置
 */
class UserPermissionSeeder extends Seeder
{
    /**
     * 執行種子資料
     */
    public function run(): void
    {
        $this->command->info('開始建立測試使用者和權限配置...');

        // 建立管理員使用者
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '系統管理員',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => true,
                'permissions' => [
                    'user.read',
                    'user.update',
                    'user.change_password',
                    'user.list',
                    'user.create',
                    'user.delete',
                    'system.read',
                    'system.server_status',
                    'system.config',
                    'admin.read',
                    'admin.write',
                    'admin.delete',
                ],
            ]
        );

        $this->command->line("建立管理員使用者: {$admin->email}");

        // 建立一般使用者
        $user = User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => '一般使用者',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => false,
                'permissions' => [
                    'user.read',
                    'user.update',
                    'user.change_password',
                ],
            ]
        );

        $this->command->line("建立一般使用者: {$user->email}");

        // 建立進階使用者
        $powerUser = User::updateOrCreate(
            ['email' => 'power@example.com'],
            [
                'name' => '進階使用者',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => false,
                'permissions' => [
                    'user.read',
                    'user.update',
                    'user.change_password',
                    'user.list',
                    'system.read',
                ],
            ]
        );

        $this->command->line("建立進階使用者: {$powerUser->email}");

        // 建立受限使用者
        $limitedUser = User::updateOrCreate(
            ['email' => 'limited@example.com'],
            [
                'name' => '受限使用者',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => false,
                'permissions' => [
                    'user.read',
                ],
            ]
        );

        $this->command->line("建立受限使用者: {$limitedUser->email}");

        // 建立無權限使用者
        $noPermUser = User::updateOrCreate(
            ['email' => 'noperm@example.com'],
            [
                'name' => '無權限使用者',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => false,
                'permissions' => [],
            ]
        );

        $this->command->line("建立無權限使用者: {$noPermUser->email}");

        $this->command->info('測試使用者建立完成！');
        $this->command->line('');
        $this->command->info('測試帳號資訊:');
        $this->command->line('管理員: admin@example.com / password');
        $this->command->line('一般使用者: user@example.com / password');
        $this->command->line('進階使用者: power@example.com / password');
        $this->command->line('受限使用者: limited@example.com / password');
        $this->command->line('無權限使用者: noperm@example.com / password');
    }
}
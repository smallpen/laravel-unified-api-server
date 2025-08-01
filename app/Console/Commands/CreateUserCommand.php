<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * 建立使用者指令
 * 
 * 提供指令列介面來建立新的系統使用者
 */
class CreateUserCommand extends Command
{
    /**
     * 指令簽名
     *
     * @var string
     */
    protected $signature = 'user:create 
                            {--name= : 使用者姓名}
                            {--email= : 電子郵件地址}
                            {--password= : 密碼}
                            {--admin : 設定為管理員}
                            {--permissions=* : 權限列表}
                            {--verified : 設定電子郵件為已驗證}';

    /**
     * 指令描述
     *
     * @var string
     */
    protected $description = '建立新的系統使用者';

    /**
     * 執行指令
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('建立新使用者');
        $this->line('');

        // 取得使用者輸入
        $name = $this->getUserName();
        $email = $this->getUserEmail();
        $password = $this->getUserPassword();
        $isAdmin = $this->getAdminStatus();
        $permissions = $this->getUserPermissions();
        $isVerified = $this->getVerificationStatus();

        // 驗證資料
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            $this->error('資料驗證失敗：');
            foreach ($validator->errors()->all() as $error) {
                $this->line("  - {$error}");
            }
            return 1;
        }

        try {
            // 建立使用者
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => $isVerified ? now() : null,
                'is_admin' => $isAdmin,
                'permissions' => $permissions,
            ]);

            $this->info('使用者建立成功！');
            $this->line('');

            // 顯示使用者資訊
            $this->table(
                ['項目', '值'],
                [
                    ['ID', $user->id],
                    ['姓名', $user->name],
                    ['電子郵件', $user->email],
                    ['管理員', $user->is_admin ? '是' : '否'],
                    ['電子郵件已驗證', $user->email_verified_at ? '是' : '否'],
                    ['權限', implode(', ', $user->permissions ?: ['無'])],
                    ['建立時間', $user->created_at->format('Y-m-d H:i:s')],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            $this->error("建立使用者失敗：{$e->getMessage()}");
            return 1;
        }
    }

    /**
     * 取得使用者姓名
     *
     * @return string
     */
    protected function getUserName(): string
    {
        $name = $this->option('name');
        
        if (!$name) {
            $name = $this->ask('請輸入使用者姓名');
        }

        return $name;
    }

    /**
     * 取得電子郵件地址
     *
     * @return string
     */
    protected function getUserEmail(): string
    {
        $email = $this->option('email');
        
        if (!$email) {
            $email = $this->ask('請輸入電子郵件地址');
        }

        // 檢查電子郵件是否已存在
        while (User::where('email', $email)->exists()) {
            $this->error("電子郵件 '{$email}' 已存在");
            $email = $this->ask('請輸入其他電子郵件地址');
        }

        return $email;
    }

    /**
     * 取得密碼
     *
     * @return string
     */
    protected function getUserPassword(): string
    {
        $password = $this->option('password');
        
        if (!$password) {
            $password = $this->secret('請輸入密碼（至少 8 個字元）');
            
            while (strlen($password) < 8) {
                $this->error('密碼長度至少需要 8 個字元');
                $password = $this->secret('請重新輸入密碼');
            }
        }

        return $password;
    }

    /**
     * 取得管理員狀態
     *
     * @return bool
     */
    protected function getAdminStatus(): bool
    {
        if ($this->option('admin')) {
            return true;
        }

        return $this->confirm('是否設定為管理員？', false);
    }

    /**
     * 取得權限列表
     *
     * @return array
     */
    protected function getUserPermissions(): array
    {
        $permissions = $this->option('permissions');
        
        if (empty($permissions)) {
            $this->line('');
            $this->info('可用的權限類型：');
            $this->line('  使用者權限: user.read, user.update, user.change_password, user.list, user.create, user.delete');
            $this->line('  系統權限: system.read, system.server_status, system.config');
            $this->line('  管理權限: admin.read, admin.write, admin.delete');
            $this->line('');
            
            $permissionInput = $this->ask('請輸入權限列表（用逗號分隔，留空表示無權限）');
            
            if ($permissionInput) {
                $permissions = array_map('trim', explode(',', $permissionInput));
            }
        }

        return array_filter($permissions ?: []);
    }

    /**
     * 取得電子郵件驗證狀態
     *
     * @return bool
     */
    protected function getVerificationStatus(): bool
    {
        if ($this->option('verified')) {
            return true;
        }

        return $this->confirm('是否設定電子郵件為已驗證？', true);
    }
}
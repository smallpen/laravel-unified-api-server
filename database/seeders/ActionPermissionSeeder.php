<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActionPermission;

/**
 * Action權限配置種子資料
 * 
 * 初始化系統的Action權限配置
 */
class ActionPermissionSeeder extends Seeder
{
    /**
     * 執行種子資料
     */
    public function run(): void
    {
        $this->command->info('開始建立Action權限配置...');

        // 讀取權限配置檔案
        $configPath = config_path('action_permissions.json');
        
        if (!file_exists($configPath)) {
            $this->command->warn('權限配置檔案不存在，跳過權限配置初始化');
            return;
        }

        $content = file_get_contents($configPath);
        $permissions = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('權限配置檔案格式錯誤: ' . json_last_error_msg());
            return;
        }

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($permissions as $actionType => $config) {
            $requiredPermissions = $config['permissions'] ?? [];
            $description = $config['description'] ?? null;
            $isActive = $config['is_active'] ?? true;

            // 檢查是否已存在
            $existing = ActionPermission::where('action_type', $actionType)->first();

            if ($existing) {
                // 更新現有配置
                $existing->update([
                    'required_permissions' => $requiredPermissions,
                    'description' => $description,
                    'is_active' => $isActive,
                ]);
                $updatedCount++;
                $this->command->line("更新: {$actionType}");
            } else {
                // 建立新配置
                ActionPermission::create([
                    'action_type' => $actionType,
                    'required_permissions' => $requiredPermissions,
                    'description' => $description,
                    'is_active' => $isActive,
                ]);
                $createdCount++;
                $this->command->line("建立: {$actionType}");
            }
        }

        $this->command->info("Action權限配置完成！建立 {$createdCount} 個，更新 {$updatedCount} 個");
    }
}
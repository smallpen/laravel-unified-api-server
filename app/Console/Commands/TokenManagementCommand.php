<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TokenService;
use App\Models\User;
use Carbon\Carbon;

/**
 * Token 管理命令
 * 
 * 提供 Token 管理的命令列工具
 */
class TokenManagementCommand extends Command
{
    /**
     * 命令名稱和參數
     *
     * @var string
     */
    protected $signature = 'token:manage 
                            {action : 動作類型 (create|revoke|list|cleanup|info)}
                            {--user= : 使用者 ID}
                            {--name= : Token 名稱}
                            {--permissions=* : 權限列表}
                            {--days= : 過期天數}
                            {--token= : Token 字串}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = 'Token 管理工具 - 建立、撤銷、列出和清理 API Token';

    /**
     * Token 服務
     *
     * @var \App\Services\TokenService
     */
    protected TokenService $tokenService;

    /**
     * 建構函式
     *
     * @param \App\Services\TokenService $tokenService
     */
    public function __construct(TokenService $tokenService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
    }

    /**
     * 執行命令
     *
     * @return int
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'create' => $this->createToken(),
            'revoke' => $this->revokeToken(),
            'list' => $this->listTokens(),
            'cleanup' => $this->cleanupTokens(),
            'info' => $this->showTokenInfo(),
            default => $this->showUsage(),
        };
    }

    /**
     * 建立新的 Token
     *
     * @return int
     */
    protected function createToken(): int
    {
        $userId = $this->option('user');
        $name = $this->option('name') ?? 'CLI Generated Token';
        $permissions = $this->option('permissions') ?? [];
        $days = $this->option('days');

        if (!$userId) {
            $this->error('請指定使用者 ID (--user)');
            return 1;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("找不到使用者 ID: {$userId}");
            return 1;
        }

        $expiresAt = $days ? Carbon::now()->addDays((int)$days) : null;

        try {
            $tokenData = $this->tokenService->createToken($user, $name, $permissions, $expiresAt);

            $this->info('Token 建立成功！');
            $this->table(
                ['欄位', '值'],
                [
                    ['Token', $tokenData['token']],
                    ['名稱', $tokenData['model']->name],
                    ['使用者', $user->name . ' (' . $user->email . ')'],
                    ['權限', implode(', ', $tokenData['model']->permissions ?: ['無'])],
                    ['過期時間', $tokenData['model']->expires_at?->format('Y-m-d H:i:s') ?? '永不過期'],
                    ['建立時間', $tokenData['model']->created_at->format('Y-m-d H:i:s')],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            $this->error('建立 Token 失敗: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 撤銷 Token
     *
     * @return int
     */
    protected function revokeToken(): int
    {
        $token = $this->option('token');
        $userId = $this->option('user');
        $name = $this->option('name');

        if ($token) {
            // 撤銷特定 Token
            if ($this->tokenService->revokeToken($token)) {
                $this->info('Token 已成功撤銷');
                return 0;
            } else {
                $this->error('撤銷 Token 失敗，Token 可能不存在或已被撤銷');
                return 1;
            }
        } elseif ($userId && $name) {
            // 撤銷指定使用者的指定名稱 Token
            $user = User::find($userId);
            if (!$user) {
                $this->error("找不到使用者 ID: {$userId}");
                return 1;
            }

            $count = $this->tokenService->revokeTokensByName($user, $name);
            $this->info("已撤銷 {$count} 個名為 '{$name}' 的 Token");
            return 0;
        } elseif ($userId) {
            // 撤銷使用者的所有 Token
            $user = User::find($userId);
            if (!$user) {
                $this->error("找不到使用者 ID: {$userId}");
                return 1;
            }

            if ($this->confirm("確定要撤銷使用者 {$user->name} 的所有 Token 嗎？")) {
                $count = $this->tokenService->revokeAllUserTokens($user);
                $this->info("已撤銷 {$count} 個 Token");
                return 0;
            }
        } else {
            $this->error('請指定要撤銷的 Token (--token) 或使用者 (--user)');
            return 1;
        }

        return 0;
    }

    /**
     * 列出 Token
     *
     * @return int
     */
    protected function listTokens(): int
    {
        $userId = $this->option('user');

        if (!$userId) {
            $this->error('請指定使用者 ID (--user)');
            return 1;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("找不到使用者 ID: {$userId}");
            return 1;
        }

        $tokens = $this->tokenService->getUserTokens($user);

        if ($tokens->isEmpty()) {
            $this->info('該使用者沒有有效的 Token');
            return 0;
        }

        $this->info("使用者 {$user->name} 的 Token 列表：");

        $tableData = $tokens->map(function ($token) {
            return [
                'ID' => $token->id,
                '名稱' => $token->name,
                '權限' => implode(', ', $token->permissions ?: ['無']),
                '最後使用' => $token->last_used_at?->format('Y-m-d H:i:s') ?? '從未使用',
                '過期時間' => $token->expires_at?->format('Y-m-d H:i:s') ?? '永不過期',
                '建立時間' => $token->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        $this->table(
            ['ID', '名稱', '權限', '最後使用', '過期時間', '建立時間'],
            $tableData
        );

        return 0;
    }

    /**
     * 清理過期的 Token
     *
     * @return int
     */
    protected function cleanupTokens(): int
    {
        $this->info('開始清理過期的 Token...');

        $count = $this->tokenService->cleanupExpiredTokens();

        if ($count > 0) {
            $this->info("已清理 {$count} 個過期的 Token");
        } else {
            $this->info('沒有找到過期的 Token');
        }

        return 0;
    }

    /**
     * 顯示 Token 資訊
     *
     * @return int
     */
    protected function showTokenInfo(): int
    {
        $token = $this->option('token');

        if (!$token) {
            $this->error('請指定 Token (--token)');
            return 1;
        }

        $tokenInfo = $this->tokenService->getTokenInfo($token);

        if (!$tokenInfo) {
            $this->error('找不到指定的 Token');
            return 1;
        }

        $user = $tokenInfo->user;
        $remainingDays = $this->tokenService->getTokenRemainingDays($token);

        $this->info('Token 詳細資訊：');
        $this->table(
            ['欄位', '值'],
            [
                ['ID', $tokenInfo->id],
                ['名稱', $tokenInfo->name],
                ['使用者', $user->name . ' (' . $user->email . ')'],
                ['權限', implode(', ', $tokenInfo->permissions ?: ['無'])],
                ['狀態', $tokenInfo->is_active ? '啟用' : '已撤銷'],
                ['是否過期', $this->tokenService->isTokenExpired($token) ? '是' : '否'],
                ['即將過期', $this->tokenService->isTokenExpiringSoon($token) ? '是（7天內）' : '否'],
                ['剩餘天數', $remainingDays !== null ? $remainingDays . ' 天' : '永不過期'],
                ['最後使用', $tokenInfo->last_used_at?->format('Y-m-d H:i:s') ?? '從未使用'],
                ['過期時間', $tokenInfo->expires_at?->format('Y-m-d H:i:s') ?? '永不過期'],
                ['建立時間', $tokenInfo->created_at->format('Y-m-d H:i:s')],
                ['更新時間', $tokenInfo->updated_at->format('Y-m-d H:i:s')],
            ]
        );

        return 0;
    }

    /**
     * 顯示使用說明
     *
     * @return int
     */
    protected function showUsage(): int
    {
        $this->error('無效的動作類型');
        $this->line('');
        $this->line('可用的動作：');
        $this->line('  create   - 建立新的 Token');
        $this->line('  revoke   - 撤銷 Token');
        $this->line('  list     - 列出使用者的 Token');
        $this->line('  cleanup  - 清理過期的 Token');
        $this->line('  info     - 顯示 Token 詳細資訊');
        $this->line('');
        $this->line('範例：');
        $this->line('  php artisan token:manage create --user=1 --name="My Token" --permissions=read,write --days=30');
        $this->line('  php artisan token:manage revoke --token="your-token-here"');
        $this->line('  php artisan token:manage list --user=1');
        $this->line('  php artisan token:manage cleanup');
        $this->line('  php artisan token:manage info --token="your-token-here"');

        return 1;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * API Token 模型
 * 
 * 管理 API 存取權杖的建立、驗證和過期機制
 */
class ApiToken extends Model
{
    use HasFactory;

    /**
     * 資料表名稱
     *
     * @var string
     */
    protected $table = 'api_tokens';

    /**
     * 可批量賦值的屬性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'token_hash',
        'name',
        'expires_at',
        'last_used_at',
        'permissions',
        'is_active',
    ];

    /**
     * 屬性類型轉換
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * 取得擁有此 Token 的使用者
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 建立新的 API Token
     *
     * @param int $userId 使用者 ID
     * @param string $name Token 名稱
     * @param array $permissions 權限陣列
     * @param \Carbon\Carbon|null $expiresAt 過期時間
     * @return array 包含 token 和 model 的陣列
     */
    public static function createToken(int $userId, string $name, array $permissions = [], ?Carbon $expiresAt = null): array
    {
        // 生成隨機 Token
        $plainToken = Str::random(80);
        
        // 建立 Token 記錄
        $apiToken = static::create([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $plainToken),
            'name' => $name,
            'permissions' => $permissions,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        return [
            'token' => $plainToken,
            'model' => $apiToken,
        ];
    }

    /**
     * 驗證 Token 是否有效
     *
     * @param string $token 原始 Token
     * @return bool
     */
    public function validateToken(string $token): bool
    {
        return hash('sha256', $token) === $this->token_hash;
    }

    /**
     * 檢查 Token 是否已過期
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * 檢查 Token 是否有效（未過期且啟用）
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * 更新最後使用時間
     *
     * @return void
     */
    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * 撤銷 Token
     *
     * @return void
     */
    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * 檢查是否有特定權限
     *
     * @param string $permission 權限名稱
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions)) {
            return false;
        }

        return in_array($permission, $this->permissions) || in_array('*', $this->permissions);
    }

    /**
     * 根據 Token 字串查找對應的模型
     *
     * @param string $token 原始 Token
     * @return static|null
     */
    public static function findByToken(string $token): ?static
    {
        $tokenHash = hash('sha256', $token);
        
        return static::where('token_hash', $tokenHash)
            ->where('is_active', true)
            ->first();
    }

    /**
     * 清理過期的 Token
     *
     * @return int 清理的數量
     */
    public static function cleanupExpiredTokens(): int
    {
        return static::where('expires_at', '<', now())
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}
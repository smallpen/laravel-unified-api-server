<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Action權限模型
 * 
 * 管理Action層級的權限配置
 * 
 * @property int $id
 * @property string $action_type Action類型識別碼
 * @property array $required_permissions 所需權限清單
 * @property bool $is_active 是否啟用
 * @property string|null $description 權限描述
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class ActionPermission extends Model
{
    use HasFactory;

    /**
     * 可批量賦值的屬性
     * 
     * @var array<string>
     */
    protected $fillable = [
        'action_type',
        'required_permissions',
        'is_active',
        'description',
    ];

    /**
     * 屬性類型轉換
     * 
     * @var array<string, string>
     */
    protected $casts = [
        'required_permissions' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 根據Action類型查詢權限配置
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $actionType Action類型
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForActionType(Builder $query, string $actionType): Builder
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * 查詢啟用的權限配置
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * 查詢停用的權限配置
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * 檢查是否包含指定權限
     * 
     * @param string $permission 權限名稱
     * @return bool 是否包含該權限
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->required_permissions ?? []);
    }

    /**
     * 新增權限到權限清單
     * 
     * @param string $permission 權限名稱
     * @return bool 是否成功新增
     */
    public function addPermission(string $permission): bool
    {
        if ($this->hasPermission($permission)) {
            return false; // 權限已存在
        }

        $permissions = $this->required_permissions ?? [];
        $permissions[] = $permission;
        $this->required_permissions = $permissions;

        return true;
    }

    /**
     * 從權限清單移除權限
     * 
     * @param string $permission 權限名稱
     * @return bool 是否成功移除
     */
    public function removePermission(string $permission): bool
    {
        if (!$this->hasPermission($permission)) {
            return false; // 權限不存在
        }

        $permissions = $this->required_permissions ?? [];
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->required_permissions = array_values($permissions);

        return true;
    }

    /**
     * 設定權限清單
     * 
     * @param array $permissions 權限陣列
     * @return void
     */
    public function setPermissions(array $permissions): void
    {
        $this->required_permissions = array_values(array_unique($permissions));
    }

    /**
     * 取得權限清單
     * 
     * @return array 權限陣列
     */
    public function getPermissions(): array
    {
        return $this->required_permissions ?? [];
    }

    /**
     * 檢查權限配置是否啟用
     * 
     * @return bool 是否啟用
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * 啟用權限配置
     * 
     * @return void
     */
    public function activate(): void
    {
        $this->is_active = true;
    }

    /**
     * 停用權限配置
     * 
     * @return void
     */
    public function deactivate(): void
    {
        $this->is_active = false;
    }

    /**
     * 根據Action類型查找權限配置
     * 
     * @param string $actionType Action類型
     * @return static|null 權限配置實例
     */
    public static function findByActionType(string $actionType): ?self
    {
        return static::forActionType($actionType)->active()->first();
    }

    /**
     * 建立或更新Action權限配置
     * 
     * @param string $actionType Action類型
     * @param array $permissions 權限陣列
     * @param string|null $description 描述
     * @return static 權限配置實例
     */
    public static function createOrUpdate(string $actionType, array $permissions, ?string $description = null): self
    {
        return static::updateOrCreate(
            ['action_type' => $actionType],
            [
                'required_permissions' => $permissions,
                'description' => $description,
                'is_active' => true,
            ]
        );
    }

    /**
     * 取得所有啟用的Action權限配置
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllActive(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()->get();
    }

    /**
     * 批量同步Action權限配置
     * 
     * @param array $actionPermissions Action權限配置陣列
     * @return int 同步的配置數量
     */
    public static function syncPermissions(array $actionPermissions): int
    {
        $syncCount = 0;

        foreach ($actionPermissions as $actionType => $config) {
            $permissions = $config['permissions'] ?? [];
            $description = $config['description'] ?? null;
            $isActive = $config['is_active'] ?? true;

            static::updateOrCreate(
                ['action_type' => $actionType],
                [
                    'required_permissions' => $permissions,
                    'description' => $description,
                    'is_active' => $isActive,
                ]
            );

            $syncCount++;
        }

        return $syncCount;
    }
}

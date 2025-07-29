<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * 使用者模型
 * 
 * 管理系統使用者的基本資訊和認證功能
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * 可批量賦值的屬性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'permissions',
        'is_admin',
    ];

    /**
     * 需要隱藏的屬性
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 屬性類型轉換
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array',
        'is_admin' => 'boolean',
    ];

    /**
     * 取得使用者的 API Token
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function apiTokens()
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * 檢查使用者是否為管理員
     * 
     * @return bool 是否為管理員
     */
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    /**
     * 取得使用者權限清單
     * 
     * @return array 權限陣列
     */
    public function getPermissions(): array
    {
        return $this->permissions ?? [];
    }

    /**
     * 檢查使用者是否具有指定權限
     * 
     * @param string $permission 權限名稱
     * @return bool 是否具有權限
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }

    /**
     * 檢查使用者是否具有任一指定權限
     * 
     * @param array $permissions 權限陣列
     * @return bool 是否具有任一權限
     */
    public function hasAnyPermission(array $permissions): bool
    {
        $userPermissions = $this->getPermissions();
        
        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 檢查使用者是否具有所有指定權限
     * 
     * @param array $permissions 權限陣列
     * @return bool 是否具有所有權限
     */
    public function hasAllPermissions(array $permissions): bool
    {
        $userPermissions = $this->getPermissions();
        
        foreach ($permissions as $permission) {
            if (!in_array($permission, $userPermissions)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 新增權限到使用者
     * 
     * @param string $permission 權限名稱
     * @return bool 是否成功新增
     */
    public function addPermission(string $permission): bool
    {
        if ($this->hasPermission($permission)) {
            return false; // 權限已存在
        }

        $permissions = $this->getPermissions();
        $permissions[] = $permission;
        $this->permissions = $permissions;

        return true;
    }

    /**
     * 從使用者移除權限
     * 
     * @param string $permission 權限名稱
     * @return bool 是否成功移除
     */
    public function removePermission(string $permission): bool
    {
        if (!$this->hasPermission($permission)) {
            return false; // 權限不存在
        }

        $permissions = $this->getPermissions();
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->permissions = array_values($permissions);

        return true;
    }

    /**
     * 設定使用者權限清單
     * 
     * @param array $permissions 權限陣列
     * @return void
     */
    public function setPermissions(array $permissions): void
    {
        $this->permissions = array_unique($permissions);
    }

    /**
     * 清除所有使用者權限
     * 
     * @return void
     */
    public function clearPermissions(): void
    {
        $this->permissions = [];
    }

    /**
     * 設定使用者為管理員
     * 
     * @return void
     */
    public function makeAdmin(): void
    {
        $this->is_admin = true;
    }

    /**
     * 移除使用者的管理員身份
     * 
     * @return void
     */
    public function removeAdmin(): void
    {
        $this->is_admin = false;
    }
}
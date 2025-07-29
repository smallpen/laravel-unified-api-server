<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * API Token 模型測試
 * 
 * 測試 ApiToken 模型的基本功能，不依賴完整的 Laravel 應用程式
 */
class ApiTokenModelTest extends TestCase
{
    /**
     * 測試 ApiToken 類別是否存在
     */
    public function test_api_token_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Models\ApiToken::class));
    }

    /**
     * 測試 User 類別是否存在
     */
    public function test_user_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Models\User::class));
    }

    /**
     * 測試 ApiToken 類別的方法是否存在
     */
    public function test_api_token_methods_exist(): void
    {
        $reflection = new \ReflectionClass(\App\Models\ApiToken::class);
        
        // 檢查靜態方法
        $this->assertTrue($reflection->hasMethod('createToken'));
        $this->assertTrue($reflection->hasMethod('findByToken'));
        $this->assertTrue($reflection->hasMethod('cleanupExpiredTokens'));
        
        // 檢查實例方法
        $this->assertTrue($reflection->hasMethod('validateToken'));
        $this->assertTrue($reflection->hasMethod('isExpired'));
        $this->assertTrue($reflection->hasMethod('isValid'));
        $this->assertTrue($reflection->hasMethod('updateLastUsed'));
        $this->assertTrue($reflection->hasMethod('revoke'));
        $this->assertTrue($reflection->hasMethod('hasPermission'));
        $this->assertTrue($reflection->hasMethod('user'));
    }

    /**
     * 測試 User 類別的方法是否存在
     */
    public function test_user_methods_exist(): void
    {
        $reflection = new \ReflectionClass(\App\Models\User::class);
        
        // 檢查關聯方法
        $this->assertTrue($reflection->hasMethod('apiTokens'));
    }

    /**
     * 測試 ApiToken 類別的屬性設定
     */
    public function test_api_token_properties(): void
    {
        $reflection = new \ReflectionClass(\App\Models\ApiToken::class);
        
        // 檢查是否有 fillable 屬性
        $this->assertTrue($reflection->hasProperty('fillable'));
        
        // 檢查是否有 casts 屬性
        $this->assertTrue($reflection->hasProperty('casts'));
        
        // 檢查是否有 table 屬性
        $this->assertTrue($reflection->hasProperty('table'));
    }

    /**
     * 測試遷移檔案是否存在
     */
    public function test_migration_files_exist(): void
    {
        $userMigration = 'database/migrations/2024_01_01_000000_create_users_table.php';
        $tokenMigration = 'database/migrations/2024_01_01_000001_create_api_tokens_table.php';
        
        $this->assertFileExists($userMigration);
        $this->assertFileExists($tokenMigration);
    }

    /**
     * 測試工廠檔案是否存在
     */
    public function test_factory_files_exist(): void
    {
        $userFactory = 'database/factories/UserFactory.php';
        $tokenFactory = 'database/factories/ApiTokenFactory.php';
        
        $this->assertFileExists($userFactory);
        $this->assertFileExists($tokenFactory);
    }
}
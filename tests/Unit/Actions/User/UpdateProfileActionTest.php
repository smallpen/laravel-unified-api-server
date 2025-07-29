<?php

namespace Tests\Unit\Actions\User;

use Tests\TestCase;
use App\Actions\User\UpdateProfileAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

/**
 * UpdateProfileAction單元測試
 */
class UpdateProfileActionTest extends TestCase
{
    use RefreshDatabase;

    protected UpdateProfileAction $action;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->action = new UpdateProfileAction();
        $this->user = User::factory()->create([
            'name' => '原始姓名',
            'email' => 'original@example.com',
            'password' => Hash::make('original_password'),
            'email_verified_at' => now(), // 設定初始驗證時間
        ]);
    }

    /**
     * 測試更新使用者姓名
     */
    public function test_execute_updates_user_name(): void
    {
        $request = new Request(['name' => '新姓名']);
        
        $result = $this->action->execute($request, $this->user);
        
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('新姓名', $result['user']['name']);
        $this->assertEquals('個人資料更新成功', $result['message']);
        
        // 驗證資料庫中的資料已更新
        $this->user->refresh();
        $this->assertEquals('新姓名', $this->user->name);
    }

    /**
     * 測試更新使用者電子郵件
     */
    public function test_execute_updates_user_email(): void
    {
        $newEmail = 'newemail@example.com';
        $request = new Request(['email' => $newEmail]);
        
        $result = $this->action->execute($request, $this->user);
        
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($newEmail, $result['user']['email']);
        $this->assertNull($result['user']['email_verified_at']); // 電子郵件驗證應重置
        
        // 驗證資料庫中的資料已更新
        $this->user->refresh();
        $this->assertEquals($newEmail, $this->user->email);
        $this->assertNull($this->user->email_verified_at);
    }

    /**
     * 測試更新使用者密碼
     */
    public function test_execute_updates_user_password(): void
    {
        $newPassword = 'new_password123';
        $request = new Request([
            'password' => $newPassword,
            'password_confirmation' => $newPassword
        ]);
        
        $result = $this->action->execute($request, $this->user);
        
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('message', $result);
        
        // 驗證密碼已更新
        $this->user->refresh();
        $this->assertTrue(Hash::check($newPassword, $this->user->password));
    }

    /**
     * 測試同時更新多個欄位
     */
    public function test_execute_updates_multiple_fields(): void
    {
        $request = new Request([
            'name' => '新姓名',
            'email' => 'newemail@example.com'
        ]);
        
        $result = $this->action->execute($request, $this->user);
        
        $this->assertEquals('新姓名', $result['user']['name']);
        $this->assertEquals('newemail@example.com', $result['user']['email']);
        
        // 驗證資料庫中的資料已更新
        $this->user->refresh();
        $this->assertEquals('新姓名', $this->user->name);
        $this->assertEquals('newemail@example.com', $this->user->email);
    }

    /**
     * 測試電子郵件重複時拋出例外
     */
    public function test_execute_throws_exception_for_duplicate_email(): void
    {
        // 建立另一個使用者
        $otherUser = User::factory()->create(['email' => 'existing@example.com']);
        
        $request = new Request(['email' => 'existing@example.com']);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('此電子郵件已被其他使用者使用');
        $this->expectExceptionCode(422);
        
        $this->action->execute($request, $this->user);
    }

    /**
     * 測試沒有提供更新資料時拋出例外
     */
    public function test_execute_throws_exception_for_no_update_data(): void
    {
        $request = new Request();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('沒有提供要更新的資料');
        $this->expectExceptionCode(422);
        
        $this->action->execute($request, $this->user);
    }

    /**
     * 測試參數驗證 - 有效的姓名
     */
    public function test_validate_passes_with_valid_name(): void
    {
        $request = new Request(['name' => '有效姓名']);
        
        $result = $this->action->validate($request);
        
        $this->assertTrue($result);
    }

    /**
     * 測試參數驗證 - 有效的電子郵件
     */
    public function test_validate_passes_with_valid_email(): void
    {
        $request = new Request(['email' => 'valid@example.com']);
        
        $result = $this->action->validate($request);
        
        $this->assertTrue($result);
    }

    /**
     * 測試參數驗證 - 有效的密碼
     */
    public function test_validate_passes_with_valid_password(): void
    {
        $request = new Request([
            'password' => 'validpassword123',
            'password_confirmation' => 'validpassword123'
        ]);
        
        $result = $this->action->validate($request);
        
        $this->assertTrue($result);
    }

    /**
     * 測試參數驗證 - 姓名太短
     */
    public function test_validate_fails_with_short_name(): void
    {
        $request = new Request(['name' => 'A']);
        
        $this->expectException(ValidationException::class);
        
        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 姓名太長
     */
    public function test_validate_fails_with_long_name(): void
    {
        $request = new Request(['name' => str_repeat('A', 256)]);
        
        $this->expectException(ValidationException::class);
        
        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 無效的電子郵件格式
     */
    public function test_validate_fails_with_invalid_email(): void
    {
        $request = new Request(['email' => 'invalid-email']);
        
        $this->expectException(ValidationException::class);
        
        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 密碼太短
     */
    public function test_validate_fails_with_short_password(): void
    {
        $request = new Request([
            'password' => '123',
            'password_confirmation' => '123'
        ]);
        
        $this->expectException(ValidationException::class);
        
        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 密碼確認不符
     */
    public function test_validate_fails_with_password_mismatch(): void
    {
        $request = new Request([
            'password' => 'password123',
            'password_confirmation' => 'different123'
        ]);
        
        $this->expectException(ValidationException::class);
        
        $this->action->validate($request);
    }

    /**
     * 測試取得所需權限
     */
    public function test_get_required_permissions(): void
    {
        $permissions = $this->action->getRequiredPermissions();
        
        $this->assertIsArray($permissions);
        $this->assertContains('user.update', $permissions);
    }

    /**
     * 測試取得Action類型
     */
    public function test_get_action_type(): void
    {
        $actionType = $this->action->getActionType();
        
        $this->assertEquals('user.update', $actionType);
    }

    /**
     * 測試Action是否啟用
     */
    public function test_is_enabled(): void
    {
        $isEnabled = $this->action->isEnabled();
        
        $this->assertTrue($isEnabled);
    }

    /**
     * 測試取得版本資訊
     */
    public function test_get_version(): void
    {
        $version = $this->action->getVersion();
        
        $this->assertEquals('1.0.0', $version);
    }

    /**
     * 測試取得文件資訊
     */
    public function test_get_documentation(): void
    {
        $documentation = $this->action->getDocumentation();
        
        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('name', $documentation);
        $this->assertArrayHasKey('description', $documentation);
        $this->assertArrayHasKey('parameters', $documentation);
        $this->assertArrayHasKey('responses', $documentation);
        $this->assertArrayHasKey('examples', $documentation);
        
        $this->assertEquals('更新使用者個人資料', $documentation['name']);
    }

    /**
     * 測試文件資訊中的參數規格
     */
    public function test_documentation_parameters(): void
    {
        $documentation = $this->action->getDocumentation();
        
        $this->assertArrayHasKey('name', $documentation['parameters']);
        $this->assertArrayHasKey('email', $documentation['parameters']);
        $this->assertArrayHasKey('password', $documentation['parameters']);
        $this->assertArrayHasKey('password_confirmation', $documentation['parameters']);
        
        $nameParam = $documentation['parameters']['name'];
        $this->assertEquals('string', $nameParam['type']);
        $this->assertFalse($nameParam['required']);
        $this->assertEquals(2, $nameParam['min_length']);
        $this->assertEquals(255, $nameParam['max_length']);
    }

    /**
     * 測試文件資訊中的使用範例
     */
    public function test_documentation_examples(): void
    {
        $documentation = $this->action->getDocumentation();
        
        $this->assertIsArray($documentation['examples']);
        $this->assertGreaterThan(0, count($documentation['examples']));
        
        foreach ($documentation['examples'] as $example) {
            $this->assertArrayHasKey('title', $example);
            $this->assertArrayHasKey('request', $example);
            $this->assertIsString($example['title']);
            $this->assertIsArray($example['request']);
            $this->assertArrayHasKey('action_type', $example['request']);
            $this->assertEquals('user.update', $example['request']['action_type']);
        }
    }
}
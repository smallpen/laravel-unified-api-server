<?php

namespace Tests\Unit\Actions\User;

use Tests\TestCase;
use App\Actions\User\ChangePasswordAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * ChangePasswordAction單元測試
 */
class ChangePasswordActionTest extends TestCase
{
    use RefreshDatabase;

    private ChangePasswordAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ChangePasswordAction();
    }

    /**
     * 測試Action類型
     */
    public function test_action_type(): void
    {
        $this->assertEquals('user.change_password', $this->action->getActionType());
    }

    /**
     * 測試所需權限
     */
    public function test_required_permissions(): void
    {
        $permissions = $this->action->getRequiredPermissions();
        
        $this->assertEquals(['user.change_password'], $permissions);
    }

    /**
     * 測試成功變更密碼
     */
    public function test_successful_password_change(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword123')
        ]);

        $request = Request::create('/', 'POST', [
            'current_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $result = $this->action->execute($request, $user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('updated_at', $result);
        $this->assertEquals('密碼變更成功', $result['message']);

        // 驗證密碼已更新
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertFalse(Hash::check('oldpassword123', $user->password));
    }

    /**
     * 測試當前密碼錯誤
     */
    public function test_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correctpassword')
        ]);

        $request = Request::create('/', 'POST', [
            'current_password' => 'wrongpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('當前密碼不正確');
        $this->expectExceptionCode(422);

        $this->action->execute($request, $user);
    }

    /**
     * 測試新密碼與當前密碼相同
     */
    public function test_new_password_same_as_current(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('samepassword123')
        ]);

        $request = Request::create('/', 'POST', [
            'current_password' => 'samepassword123',
            'new_password' => 'samepassword123',
            'new_password_confirmation' => 'samepassword123'
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('新密碼不能與當前密碼相同');
        $this->expectExceptionCode(422);

        $this->action->execute($request, $user);
    }

    /**
     * 測試參數驗證 - 有效參數
     */
    public function test_validation_passes_with_valid_parameters(): void
    {
        $request = Request::create('/', 'POST', [
            'current_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $result = $this->action->validate($request);

        $this->assertTrue($result);
    }

    /**
     * 測試參數驗證 - 缺少當前密碼
     */
    public function test_validation_fails_without_current_password(): void
    {
        $request = Request::create('/', 'POST', [
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $this->expectException(ValidationException::class);

        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 缺少新密碼
     */
    public function test_validation_fails_without_new_password(): void
    {
        $request = Request::create('/', 'POST', [
            'current_password' => 'oldpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $this->expectException(ValidationException::class);

        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 新密碼太短
     */
    public function test_validation_fails_with_short_new_password(): void
    {
        $request = Request::create('/', 'POST', [
            'current_password' => 'oldpassword123',
            'new_password' => '123', // 太短
            'new_password_confirmation' => '123'
        ]);

        $this->expectException(ValidationException::class);

        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 新密碼確認不符
     */
    public function test_validation_fails_with_password_mismatch(): void
    {
        $request = Request::create('/', 'POST', [
            'current_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'differentpassword123'
        ]);

        $this->expectException(ValidationException::class);

        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 缺少新密碼確認
     */
    public function test_validation_fails_without_password_confirmation(): void
    {
        $request = Request::create('/', 'POST', [
            'current_password' => 'oldpassword123',
            'new_password' => 'newpassword123'
        ]);

        $this->expectException(ValidationException::class);

        $this->action->validate($request);
    }

    /**
     * 測試文件資訊
     */
    public function test_documentation(): void
    {
        $documentation = $this->action->getDocumentation();

        $this->assertIsArray($documentation);
        $this->assertEquals('變更密碼', $documentation['name']);
        $this->assertEquals('變更使用者密碼，需要驗證當前密碼', $documentation['description']);
        $this->assertArrayHasKey('parameters', $documentation);
        $this->assertArrayHasKey('responses', $documentation);
        $this->assertArrayHasKey('examples', $documentation);

        // 檢查參數文件
        $parameters = $documentation['parameters'];
        $this->assertArrayHasKey('current_password', $parameters);
        $this->assertArrayHasKey('new_password', $parameters);
        $this->assertArrayHasKey('new_password_confirmation', $parameters);

        // 檢查所有參數都是必填的
        $this->assertTrue($parameters['current_password']['required']);
        $this->assertTrue($parameters['new_password']['required']);
        $this->assertTrue($parameters['new_password_confirmation']['required']);
    }

    /**
     * 測試密碼變更後資料庫更新
     */
    public function test_password_updated_in_database(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword123')
        ]);

        $originalUpdatedAt = $user->updated_at;

        // 等待一秒確保updated_at會改變
        sleep(1);

        $request = Request::create('/', 'POST', [
            'current_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $this->action->execute($request, $user);

        // 重新載入使用者資料
        $user->refresh();

        // 檢查密碼已更新
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        
        // 檢查updated_at已更新
        $this->assertNotEquals($originalUpdatedAt, $user->updated_at);
    }

    /**
     * 測試回傳資料格式
     */
    public function test_response_format(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword123')
        ]);

        $request = Request::create('/', 'POST', [
            'current_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $result = $this->action->execute($request, $user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('updated_at', $result);
        
        $this->assertIsString($result['message']);
        $this->assertIsString($result['updated_at']);
        
        // 檢查updated_at是ISO格式
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $result['updated_at']);
    }
}
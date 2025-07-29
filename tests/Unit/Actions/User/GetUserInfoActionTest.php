<?php

namespace Tests\Unit\Actions\User;

use Tests\TestCase;
use App\Actions\User\GetUserInfoAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * GetUserInfoAction單元測試
 */
class GetUserInfoActionTest extends TestCase
{
    use RefreshDatabase;

    protected GetUserInfoAction $action;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->action = new GetUserInfoAction();
        $this->user = User::factory()->create([
            'name' => '測試使用者',
            'email' => 'test@example.com',
        ]);
    }

    /**
     * 測試取得當前使用者資訊
     */
    public function test_execute_returns_current_user_info(): void
    {
        $request = new Request();
        
        $result = $this->action->execute($request, $this->user);
        
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($this->user->id, $result['user']['id']);
        $this->assertEquals($this->user->name, $result['user']['name']);
        $this->assertEquals($this->user->email, $result['user']['email']);
        $this->assertArrayHasKey('created_at', $result['user']);
        $this->assertArrayHasKey('updated_at', $result['user']);
    }

    /**
     * 測試取得指定使用者資訊
     */
    public function test_execute_returns_specified_user_info(): void
    {
        $targetUser = User::factory()->create([
            'name' => '目標使用者',
            'email' => 'target@example.com',
        ]);

        $request = new Request(['user_id' => $targetUser->id]);
        
        $result = $this->action->execute($request, $this->user);
        
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($targetUser->id, $result['user']['id']);
        $this->assertEquals($targetUser->name, $result['user']['name']);
        $this->assertEquals($targetUser->email, $result['user']['email']);
    }

    /**
     * 測試查詢不存在的使用者
     */
    public function test_execute_throws_exception_for_nonexistent_user(): void
    {
        $request = new Request(['user_id' => 99999]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('找不到指定的使用者');
        $this->expectExceptionCode(404);
        
        $this->action->execute($request, $this->user);
    }

    /**
     * 測試參數驗證 - 有效的user_id
     */
    public function test_validate_passes_with_valid_user_id(): void
    {
        $request = new Request(['user_id' => 123]);
        
        $result = $this->action->validate($request);
        
        $this->assertTrue($result);
    }

    /**
     * 測試參數驗證 - 無參數
     */
    public function test_validate_passes_with_no_parameters(): void
    {
        $request = new Request();
        
        $result = $this->action->validate($request);
        
        $this->assertTrue($result);
    }

    /**
     * 測試參數驗證 - 無效的user_id
     */
    public function test_validate_fails_with_invalid_user_id(): void
    {
        $request = new Request(['user_id' => 'invalid']);
        
        $this->expectException(ValidationException::class);
        
        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 負數user_id
     */
    public function test_validate_fails_with_negative_user_id(): void
    {
        $request = new Request(['user_id' => -1]);
        
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
        $this->assertContains('user.read', $permissions);
    }

    /**
     * 測試取得Action類型
     */
    public function test_get_action_type(): void
    {
        $actionType = $this->action->getActionType();
        
        $this->assertEquals('user.info', $actionType);
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
        
        $this->assertEquals('取得使用者資訊', $documentation['name']);
        $this->assertIsArray($documentation['parameters']);
        $this->assertIsArray($documentation['responses']);
        $this->assertIsArray($documentation['examples']);
    }

    /**
     * 測試文件資訊中的參數規格
     */
    public function test_documentation_parameters(): void
    {
        $documentation = $this->action->getDocumentation();
        
        $this->assertArrayHasKey('user_id', $documentation['parameters']);
        
        $userIdParam = $documentation['parameters']['user_id'];
        $this->assertEquals('integer', $userIdParam['type']);
        $this->assertFalse($userIdParam['required']);
        $this->assertIsString($userIdParam['description']);
        $this->assertIsInt($userIdParam['example']);
    }

    /**
     * 測試文件資訊中的回應格式
     */
    public function test_documentation_responses(): void
    {
        $documentation = $this->action->getDocumentation();
        
        $this->assertArrayHasKey('success', $documentation['responses']);
        $this->assertArrayHasKey('error', $documentation['responses']);
        
        $successResponse = $documentation['responses']['success'];
        $this->assertEquals('success', $successResponse['status']);
        $this->assertArrayHasKey('data', $successResponse);
        $this->assertArrayHasKey('user', $successResponse['data']);
        
        $errorResponse = $documentation['responses']['error'];
        $this->assertEquals('error', $errorResponse['status']);
        $this->assertArrayHasKey('message', $errorResponse);
        $this->assertArrayHasKey('error_code', $errorResponse);
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
            $this->assertEquals('user.info', $example['request']['action_type']);
        }
    }
}
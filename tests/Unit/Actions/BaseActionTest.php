<?php

namespace Tests\Unit\Actions;

use Tests\TestCase;
use App\Actions\BaseAction;
use App\Contracts\ActionInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\User;

/**
 * BaseAction單元測試
 */
class BaseActionTest extends TestCase
{
    /**
     * 建立測試用的Action實作
     */
    protected function createTestAction(): BaseAction
    {
        return new class extends BaseAction {
            public function getActionType(): string
            {
                return 'test.action';
            }

            public function execute(Request $request, User $user): array
            {
                return ['test' => 'success'];
            }

            protected function getValidationRules(): array
            {
                return [
                    'name' => 'required|string|max:100',
                    'email' => 'required|email',
                ];
            }

            protected function getValidationMessages(): array
            {
                return [
                    'name.required' => '名稱為必填項目',
                    'email.required' => '電子郵件為必填項目',
                    'email.email' => '電子郵件格式不正確',
                ];
            }
        };
    }

    /**
     * 測試Action實作ActionInterface
     */
    public function test_implements_action_interface(): void
    {
        $action = $this->createTestAction();

        $this->assertInstanceOf(ActionInterface::class, $action);
    }

    /**
     * 測試取得Action類型
     */
    public function test_get_action_type(): void
    {
        $action = $this->createTestAction();

        $this->assertEquals('test.action', $action->getActionType());
    }

    /**
     * 測試預設版本號
     */
    public function test_default_version(): void
    {
        $action = $this->createTestAction();

        $this->assertEquals('1.0.0', $action->getVersion());
    }

    /**
     * 測試預設啟用狀態
     */
    public function test_default_enabled_status(): void
    {
        $action = $this->createTestAction();

        $this->assertTrue($action->isEnabled());
    }

    /**
     * 測試預設權限清單為空
     */
    public function test_default_required_permissions(): void
    {
        $action = $this->createTestAction();

        $this->assertEmpty($action->getRequiredPermissions());
    }

    /**
     * 測試參數驗證成功
     */
    public function test_validation_passes(): void
    {
        $action = $this->createTestAction();
        $request = Request::create('/', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $result = $action->validate($request);

        $this->assertTrue($result);
    }

    /**
     * 測試參數驗證失敗
     */
    public function test_validation_fails(): void
    {
        $action = $this->createTestAction();
        $request = Request::create('/', 'POST', [
            'name' => '', // 空值，應該驗證失敗
            'email' => 'invalid-email', // 無效的電子郵件格式
        ]);

        $this->expectException(ValidationException::class);

        $action->validate($request);
    }

    /**
     * 測試無驗證規則時直接通過
     */
    public function test_validation_passes_with_no_rules(): void
    {
        $action = new class extends BaseAction {
            public function getActionType(): string
            {
                return 'test.no.validation';
            }

            public function execute(Request $request, User $user): array
            {
                return ['test' => 'success'];
            }
        };

        $request = Request::create('/', 'POST', []);

        $result = $action->validate($request);

        $this->assertTrue($result);
    }

    /**
     * 測試取得文件資訊
     */
    public function test_get_documentation(): void
    {
        $action = $this->createTestAction();

        $documentation = $action->getDocumentation();

        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('name', $documentation);
        $this->assertArrayHasKey('description', $documentation);
        $this->assertArrayHasKey('version', $documentation);
        $this->assertArrayHasKey('enabled', $documentation);
        $this->assertArrayHasKey('required_permissions', $documentation);
        $this->assertArrayHasKey('parameters', $documentation);
        $this->assertArrayHasKey('responses', $documentation);
        $this->assertArrayHasKey('examples', $documentation);

        $this->assertEquals('test.action', $documentation['name']);
        $this->assertEquals('1.0.0', $documentation['version']);
        $this->assertTrue($documentation['enabled']);
    }

    /**
     * 測試執行Action
     */
    public function test_execute_action(): void
    {
        $action = $this->createTestAction();
        $request = Request::create('/', 'POST', []);
        $user = new User(['id' => 1, 'name' => 'Test User']);

        $result = $action->execute($request, $user);

        $this->assertIsArray($result);
        $this->assertEquals(['test' => 'success'], $result);
    }

    /**
     * 測試自訂版本號的Action
     */
    public function test_custom_version_action(): void
    {
        $action = new class extends BaseAction {
            protected string $version = '2.1.0';

            public function getActionType(): string
            {
                return 'test.custom.version';
            }

            public function execute(Request $request, User $user): array
            {
                return ['version' => $this->getVersion()];
            }
        };

        $this->assertEquals('2.1.0', $action->getVersion());
    }

    /**
     * 測試停用的Action
     */
    public function test_disabled_action(): void
    {
        $action = new class extends BaseAction {
            protected bool $enabled = false;

            public function getActionType(): string
            {
                return 'test.disabled';
            }

            public function execute(Request $request, User $user): array
            {
                return ['status' => 'disabled'];
            }
        };

        $this->assertFalse($action->isEnabled());
    }

    /**
     * 測試有權限要求的Action
     */
    public function test_action_with_permissions(): void
    {
        $action = new class extends BaseAction {
            public function getActionType(): string
            {
                return 'test.with.permissions';
            }

            public function execute(Request $request, User $user): array
            {
                return ['permissions' => $this->getRequiredPermissions()];
            }

            public function getRequiredPermissions(): array
            {
                return ['admin.read', 'admin.write'];
            }
        };

        $permissions = $action->getRequiredPermissions();

        $this->assertEquals(['admin.read', 'admin.write'], $permissions);
    }
}
<?php

namespace Tests\Unit\Actions\User;

use Tests\TestCase;
use App\Actions\User\GetUserListAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * GetUserListAction單元測試
 */
class GetUserListActionTest extends TestCase
{
    use RefreshDatabase;

    private GetUserListAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetUserListAction();
    }

    /**
     * 測試Action類型
     */
    public function test_action_type(): void
    {
        $this->assertEquals('user.list', $this->action->getActionType());
    }

    /**
     * 測試所需權限
     */
    public function test_required_permissions(): void
    {
        $permissions = $this->action->getRequiredPermissions();
        
        $this->assertEquals(['user.list'], $permissions);
    }

    /**
     * 測試基本使用者清單查詢
     */
    public function test_basic_user_list(): void
    {
        // 建立測試使用者
        $users = User::factory()->count(5)->create();
        $currentUser = $users->first();

        $request = Request::create('/', 'POST', []);

        $result = $this->action->execute($request, $currentUser);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('users', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(5, $result['users']);

        // 檢查分頁資訊
        $pagination = $result['pagination'];
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(5, $pagination['total']);
        $this->assertEquals(15, $pagination['per_page']);
    }

    /**
     * 測試分頁功能
     */
    public function test_pagination(): void
    {
        // 建立20個測試使用者
        User::factory()->count(20)->create();
        $currentUser = User::factory()->create();

        $request = Request::create('/', 'POST', [
            'page' => 2,
            'per_page' => 5
        ]);

        $result = $this->action->execute($request, $currentUser);

        $this->assertCount(5, $result['users']);
        $this->assertEquals(2, $result['pagination']['current_page']);
        $this->assertEquals(21, $result['pagination']['total']); // 20 + 1 (currentUser)
        $this->assertEquals(5, $result['pagination']['per_page']);
    }

    /**
     * 測試搜尋功能
     */
    public function test_search_functionality(): void
    {
        // 建立特定名稱的使用者
        User::factory()->create(['name' => '張三', 'email' => 'zhang@example.com']);
        User::factory()->create(['name' => '李四', 'email' => 'li@example.com']);
        User::factory()->create(['name' => '王五', 'email' => 'wang@example.com']);
        $currentUser = User::factory()->create();

        // 搜尋姓名
        $request = Request::create('/', 'POST', [
            'search' => '張三'
        ]);

        $result = $this->action->execute($request, $currentUser);

        $this->assertCount(1, $result['users']);
        $this->assertEquals('張三', $result['users'][0]['name']);

        // 搜尋電子郵件
        $request = Request::create('/', 'POST', [
            'search' => 'li@example.com'
        ]);

        $result = $this->action->execute($request, $currentUser);

        $this->assertCount(1, $result['users']);
        $this->assertEquals('li@example.com', $result['users'][0]['email']);
    }

    /**
     * 測試排序功能
     */
    public function test_sorting(): void
    {
        // 建立使用者，確保有不同的建立時間
        $user1 = User::factory()->create(['name' => 'Alice']);
        sleep(1);
        $user2 = User::factory()->create(['name' => 'Bob']);
        $currentUser = User::factory()->create();

        // 按姓名升序排序
        $request = Request::create('/', 'POST', [
            'sort_by' => 'name',
            'sort_order' => 'asc'
        ]);

        $result = $this->action->execute($request, $currentUser);

        $names = collect($result['users'])->pluck('name')->toArray();
        $this->assertEquals('Alice', $names[0]);
        $this->assertEquals('Bob', $names[1]);

        // 按姓名降序排序
        $request = Request::create('/', 'POST', [
            'sort_by' => 'name',
            'sort_order' => 'desc'
        ]);

        $result = $this->action->execute($request, $currentUser);

        $names = collect($result['users'])->pluck('name')->toArray();
        // 降序排序，所以順序應該相反
        $sortedNames = collect($names)->sort()->reverse()->values()->toArray();
        $this->assertEquals($sortedNames, $names);
    }

    /**
     * 測試參數驗證 - 有效參數
     */
    public function test_validation_passes_with_valid_parameters(): void
    {
        $request = Request::create('/', 'POST', [
            'page' => 1,
            'per_page' => 10,
            'search' => 'test',
            'sort_by' => 'name',
            'sort_order' => 'asc'
        ]);

        $result = $this->action->validate($request);

        $this->assertTrue($result);
    }

    /**
     * 測試參數驗證 - 無效頁碼
     */
    public function test_validation_fails_with_invalid_page(): void
    {
        $request = Request::create('/', 'POST', [
            'page' => 0 // 無效的頁碼
        ]);

        $this->expectException(ValidationException::class);

        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 無效每頁筆數
     */
    public function test_validation_fails_with_invalid_per_page(): void
    {
        $request = Request::create('/', 'POST', [
            'per_page' => 101 // 超過最大值
        ]);

        $this->expectException(ValidationException::class);

        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 無效排序欄位
     */
    public function test_validation_fails_with_invalid_sort_by(): void
    {
        $request = Request::create('/', 'POST', [
            'sort_by' => 'invalid_field'
        ]);

        $this->expectException(ValidationException::class);

        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 無效排序方向
     */
    public function test_validation_fails_with_invalid_sort_order(): void
    {
        $request = Request::create('/', 'POST', [
            'sort_order' => 'invalid_order'
        ]);

        $this->expectException(ValidationException::class);

        $this->action->validate($request);
    }

    /**
     * 測試回傳資料格式
     */
    public function test_response_format(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        $currentUser = User::factory()->create();

        $request = Request::create('/', 'POST', []);

        $result = $this->action->execute($request, $currentUser);

        // 檢查使用者資料格式
        $userData = $result['users'][0];
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('name', $userData);
        $this->assertArrayHasKey('email', $userData);
        $this->assertArrayHasKey('email_verified_at', $userData);
        $this->assertArrayHasKey('created_at', $userData);
        $this->assertArrayHasKey('updated_at', $userData);

        // 檢查分頁資料格式
        $pagination = $result['pagination'];
        $this->assertArrayHasKey('current_page', $pagination);
        $this->assertArrayHasKey('last_page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('from', $pagination);
        $this->assertArrayHasKey('to', $pagination);
        $this->assertArrayHasKey('has_more_pages', $pagination);
    }

    /**
     * 測試文件資訊
     */
    public function test_documentation(): void
    {
        $documentation = $this->action->getDocumentation();

        $this->assertIsArray($documentation);
        $this->assertEquals('取得使用者清單', $documentation['name']);
        $this->assertEquals('分頁查詢使用者清單，支援搜尋和排序功能', $documentation['description']);
        $this->assertArrayHasKey('parameters', $documentation);
        $this->assertArrayHasKey('responses', $documentation);
        $this->assertArrayHasKey('examples', $documentation);
    }

    /**
     * 測試空搜尋結果
     */
    public function test_empty_search_results(): void
    {
        User::factory()->count(3)->create();
        $currentUser = User::factory()->create();

        $request = Request::create('/', 'POST', [
            'search' => 'nonexistent'
        ]);

        $result = $this->action->execute($request, $currentUser);

        $this->assertCount(0, $result['users']);
        $this->assertEquals(0, $result['pagination']['total']);
    }
}
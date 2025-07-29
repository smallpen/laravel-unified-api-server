# Action開發指南和範本

## 概述

本指南提供完整的Action開發流程，包含標準範本、最佳實踐和常見模式的實作方式。

## Action基本結構

### 1. 標準Action範本

```php
<?php

namespace App\Actions;

use App\Contracts\ActionInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExampleAction implements ActionInterface
{
    /**
     * 執行Action邏輯
     *
     * @param Request $request 請求物件
     * @param User $user 已驗證的使用者
     * @return array 執行結果
     * @throws ValidationException
     */
    public function execute(Request $request, User $user): array
    {
        // 1. 驗證輸入參數
        $validatedData = $this->validateRequest($request);
        
        // 2. 執行業務邏輯
        $result = $this->processBusinessLogic($validatedData, $user);
        
        // 3. 回傳結果
        return $result;
    }

    /**
     * 取得Action所需權限
     *
     * @return array 權限清單
     */
    public function getRequiredPermissions(): array
    {
        return ['example.read'];
    }

    /**
     * 取得Action文件資訊
     *
     * @return array 文件結構
     */
    public function getDocumentation(): array
    {
        return [
            'name' => '範例Action',
            'description' => '這是一個範例Action，展示基本的實作結構',
            'version' => '1.0.0',
            'enabled' => true,
            'required_permissions' => $this->getRequiredPermissions(),
            'parameters' => [
                'example_param' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => '範例參數說明',
                    'example' => 'example_value',
                ],
            ],
            'responses' => [
                'success' => [
                    'status' => 'success',
                    'data' => [
                        'result' => '處理結果',
                        'processed_at' => '2024-01-01T12:00:00Z',
                    ],
                ],
                'error' => [
                    'status' => 'error',
                    'message' => '錯誤訊息',
                    'error_code' => 'ERROR_CODE',
                ],
            ],
            'examples' => [
                [
                    'title' => '基本使用範例',
                    'request' => [
                        'action_type' => 'example.action',
                        'example_param' => 'test_value',
                    ],
                    'response' => [
                        'status' => 'success',
                        'data' => [
                            'result' => 'processed_test_value',
                            'processed_at' => '2024-01-01T12:00:00Z',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 驗證請求參數
     *
     * @param Request $request
     * @return array 驗證後的資料
     * @throws ValidationException
     */
    private function validateRequest(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'example_param' => 'required|string|max:255',
        ], [
            'example_param.required' => '範例參數為必填欄位',
            'example_param.string' => '範例參數必須為字串',
            'example_param.max' => '範例參數長度不能超過255個字元',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 執行業務邏輯
     *
     * @param array $data 驗證後的資料
     * @param User $user 使用者物件
     * @return array 處理結果
     */
    private function processBusinessLogic(array $data, User $user): array
    {
        // 實作具體的業務邏輯
        $processedValue = 'processed_' . $data['example_param'];
        
        return [
            'result' => $processedValue,
            'processed_at' => now()->toISOString(),
            'processed_by' => $user->id,
        ];
    }
}
```

### 2. 基礎Action抽象類別

```php
<?php

namespace App\Actions;

use App\Contracts\ActionInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class BaseAction implements ActionInterface
{
    /**
     * Action名稱
     */
    protected string $name = '';

    /**
     * Action描述
     */
    protected string $description = '';

    /**
     * Action版本
     */
    protected string $version = '1.0.0';

    /**
     * 是否啟用
     */
    protected bool $enabled = true;

    /**
     * 驗證規則
     */
    protected array $validationRules = [];

    /**
     * 驗證錯誤訊息
     */
    protected array $validationMessages = [];

    /**
     * 執行Action
     */
    public function execute(Request $request, User $user): array
    {
        try {
            // 記錄Action開始執行
            $this->logActionStart($request, $user);

            // 驗證輸入
            $validatedData = $this->validateInput($request);

            // 執行前置檢查
            $this->beforeExecute($validatedData, $user);

            // 執行主要邏輯
            $result = $this->handle($validatedData, $user);

            // 執行後置處理
            $this->afterExecute($result, $user);

            // 記錄成功執行
            $this->logActionSuccess($result, $user);

            return $result;

        } catch (\Exception $e) {
            // 記錄錯誤
            $this->logActionError($e, $user);
            throw $e;
        }
    }

    /**
     * 主要業務邏輯處理方法（子類別必須實作）
     */
    abstract protected function handle(array $data, User $user): array;

    /**
     * 驗證輸入資料
     */
    protected function validateInput(Request $request): array
    {
        if (empty($this->validationRules)) {
            return $request->all();
        }

        $validator = Validator::make(
            $request->all(),
            $this->validationRules,
            $this->validationMessages
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 執行前置檢查
     */
    protected function beforeExecute(array $data, User $user): void
    {
        // 子類別可以覆寫此方法實作前置檢查
    }

    /**
     * 執行後置處理
     */
    protected function afterExecute(array $result, User $user): void
    {
        // 子類別可以覆寫此方法實作後置處理
    }

    /**
     * 取得Action文件
     */
    public function getDocumentation(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'enabled' => $this->enabled,
            'required_permissions' => $this->getRequiredPermissions(),
            'parameters' => $this->getParameterDocumentation(),
            'responses' => $this->getResponseDocumentation(),
            'examples' => $this->getExamples(),
        ];
    }

    /**
     * 取得參數文件（子類別可覆寫）
     */
    protected function getParameterDocumentation(): array
    {
        return [];
    }

    /**
     * 取得回應文件（子類別可覆寫）
     */
    protected function getResponseDocumentation(): array
    {
        return [
            'success' => [
                'status' => 'success',
                'data' => [],
            ],
            'error' => [
                'status' => 'error',
                'message' => '錯誤訊息',
                'error_code' => 'ERROR_CODE',
            ],
        ];
    }

    /**
     * 取得使用範例（子類別可覆寫）
     */
    protected function getExamples(): array
    {
        return [];
    }

    /**
     * 記錄Action開始執行
     */
    protected function logActionStart(Request $request, User $user): void
    {
        Log::info('Action開始執行', [
            'action' => static::class,
            'user_id' => $user->id,
            'request_data' => $request->all(),
        ]);
    }

    /**
     * 記錄Action成功執行
     */
    protected function logActionSuccess(array $result, User $user): void
    {
        Log::info('Action執行成功', [
            'action' => static::class,
            'user_id' => $user->id,
            'result_keys' => array_keys($result),
        ]);
    }

    /**
     * 記錄Action執行錯誤
     */
    protected function logActionError(\Exception $e, User $user): void
    {
        Log::error('Action執行失敗', [
            'action' => static::class,
            'user_id' => $user->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
```

## 常見Action模式

### 1. CRUD操作Action

#### 建立資源Action
```php
<?php

namespace App\Actions\User;

use App\Actions\BaseAction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserAction extends BaseAction
{
    protected string $name = '建立使用者';
    protected string $description = '建立新的使用者帳號';
    
    protected array $validationRules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
    ];

    protected array $validationMessages = [
        'name.required' => '姓名為必填欄位',
        'email.required' => '電子郵件為必填欄位',
        'email.email' => '電子郵件格式不正確',
        'email.unique' => '此電子郵件已被使用',
        'password.required' => '密碼為必填欄位',
        'password.min' => '密碼長度至少8個字元',
        'password.confirmed' => '密碼確認不一致',
    ];

    public function getRequiredPermissions(): array
    {
        return ['user.create'];
    }

    protected function handle(array $data, User $user): array
    {
        $newUser = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'created_by' => $user->id,
        ]);

        return [
            'user' => $newUser->toArray(),
            'message' => '使用者建立成功',
        ];
    }

    protected function getParameterDocumentation(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'required' => true,
                'description' => '使用者姓名',
                'example' => '張三',
            ],
            'email' => [
                'type' => 'string',
                'required' => true,
                'description' => '電子郵件地址',
                'example' => 'zhang@example.com',
            ],
            'password' => [
                'type' => 'string',
                'required' => true,
                'description' => '密碼（至少8個字元）',
                'example' => 'password123',
            ],
            'password_confirmation' => [
                'type' => 'string',
                'required' => true,
                'description' => '密碼確認',
                'example' => 'password123',
            ],
        ];
    }
}
```

#### 查詢資源Action
```php
<?php

namespace App\Actions\User;

use App\Actions\BaseAction;
use App\Models\User;

class GetUserInfoAction extends BaseAction
{
    protected string $name = '取得使用者資訊';
    protected string $description = '根據使用者ID取得使用者詳細資訊';
    
    protected array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'include_permissions' => 'boolean',
    ];

    public function getRequiredPermissions(): array
    {
        return ['user.read'];
    }

    protected function handle(array $data, User $user): array
    {
        $targetUser = User::findOrFail($data['user_id']);
        
        $result = [
            'id' => $targetUser->id,
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'created_at' => $targetUser->created_at->toISOString(),
            'updated_at' => $targetUser->updated_at->toISOString(),
        ];

        // 根據參數決定是否包含權限資訊
        if ($data['include_permissions'] ?? false) {
            $result['permissions'] = $targetUser->getPermissions();
        }

        return $result;
    }

    protected function beforeExecute(array $data, User $user): void
    {
        // 檢查是否有權限查看其他使用者的資訊
        if ($data['user_id'] !== $user->id && !$user->hasPermission('admin.read')) {
            throw new \Exception('無權限查看其他使用者資訊');
        }
    }
}
```

### 2. 分頁查詢Action

```php
<?php

namespace App\Actions\User;

use App\Actions\BaseAction;
use App\Models\User;

class ListUsersAction extends BaseAction
{
    protected string $name = '使用者清單';
    protected string $description = '取得使用者清單，支援分頁和搜尋';
    
    protected array $validationRules = [
        'page' => 'integer|min:1',
        'per_page' => 'integer|min:1|max:100',
        'search' => 'string|max:255',
        'sort_by' => 'string|in:id,name,email,created_at',
        'sort_order' => 'string|in:asc,desc',
        'status' => 'string|in:active,inactive',
    ];

    public function getRequiredPermissions(): array
    {
        return ['user.list'];
    }

    protected function handle(array $data, User $user): array
    {
        $query = User::query();

        // 搜尋功能
        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 狀態篩選
        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        // 排序
        $sortBy = $data['sort_by'] ?? 'created_at';
        $sortOrder = $data['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // 分頁
        $perPage = $data['per_page'] ?? 15;
        $page = $data['page'] ?? 1;
        
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'users' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ];
    }
}
```

### 3. 檔案處理Action

```php
<?php

namespace App\Actions\File;

use App\Actions\BaseAction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadFileAction extends BaseAction
{
    protected string $name = '檔案上傳';
    protected string $description = '上傳檔案到伺服器';
    
    protected array $validationRules = [
        'file_data' => 'required|string',
        'file_name' => 'required|string|max:255',
        'file_type' => 'required|string|max:100',
        'folder' => 'string|max:100',
    ];

    public function getRequiredPermissions(): array
    {
        return ['file.upload'];
    }

    protected function handle(array $data, User $user): array
    {
        // 解碼Base64檔案資料
        $fileContent = base64_decode($data['file_data']);
        
        if ($fileContent === false) {
            throw new \Exception('檔案資料格式不正確');
        }

        // 驗證檔案大小
        $maxSize = 10 * 1024 * 1024; // 10MB
        if (strlen($fileContent) > $maxSize) {
            throw new \Exception('檔案大小超過限制（10MB）');
        }

        // 驗證檔案類型
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'text/plain'];
        if (!in_array($data['file_type'], $allowedTypes)) {
            throw new \Exception('不支援的檔案類型');
        }

        // 生成唯一檔案名稱
        $extension = $this->getExtensionFromMimeType($data['file_type']);
        $fileName = Str::uuid() . '.' . $extension;
        
        // 確定儲存路徑
        $folder = $data['folder'] ?? 'uploads';
        $filePath = $folder . '/' . $fileName;

        // 儲存檔案
        Storage::disk('public')->put($filePath, $fileContent);

        // 記錄檔案資訊到資料庫
        $fileRecord = \App\Models\File::create([
            'original_name' => $data['file_name'],
            'stored_name' => $fileName,
            'file_path' => $filePath,
            'file_type' => $data['file_type'],
            'file_size' => strlen($fileContent),
            'uploaded_by' => $user->id,
        ]);

        return [
            'file_id' => $fileRecord->id,
            'file_url' => Storage::disk('public')->url($filePath),
            'original_name' => $data['file_name'],
            'file_size' => strlen($fileContent),
            'uploaded_at' => $fileRecord->created_at->toISOString(),
        ];
    }

    private function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
        ];

        return $mimeToExt[$mimeType] ?? 'bin';
    }
}
```

### 4. 批次處理Action

```php
<?php

namespace App\Actions\User;

use App\Actions\BaseAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BatchUpdateUsersAction extends BaseAction
{
    protected string $name = '批次更新使用者';
    protected string $description = '批次更新多個使用者的資料';
    
    protected array $validationRules = [
        'user_ids' => 'required|array|min:1|max:100',
        'user_ids.*' => 'integer|exists:users,id',
        'updates' => 'required|array',
        'updates.status' => 'string|in:active,inactive',
        'updates.department' => 'string|max:100',
    ];

    public function getRequiredPermissions(): array
    {
        return ['user.batch_update'];
    }

    protected function handle(array $data, User $user): array
    {
        $userIds = $data['user_ids'];
        $updates = $data['updates'];
        
        // 添加更新時間和更新者
        $updates['updated_at'] = now();
        $updates['updated_by'] = $user->id;

        DB::beginTransaction();
        
        try {
            // 執行批次更新
            $affectedRows = User::whereIn('id', $userIds)->update($updates);
            
            // 記錄操作日誌
            foreach ($userIds as $userId) {
                \App\Models\UserLog::create([
                    'user_id' => $userId,
                    'action' => 'batch_update',
                    'changes' => $updates,
                    'performed_by' => $user->id,
                ]);
            }
            
            DB::commit();
            
            return [
                'affected_users' => $affectedRows,
                'updated_fields' => array_keys($updates),
                'message' => "成功更新 {$affectedRows} 個使用者",
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function beforeExecute(array $data, User $user): void
    {
        // 檢查是否有權限更新所有指定的使用者
        $restrictedUsers = User::whereIn('id', $data['user_ids'])
            ->where('role', 'admin')
            ->count();
            
        if ($restrictedUsers > 0 && !$user->hasPermission('admin.manage')) {
            throw new \Exception('無權限更新管理員使用者');
        }
    }
}
```

## Action測試

### 1. 單元測試範本

```php
<?php

namespace Tests\Unit\Actions;

use App\Actions\User\GetUserInfoAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class GetUserInfoActionTest extends TestCase
{
    use RefreshDatabase;

    private GetUserInfoAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetUserInfoAction();
    }

    public function test_can_get_user_info()
    {
        // 準備測試資料
        $user = User::factory()->create();
        $targetUser = User::factory()->create([
            'name' => '測試使用者',
            'email' => 'test@example.com',
        ]);

        // 建立請求
        $request = new Request([
            'user_id' => $targetUser->id,
        ]);

        // 執行Action
        $result = $this->action->execute($request, $user);

        // 驗證結果
        $this->assertEquals($targetUser->id, $result['id']);
        $this->assertEquals('測試使用者', $result['name']);
        $this->assertEquals('test@example.com', $result['email']);
    }

    public function test_validation_fails_with_invalid_user_id()
    {
        $user = User::factory()->create();
        
        $request = new Request([
            'user_id' => 'invalid',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->action->execute($request, $user);
    }

    public function test_required_permissions()
    {
        $permissions = $this->action->getRequiredPermissions();
        $this->assertContains('user.read', $permissions);
    }

    public function test_documentation_structure()
    {
        $doc = $this->action->getDocumentation();
        
        $this->assertArrayHasKey('name', $doc);
        $this->assertArrayHasKey('description', $doc);
        $this->assertArrayHasKey('parameters', $doc);
        $this->assertArrayHasKey('responses', $doc);
    }
}
```

### 2. 功能測試範本

```php
<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActionsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_user_management_flow()
    {
        // 建立管理員使用者
        $admin = User::factory()->create();
        $admin->givePermissionTo(['user.create', 'user.read', 'user.update', 'user.delete']);
        
        // 建立Token
        $token = $admin->createToken('test-token')->plainTextToken;

        // 1. 建立使用者
        $createResponse = $this->postJson('/api/', [
            'action_type' => 'user.create',
            'name' => '新使用者',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $createResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $userId = $createResponse->json('data.user.id');

        // 2. 取得使用者資訊
        $getResponse = $this->postJson('/api/', [
            'action_type' => 'user.info',
            'user_id' => $userId,
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $getResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'name' => '新使用者',
                    'email' => 'new@example.com',
                ],
            ]);

        // 3. 更新使用者
        $updateResponse = $this->postJson('/api/', [
            'action_type' => 'user.update',
            'user_id' => $userId,
            'name' => '更新後的名稱',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // 4. 刪除使用者
        $deleteResponse = $this->postJson('/api/', [
            'action_type' => 'user.delete',
            'user_id' => $userId,
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $deleteResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }
}
```

## 最佳實踐

### 1. 命名規範
- Action類別名稱使用動詞+名詞的格式，如 `CreateUserAction`
- Action類型使用點號分隔的格式，如 `user.create`
- 方法名稱使用駝峰命名法

### 2. 錯誤處理
- 使用適當的例外類型
- 提供清晰的錯誤訊息
- 記錄詳細的錯誤日誌

### 3. 效能考量
- 避免在Action中執行耗時操作
- 使用資料庫查詢優化
- 考慮使用快取機制

### 4. 安全性
- 始終驗證輸入參數
- 檢查使用者權限
- 避免資訊洩漏

### 5. 可維護性
- 保持Action職責單一
- 使用依賴注入
- 撰寫完整的測試

這個指南提供了完整的Action開發流程和範本，幫助開發者快速建立高品質的Action處理器。
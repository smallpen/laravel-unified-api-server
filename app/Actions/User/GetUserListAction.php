<?php

namespace App\Actions\User;

use App\Actions\BaseAction;
use Illuminate\Http\Request;
use App\Models\User;

/**
 * 取得使用者清單Action
 * 
 * 提供分頁查詢使用者清單的功能，支援搜尋和排序
 */
class GetUserListAction extends BaseAction
{
    /**
     * 取得Action的唯一識別碼
     * 
     * @return string Action類型識別碼
     */
    public function getActionType(): string
    {
        return 'user.list';
    }

    /**
     * 執行取得使用者清單的處理邏輯
     * 
     * @param \Illuminate\Http\Request $request 請求物件
     * @param \App\Models\User $user 已驗證的使用者
     * @return array 使用者清單陣列
     * @throws \Exception 當處理過程發生錯誤時拋出例外
     */
    public function execute(Request $request, User $user): array
    {
        // 驗證請求參數
        $this->validate($request);

        // 取得查詢參數
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        // 建立查詢
        $query = User::query();

        // 搜尋功能
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 排序
        $query->orderBy($sortBy, $sortOrder);

        // 分頁查詢
        $users = $query->paginate($perPage, ['*'], 'page', $page);

        // 格式化使用者資料
        $formattedUsers = $users->items();
        $userData = collect($formattedUsers)->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ];
        });

        $this->logInfo('使用者清單查詢完成', [
            'user_id' => $user->id,
            'search' => $search,
            'total_results' => $users->total(),
            'current_page' => $users->currentPage(),
        ]);

        return [
            'users' => $userData,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'has_more_pages' => $users->hasMorePages(),
            ]
        ];
    }

    /**
     * 取得此Action所需的權限清單
     * 
     * @return array 權限名稱陣列
     */
    public function getRequiredPermissions(): array
    {
        return ['user.list'];
    }

    /**
     * 取得驗證規則
     * 
     * @return array 驗證規則陣列
     */
    protected function getValidationRules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|string|max:255',
            'sort_by' => 'sometimes|string|in:id,name,email,created_at,updated_at',
            'sort_order' => 'sometimes|string|in:asc,desc'
        ];
    }

    /**
     * 取得驗證錯誤訊息
     * 
     * @return array 錯誤訊息陣列
     */
    protected function getValidationMessages(): array
    {
        return [
            'page.integer' => '頁碼必須為整數',
            'page.min' => '頁碼必須大於0',
            'per_page.integer' => '每頁筆數必須為整數',
            'per_page.min' => '每頁筆數必須大於0',
            'per_page.max' => '每頁筆數不能超過100',
            'search.string' => '搜尋關鍵字必須為字串',
            'search.max' => '搜尋關鍵字不能超過255個字元',
            'sort_by.string' => '排序欄位必須為字串',
            'sort_by.in' => '排序欄位必須為：id、name、email、created_at 或 updated_at',
            'sort_order.string' => '排序方向必須為字串',
            'sort_order.in' => '排序方向必須為：asc 或 desc'
        ];
    }

    /**
     * 取得參數文件
     * 
     * @return array 參數文件陣列
     */
    protected function getParameterDocumentation(): array
    {
        return [
            'page' => [
                'type' => 'integer',
                'required' => false,
                'description' => '頁碼',
                'example' => 1,
                'default' => 1,
                'min' => 1
            ],
            'per_page' => [
                'type' => 'integer',
                'required' => false,
                'description' => '每頁筆數',
                'example' => 15,
                'default' => 15,
                'min' => 1,
                'max' => 100
            ],
            'search' => [
                'type' => 'string',
                'required' => false,
                'description' => '搜尋關鍵字（搜尋姓名或電子郵件）',
                'example' => '張三',
                'max_length' => 255
            ],
            'sort_by' => [
                'type' => 'string',
                'required' => false,
                'description' => '排序欄位',
                'example' => 'created_at',
                'default' => 'created_at',
                'enum' => ['id', 'name', 'email', 'created_at', 'updated_at']
            ],
            'sort_order' => [
                'type' => 'string',
                'required' => false,
                'description' => '排序方向',
                'example' => 'desc',
                'default' => 'desc',
                'enum' => ['asc', 'desc']
            ]
        ];
    }

    /**
     * 取得回應文件
     * 
     * @return array 回應文件陣列
     */
    protected function getResponseDocumentation(): array
    {
        return [
            'success' => [
                'status' => 'success',
                'data' => [
                    'users' => [
                        [
                            'id' => 1,
                            'name' => '張三',
                            'email' => 'user1@example.com',
                            'email_verified_at' => '2024-01-01T00:00:00.000000Z',
                            'created_at' => '2024-01-01T00:00:00.000000Z',
                            'updated_at' => '2024-01-01T00:00:00.000000Z'
                        ]
                    ],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 5,
                        'per_page' => 15,
                        'total' => 67,
                        'from' => 1,
                        'to' => 15,
                        'has_more_pages' => true
                    ]
                ]
            ],
            'error' => [
                'status' => 'error',
                'message' => '參數驗證失敗',
                'error_code' => 'VALIDATION_ERROR'
            ]
        ];
    }

    /**
     * 取得使用範例
     * 
     * @return array 使用範例陣列
     */
    protected function getExamples(): array
    {
        return [
            [
                'title' => '取得第一頁使用者清單',
                'request' => [
                    'action_type' => 'user.list'
                ]
            ],
            [
                'title' => '搜尋使用者',
                'request' => [
                    'action_type' => 'user.list',
                    'search' => '張三',
                    'per_page' => 10
                ]
            ],
            [
                'title' => '按姓名排序',
                'request' => [
                    'action_type' => 'user.list',
                    'sort_by' => 'name',
                    'sort_order' => 'asc',
                    'page' => 2
                ]
            ]
        ];
    }

    /**
     * 取得Action的文件資訊
     * 
     * @return array 文件資訊陣列
     */
    public function getDocumentation(): array
    {
        return array_merge(parent::getDocumentation(), [
            'name' => '取得使用者清單',
            'description' => '分頁查詢使用者清單，支援搜尋和排序功能',
        ]);
    }
}
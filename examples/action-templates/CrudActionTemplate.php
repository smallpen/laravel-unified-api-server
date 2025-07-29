<?php

namespace App\Actions\Examples;

use App\Actions\BaseAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * CRUD操作Action範本
 * 
 * 這個範本展示了如何實作基本的CRUD操作Action。
 * 包含建立、讀取、更新、刪除等常見操作模式。
 */
class CrudActionTemplate extends BaseAction
{
    protected string $name = 'CRUD操作範本';
    protected string $description = '展示CRUD操作的Action實作範本';
    protected string $version = '1.0.0';

    /**
     * 根據操作類型設定不同的驗證規則
     */
    protected function getValidationRules(string $operation): array
    {
        switch ($operation) {
            case 'create':
                return [
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|unique:users,email',
                    'description' => 'nullable|string|max:1000',
                ];
                
            case 'read':
                return [
                    'id' => 'required|integer|exists:users,id',
                    'include_relations' => 'boolean',
                ];
                
            case 'update':
                return [
                    'id' => 'required|integer|exists:users,id',
                    'name' => 'sometimes|string|max:255',
                    'email' => 'sometimes|email|unique:users,email,{id}',
                    'description' => 'nullable|string|max:1000',
                ];
                
            case 'delete':
                return [
                    'id' => 'required|integer|exists:users,id',
                    'force_delete' => 'boolean',
                ];
                
            case 'list':
                return [
                    'page' => 'integer|min:1',
                    'per_page' => 'integer|min:1|max:100',
                    'search' => 'nullable|string|max:255',
                    'sort_by' => 'string|in:id,name,email,created_at',
                    'sort_order' => 'string|in:asc,desc',
                    'filters' => 'array',
                ];
                
            default:
                return [];
        }
    }

    /**
     * 建立資源
     */
    protected function handleCreate(array $data, User $user): array
    {
        DB::beginTransaction();
        
        try {
            // 建立新資源
            $resource = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'description' => $data['description'] ?? null,
                'created_by' => $user->id,
            ]);

            // 執行後續處理
            $this->afterCreate($resource, $data, $user);
            
            DB::commit();

            return [
                'id' => $resource->id,
                'message' => '資源建立成功',
                'resource' => $resource->toArray(),
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 讀取資源
     */
    protected function handleRead(array $data, User $user): array
    {
        $query = User::where('id', $data['id']);
        
        // 根據參數決定是否載入關聯資料
        if ($data['include_relations'] ?? false) {
            $query->with(['profile', 'permissions']);
        }
        
        $resource = $query->firstOrFail();
        
        // 檢查讀取權限
        $this->checkReadPermission($resource, $user);
        
        return [
            'resource' => $resource->toArray(),
            'meta' => [
                'last_updated' => $resource->updated_at->toISOString(),
                'can_edit' => $this->canEdit($resource, $user),
                'can_delete' => $this->canDelete($resource, $user),
            ],
        ];
    }

    /**
     * 更新資源
     */
    protected function handleUpdate(array $data, User $user): array
    {
        $resource = User::findOrFail($data['id']);
        
        // 檢查更新權限
        $this->checkUpdatePermission($resource, $user);
        
        DB::beginTransaction();
        
        try {
            // 記錄變更前的狀態
            $originalData = $resource->toArray();
            
            // 更新資源
            $updateData = array_intersect_key($data, array_flip([
                'name', 'email', 'description'
            ]));
            
            $updateData['updated_by'] = $user->id;
            $resource->update($updateData);
            
            // 記錄變更歷史
            $this->logChanges($resource, $originalData, $updateData, $user);
            
            // 執行後續處理
            $this->afterUpdate($resource, $data, $user);
            
            DB::commit();

            return [
                'message' => '資源更新成功',
                'resource' => $resource->fresh()->toArray(),
                'changes' => array_keys($updateData),
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 刪除資源
     */
    protected function handleDelete(array $data, User $user): array
    {
        $resource = User::findOrFail($data['id']);
        
        // 檢查刪除權限
        $this->checkDeletePermission($resource, $user);
        
        DB::beginTransaction();
        
        try {
            // 執行前置檢查
            $this->beforeDelete($resource, $user);
            
            // 執行刪除
            if ($data['force_delete'] ?? false) {
                $resource->forceDelete();
                $deleteType = '永久刪除';
            } else {
                $resource->delete();
                $deleteType = '軟刪除';
            }
            
            // 記錄刪除操作
            $this->logDeletion($resource, $deleteType, $user);
            
            DB::commit();

            return [
                'message' => "資源{$deleteType}成功",
                'deleted_id' => $data['id'],
                'delete_type' => $deleteType,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 列出資源
     */
    protected function handleList(array $data, User $user): array
    {
        $query = User::query();
        
        // 套用搜尋條件
        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // 套用篩選條件
        if (!empty($data['filters'])) {
            foreach ($data['filters'] as $field => $value) {
                if (in_array($field, ['status', 'type', 'category'])) {
                    $query->where($field, $value);
                }
            }
        }
        
        // 套用排序
        $sortBy = $data['sort_by'] ?? 'created_at';
        $sortOrder = $data['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        // 執行分頁查詢
        $perPage = $data['per_page'] ?? 15;
        $page = $data['page'] ?? 1;
        
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        
        return [
            'resources' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
            'meta' => [
                'search_term' => $data['search'] ?? null,
                'applied_filters' => $data['filters'] ?? [],
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ];
    }

    /**
     * 權限檢查方法
     */
    protected function checkReadPermission(Model $resource, User $user): void
    {
        // 實作讀取權限檢查邏輯
        if (!$this->canRead($resource, $user)) {
            throw new \Exception('無權限讀取此資源');
        }
    }

    protected function checkUpdatePermission(Model $resource, User $user): void
    {
        if (!$this->canEdit($resource, $user)) {
            throw new \Exception('無權限更新此資源');
        }
    }

    protected function checkDeletePermission(Model $resource, User $user): void
    {
        if (!$this->canDelete($resource, $user)) {
            throw new \Exception('無權限刪除此資源');
        }
    }

    /**
     * 權限判斷方法
     */
    protected function canRead(Model $resource, User $user): bool
    {
        // 實作讀取權限邏輯
        return $user->hasPermission('resource.read') || 
               $resource->created_by === $user->id;
    }

    protected function canEdit(Model $resource, User $user): bool
    {
        return $user->hasPermission('resource.update') || 
               $resource->created_by === $user->id;
    }

    protected function canDelete(Model $resource, User $user): bool
    {
        return $user->hasPermission('resource.delete') || 
               ($resource->created_by === $user->id && 
                $user->hasPermission('resource.delete_own'));
    }

    /**
     * 生命週期鉤子方法
     */
    protected function afterCreate(Model $resource, array $data, User $user): void
    {
        // 建立後的處理邏輯
        // 例如：發送通知、更新快取、記錄日誌等
    }

    protected function afterUpdate(Model $resource, array $data, User $user): void
    {
        // 更新後的處理邏輯
    }

    protected function beforeDelete(Model $resource, User $user): void
    {
        // 刪除前的檢查邏輯
        // 例如：檢查是否有關聯資料、是否允許刪除等
    }

    /**
     * 日誌記錄方法
     */
    protected function logChanges(Model $resource, array $original, array $changes, User $user): void
    {
        // 記錄變更歷史
        // \App\Models\ChangeLog::create([
        //     'resource_type' => get_class($resource),
        //     'resource_id' => $resource->id,
        //     'original_data' => $original,
        //     'changed_data' => $changes,
        //     'changed_by' => $user->id,
        // ]);
    }

    protected function logDeletion(Model $resource, string $deleteType, User $user): void
    {
        // 記錄刪除操作
        // \App\Models\DeletionLog::create([
        //     'resource_type' => get_class($resource),
        //     'resource_id' => $resource->id,
        //     'delete_type' => $deleteType,
        //     'deleted_by' => $user->id,
        //     'deleted_data' => $resource->toArray(),
        // ]);
    }

    /**
     * 主要處理方法 - 根據操作類型分發到對應的處理方法
     */
    protected function handle(array $data, User $user): array
    {
        $operation = $data['operation'] ?? 'read';
        
        // 動態設定驗證規則
        $this->validationRules = $this->getValidationRules($operation);
        
        switch ($operation) {
            case 'create':
                return $this->handleCreate($data, $user);
            case 'read':
                return $this->handleRead($data, $user);
            case 'update':
                return $this->handleUpdate($data, $user);
            case 'delete':
                return $this->handleDelete($data, $user);
            case 'list':
                return $this->handleList($data, $user);
            default:
                throw new \Exception("不支援的操作類型：{$operation}");
        }
    }

    /**
     * 取得所需權限
     */
    public function getRequiredPermissions(): array
    {
        return ['resource.read']; // 基本權限，具體權限在各方法中檢查
    }

    /**
     * 取得參數文件
     */
    protected function getParameterDocumentation(): array
    {
        return [
            'operation' => [
                'type' => 'string',
                'required' => true,
                'description' => '操作類型',
                'enum' => ['create', 'read', 'update', 'delete', 'list'],
                'example' => 'read',
            ],
            'id' => [
                'type' => 'integer',
                'required' => false,
                'description' => '資源ID（read、update、delete操作時必填）',
                'example' => 123,
            ],
            'name' => [
                'type' => 'string',
                'required' => false,
                'description' => '資源名稱（create、update操作時使用）',
                'example' => '範例資源',
            ],
            // 其他參數...
        ];
    }
}
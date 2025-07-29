<?php

namespace App\Services;

use App\Contracts\ActionInterface;
use App\Events\ActionRegistryUpdated;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

/**
 * Action註冊系統
 * 
 * 負責Action類別的註冊、查找和自動發現
 * 提供Action實例化和管理功能
 */
class ActionRegistry
{
    /**
     * 已註冊的Action清單
     * 
     * @var array<string, string> action_type => class_name
     */
    protected array $actions = [];

    /**
     * Action實例快取
     * 
     * @var array<string, ActionInterface>
     */
    protected array $instances = [];

    /**
     * Action掃描目錄
     * 
     * @var array<string>
     */
    protected array $scanDirectories = [
        'app/Actions',
        'app/Http/Actions',
    ];

    /**
     * 註冊Action類別
     * 
     * @param string $actionType Action類型識別碼
     * @param string $actionClass Action類別名稱
     * @throws \InvalidArgumentException 當Action類別不存在或未實作ActionInterface時拋出
     */
    public function register(string $actionType, string $actionClass): void
    {
        // 驗證類別是否存在
        if (!class_exists($actionClass)) {
            throw new InvalidArgumentException("Action類別不存在: {$actionClass}");
        }

        // 驗證類別是否實作ActionInterface
        if (!is_subclass_of($actionClass, ActionInterface::class)) {
            throw new InvalidArgumentException("Action類別必須實作ActionInterface: {$actionClass}");
        }

        // 註冊Action
        $this->actions[$actionType] = $actionClass;

        Log::info('Action已註冊', [
            'action_type' => $actionType,
            'action_class' => $actionClass,
        ]);

        // 觸發Action註冊更新事件
        event(new ActionRegistryUpdated('register', [$actionType]));
    }

    /**
     * 解析並取得Action實例
     * 
     * @param string $actionType Action類型識別碼
     * @return \App\Contracts\ActionInterface Action實例
     * @throws \InvalidArgumentException 當Action不存在時拋出
     */
    public function resolve(string $actionType): ActionInterface
    {
        // 檢查Action是否已註冊
        if (!$this->hasAction($actionType)) {
            throw new InvalidArgumentException("找不到指定的Action: {$actionType}");
        }

        // 檢查是否已有快取的實例
        if (isset($this->instances[$actionType])) {
            return $this->instances[$actionType];
        }

        // 建立新的Action實例
        $actionClass = $this->actions[$actionType];
        $instance = app($actionClass);

        // 快取實例
        $this->instances[$actionType] = $instance;

        return $instance;
    }

    /**
     * 取得所有已註冊的Action
     * 
     * @return array<string, string> action_type => class_name
     */
    public function getAllActions(): array
    {
        return $this->actions;
    }

    /**
     * 檢查Action是否存在
     * 
     * @param string $actionType Action類型識別碼
     * @return bool 是否存在
     */
    public function hasAction(string $actionType): bool
    {
        return isset($this->actions[$actionType]);
    }

    /**
     * 移除已註冊的Action
     * 
     * @param string $actionType Action類型識別碼
     */
    public function unregister(string $actionType): void
    {
        unset($this->actions[$actionType]);
        unset($this->instances[$actionType]);

        Log::info('Action已移除註冊', [
            'action_type' => $actionType,
        ]);
    }

    /**
     * 自動發現並註冊Action類別
     * 
     * 掃描指定目錄中的所有PHP檔案，自動註冊實作ActionInterface的類別
     */
    public function autoDiscoverActions(): void
    {
        $discoveredCount = 0;
        $discoveredActions = [];

        foreach ($this->scanDirectories as $directory) {
            $fullPath = base_path($directory);
            
            if (!File::isDirectory($fullPath)) {
                Log::debug("Action掃描目錄不存在: {$fullPath}");
                continue;
            }

            $result = $this->scanDirectory($fullPath, $directory);
            $discoveredCount += $result['count'];
            $discoveredActions = array_merge($discoveredActions, $result['actions']);
        }

        Log::info('Action自動發現完成', [
            'discovered_count' => $discoveredCount,
            'total_actions' => count($this->actions),
        ]);

        // 如果有發現新的Action，觸發事件
        if ($discoveredCount > 0) {
            event(new ActionRegistryUpdated('discover', $discoveredActions));
        }
    }

    /**
     * 掃描指定目錄中的Action類別
     * 
     * @param string $fullPath 完整目錄路徑
     * @param string $relativePath 相對目錄路徑
     * @return array 包含發現數量和Action列表的陣列
     */
    protected function scanDirectory(string $fullPath, string $relativePath): array
    {
        $discoveredCount = 0;
        $discoveredActions = [];
        $files = File::allFiles($fullPath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            try {
                $className = $this->getClassNameFromFile($file->getPathname(), $relativePath);
                
                if ($className && $this->isValidActionClass($className)) {
                    $instance = new $className();
                    $actionType = $instance->getActionType();
                    
                    // 避免重複註冊
                    if (!$this->hasAction($actionType)) {
                        // 暫時不觸發事件，由autoDiscoverActions統一處理
                        $this->actions[$actionType] = $className;
                        $discoveredCount++;
                        $discoveredActions[] = $actionType;
                        
                        Log::info('Action已發現並註冊', [
                            'action_type' => $actionType,
                            'action_class' => $className,
                        ]);
                    }
                }
            } catch (ReflectionException $e) {
                Log::warning('無法分析Action類別', [
                    'file' => $file->getPathname(),
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $e) {
                Log::error('Action自動發現過程發生錯誤', [
                    'file' => $file->getPathname(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'count' => $discoveredCount,
            'actions' => $discoveredActions,
        ];
    }

    /**
     * 從檔案路徑推斷類別名稱
     * 
     * @param string $filePath 檔案完整路徑
     * @param string $baseDirectory 基礎目錄
     * @return string|null 類別名稱
     */
    protected function getClassNameFromFile(string $filePath, string $baseDirectory): ?string
    {
        // 取得相對於基礎目錄的路徑
        $relativePath = str_replace(base_path($baseDirectory) . DIRECTORY_SEPARATOR, '', $filePath);
        
        // 移除.php副檔名
        $relativePath = str_replace('.php', '', $relativePath);
        
        // 將路徑分隔符號轉換為命名空間分隔符號
        $namespacePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
        
        // 建構完整的類別名稱
        $namespace = $this->getNamespaceFromDirectory($baseDirectory);
        $className = $namespace . '\\' . $namespacePath;

        return class_exists($className) ? $className : null;
    }

    /**
     * 根據目錄路徑取得對應的命名空間
     * 
     * @param string $directory 目錄路徑
     * @return string 命名空間
     */
    protected function getNamespaceFromDirectory(string $directory): string
    {
        $namespaceMap = [
            'app/Actions' => 'App\\Actions',
            'app/Http/Actions' => 'App\\Http\\Actions',
        ];

        return $namespaceMap[$directory] ?? 'App\\Actions';
    }

    /**
     * 檢查類別是否為有效的Action類別
     * 
     * @param string $className 類別名稱
     * @return bool 是否為有效的Action類別
     */
    protected function isValidActionClass(string $className): bool
    {
        try {
            $reflection = new ReflectionClass($className);
            
            // 檢查是否實作ActionInterface
            if (!$reflection->implementsInterface(ActionInterface::class)) {
                return false;
            }

            // 檢查是否為抽象類別
            if ($reflection->isAbstract()) {
                return false;
            }

            // 檢查是否可以實例化
            if (!$reflection->isInstantiable()) {
                return false;
            }

            return true;
        } catch (ReflectionException $e) {
            return false;
        }
    }

    /**
     * 取得Action統計資訊
     * 
     * @return array 統計資訊
     */
    public function getStatistics(): array
    {
        $enabledCount = 0;
        $disabledCount = 0;
        $versions = [];

        foreach ($this->actions as $actionType => $actionClass) {
            try {
                $instance = $this->resolve($actionType);
                
                if ($instance->isEnabled()) {
                    $enabledCount++;
                } else {
                    $disabledCount++;
                }

                $version = $instance->getVersion();
                $versions[$version] = ($versions[$version] ?? 0) + 1;
            } catch (\Exception $e) {
                $disabledCount++;
            }
        }

        return [
            'total_actions' => count($this->actions),
            'enabled_actions' => $enabledCount,
            'disabled_actions' => $disabledCount,
            'version_distribution' => $versions,
            'cached_instances' => count($this->instances),
        ];
    }

    /**
     * 清除所有快取的實例
     */
    public function clearCache(): void
    {
        $this->instances = [];
        Log::info('Action實例快取已清除');
    }

    /**
     * 設定Action掃描目錄
     * 
     * @param array<string> $directories 目錄陣列
     */
    public function setScanDirectories(array $directories): void
    {
        $this->scanDirectories = $directories;
    }

    /**
     * 取得Action掃描目錄
     * 
     * @return array<string> 目錄陣列
     */
    public function getScanDirectories(): array
    {
        return $this->scanDirectories;
    }
}
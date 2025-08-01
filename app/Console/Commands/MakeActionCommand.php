<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

/**
 * 建立Action指令
 * 
 * 提供快速建立Action類別的功能
 */
class MakeActionCommand extends Command
{
    /**
     * 指令名稱和參數
     *
     * @var string
     */
    protected $signature = 'make:action {name : Action的名稱}
                            {--type= : Action的類型識別碼}
                            {--permissions=* : 所需權限清單}
                            {--force : 覆寫已存在的檔案}';

    /**
     * 指令描述
     *
     * @var string
     */
    protected $description = '建立新的Action類別';

    /**
     * 檔案系統實例
     *
     * @var Filesystem
     */
    protected Filesystem $files;

    /**
     * 建構函式
     *
     * @param Filesystem $files
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * 執行指令
     *
     * @return int
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        
        // 解析類別名稱和路徑
        $className = $this->parseClassName($name);
        $filePath = $this->getFilePath($name);
        
        // 檢查檔案是否已存在
        if ($this->files->exists($filePath) && !$this->option('force')) {
            $this->error("Action已存在: {$filePath}");
            return 1;
        }

        // 建立目錄（如果不存在）
        $this->makeDirectory($filePath);

        // 產生Action內容
        $content = $this->buildActionContent($className, $name);

        // 寫入檔案
        $this->files->put($filePath, $content);

        $this->info("Action建立成功: {$filePath}");
        $this->showNextSteps($className);

        return 0;
    }

    /**
     * 解析類別名稱
     *
     * @param string $name
     * @return string
     */
    protected function parseClassName(string $name): string
    {
        $name = str_replace(['/', '\\'], '\\', $name);
        $parts = explode('\\', $name);
        $className = end($parts);
        
        if (!str_ends_with($className, 'Action')) {
            $className .= 'Action';
        }
        
        return $className;
    }

    /**
     * 取得檔案路徑
     *
     * @param string $name
     * @return string
     */
    protected function getFilePath(string $name): string
    {
        $name = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $name);
        $className = $this->parseClassName($name);
        
        // 如果包含路徑分隔符，保持目錄結構
        if (str_contains($name, DIRECTORY_SEPARATOR)) {
            $parts = explode(DIRECTORY_SEPARATOR, $name);
            $parts[count($parts) - 1] = $className;
            $relativePath = implode(DIRECTORY_SEPARATOR, $parts);
        } else {
            $relativePath = $className;
        }
        
        return app_path("Actions/{$relativePath}.php");
    }

    /**
     * 建立目錄
     *
     * @param string $path
     * @return void
     */
    protected function makeDirectory(string $path): void
    {
        $directory = dirname($path);
        
        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }
    }

    /**
     * 建立Action內容
     *
     * @param string $className
     * @param string $name
     * @return string
     */
    protected function buildActionContent(string $className, string $name): string
    {
        $namespace = $this->getNamespace($name);
        $actionType = $this->getActionType($className, $name);
        $permissions = $this->getPermissions();

        return $this->getStubContent($namespace, $className, $actionType, $permissions);
    }

    /**
     * 取得命名空間
     *
     * @param string $name
     * @return string
     */
    protected function getNamespace(string $name): string
    {
        $name = str_replace(['/', '\\'], '\\', $name);
        $parts = explode('\\', $name);
        
        // 移除最後一個部分（類別名稱）
        array_pop($parts);
        
        $namespace = 'App\\Actions';
        if (!empty($parts)) {
            $namespace .= '\\' . implode('\\', $parts);
        }
        
        return $namespace;
    }

    /**
     * 取得Action類型
     *
     * @param string $className
     * @param string $name
     * @return string
     */
    protected function getActionType(string $className, string $name): string
    {
        $actionType = $this->option('type');
        
        if ($actionType) {
            return $actionType;
        }

        // 從類別名稱自動產生Action類型
        $baseName = Str::replaceLast('Action', '', $className);
        $snakeName = Str::snake($baseName);
        
        // 根據路徑決定前綴
        if (str_contains($name, 'User/') || str_contains($name, 'User\\')) {
            return 'user.' . $snakeName;
        } elseif (str_contains($name, 'System/') || str_contains($name, 'System\\')) {
            return 'system.' . $snakeName;
        } elseif (str_contains($name, 'Admin/') || str_contains($name, 'Admin\\')) {
            return 'admin.' . $snakeName;
        }
        
        return $snakeName;
    }

    /**
     * 取得權限清單
     *
     * @return string
     */
    protected function getPermissions(): string
    {
        $permissions = $this->option('permissions');
        
        if (empty($permissions)) {
            return '[]';
        }
        
        $permissionsString = "'" . implode("', '", $permissions) . "'";
        return "[$permissionsString]";
    }

    /**
     * 取得stub內容
     *
     * @param string $namespace
     * @param string $className
     * @param string $actionType
     * @param string $permissions
     * @return string
     */
    protected function getStubContent(string $namespace, string $className, string $actionType, string $permissions): string
    {
        return "<?php

namespace {$namespace};

use App\Actions\BaseAction;
use Illuminate\Http\Request;
use App\Models\User;

/**
 * {$className}
 * 
 * TODO: 請在此處添加Action的描述
 */
class {$className} extends BaseAction
{
    /**
     * 執行Action的處理邏輯
     * 
     * @param \Illuminate\Http\Request \$request 請求物件
     * @param \App\Models\User \$user 已驗證的使用者
     * @return array 處理結果陣列
     * @throws \Exception 當處理過程發生錯誤時拋出例外
     */
    public function execute(Request \$request, User \$user): array
    {
        // 驗證請求參數
        \$this->validate(\$request);

        // TODO: 在此處實作您的業務邏輯
        
        // 記錄執行日誌
        \$this->logInfo('Action執行成功', [
            'user_id' => \$user->id,
            'request_data' => \$request->all()
        ]);

        // 回傳處理結果
        return [
            'message' => '操作成功',
            'data' => [
                // TODO: 回傳您的資料
            ]
        ];
    }

    /**
     * 取得Action的唯一識別碼
     * 
     * @return string Action類型識別碼
     */
    public function getActionType(): string
    {
        return '{$actionType}';
    }

    /**
     * 取得此Action所需的權限清單
     * 
     * @return array 權限名稱陣列
     */
    public function getRequiredPermissions(): array
    {
        return {$permissions};
    }

    /**
     * 取得驗證規則
     * 
     * @return array 驗證規則陣列
     */
    protected function getValidationRules(): array
    {
        return [
            // TODO: 定義您的驗證規則
            // 'field_name' => 'required|string|max:255',
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
            // TODO: 自訂驗證錯誤訊息
            // 'field_name.required' => '欄位名稱為必填項目',
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
            // TODO: 定義參數文件
            // 'field_name' => [
            //     'type' => 'string',
            //     'required' => true,
            //     'description' => '欄位描述',
            //     'example' => '範例值'
            // ]
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
                    'message' => '操作成功',
                    'data' => [
                        // TODO: 定義成功回應的資料結構
                    ]
                ]
            ],
            'error' => [
                'status' => 'error',
                'message' => '錯誤訊息',
                'error_code' => 'ERROR_CODE'
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
                'title' => '基本使用範例',
                'request' => [
                    'action_type' => '{$actionType}',
                    // TODO: 添加範例參數
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
            'name' => '{$className}',
            'description' => 'TODO: 請在此處添加Action的詳細描述',
        ]);
    }
}";
    }

    /**
     * 顯示下一步指引
     *
     * @param string $className
     * @return void
     */
    protected function showNextSteps(string $className): void
    {
        $actionType = $this->option('type') ?: $this->getActionType($className, $this->argument('name'));
        
        $this->line('');
        $this->line("Action類型: <comment>{$actionType}</comment>");
        
        $permissions = $this->option('permissions');
        if (!empty($permissions)) {
            $this->line("所需權限: <comment>" . implode(', ', $permissions) . "</comment>");
        }
        
        $this->line('');
        $this->line('下一步：');
        $this->line('1. 實作 execute() 方法');
        $this->line('2. 設定驗證規則 (getValidationRules)');
        $this->line('3. 完善文件說明 (getDocumentation)');
        $this->line('4. 執行 <comment>php artisan action:list</comment> 確認Action已註冊');
    }
}
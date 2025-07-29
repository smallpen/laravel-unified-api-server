<?php

namespace App\Services;

use App\Contracts\DocumentationGeneratorInterface;
use App\Services\ActionRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * API文件生成器
 * 
 * 負責掃描Action類別並自動生成API文件
 * 支援OpenAPI格式輸出和Swagger UI整合
 */
class DocumentationGenerator implements DocumentationGeneratorInterface
{
    /**
     * Action註冊系統實例
     * 
     * @var \App\Services\ActionRegistry
     */
    protected ActionRegistry $actionRegistry;

    /**
     * API基本資訊
     * 
     * @var array
     */
    protected array $apiInfo;

    /**
     * 文件快取
     * 
     * @var array|null
     */
    protected ?array $documentationCache = null;

    /**
     * 建構子
     * 
     * @param \App\Services\ActionRegistry $actionRegistry Action註冊系統
     */
    public function __construct(ActionRegistry $actionRegistry)
    {
        $this->actionRegistry = $actionRegistry;
        $this->initializeApiInfo();
    }

    /**
     * 初始化API基本資訊
     */
    protected function initializeApiInfo(): void
    {
        $this->apiInfo = [
            'title' => Config::get('app.name', 'Laravel Unified API Server'),
            'description' => '統一API伺服器系統，提供單一接口路徑處理所有API請求',
            'version' => '1.0.0',
            'contact' => [
                'name' => 'API Support',
                'email' => Config::get('mail.from.address', 'support@example.com'),
            ],
            'license' => [
                'name' => 'MIT',
                'url' => 'https://opensource.org/licenses/MIT',
            ],
            'servers' => [
                [
                    'url' => Config::get('app.url', 'http://localhost') . '/api',
                    'description' => '主要API伺服器',
                ],
            ],
        ];
    }

    /**
     * 生成完整的API文件
     * 
     * @return array 完整的API文件陣列
     */
    public function generateDocumentation(): array
    {
        if ($this->documentationCache !== null) {
            return $this->documentationCache;
        }

        $startTime = microtime(true);
        $actions = $this->actionRegistry->getAllActions();
        $documentation = [
            'info' => $this->apiInfo,
            'actions' => [],
            'statistics' => [],
            'generated_at' => now()->toISOString(),
        ];

        $successCount = 0;
        $errorCount = 0;
        $warnings = [];

        foreach ($actions as $actionType => $actionClass) {
            try {
                $actionDoc = $this->getActionDocumentation($actionType);
                $documentation['actions'][$actionType] = $actionDoc;
                $successCount++;

                // 驗證文件完整性
                $validation = $this->validateActionDocumentation($actionType);
                if (!empty($validation['warnings'])) {
                    $warnings[$actionType] = $validation['warnings'];
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('生成Action文件時發生錯誤', [
                    'action_type' => $actionType,
                    'action_class' => $actionClass,
                    'error' => $e->getMessage(),
                ]);

                $documentation['actions'][$actionType] = [
                    'error' => '無法生成文件',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $endTime = microtime(true);
        $documentation['statistics'] = [
            'total_actions' => count($actions),
            'successful_generations' => $successCount,
            'failed_generations' => $errorCount,
            'warnings' => $warnings,
            'generation_time' => round(($endTime - $startTime) * 1000, 2) . 'ms',
        ];

        $this->documentationCache = $documentation;

        Log::info('API文件生成完成', [
            'total_actions' => count($actions),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'generation_time' => $documentation['statistics']['generation_time'],
        ]);

        return $documentation;
    }

    /**
     * 取得指定Action的文件資訊
     * 
     * @param string $actionType Action類型識別碼
     * @return array Action文件資訊陣列
     * @throws \InvalidArgumentException 當Action不存在時拋出
     */
    public function getActionDocumentation(string $actionType): array
    {
        if (!$this->actionRegistry->hasAction($actionType)) {
            throw new InvalidArgumentException("找不到指定的Action: {$actionType}");
        }

        try {
            $action = $this->actionRegistry->resolve($actionType);
            $documentation = $action->getDocumentation();

            // 確保文件格式完整性
            $documentation = $this->normalizeActionDocumentation($documentation);

            // 添加額外的元資料
            $documentation['action_type'] = $actionType;
            $documentation['class_name'] = get_class($action);
            $documentation['generated_at'] = now()->toISOString();

            return $documentation;
        } catch (\Exception $e) {
            Log::error('取得Action文件時發生錯誤', [
                'action_type' => $actionType,
                'error' => $e->getMessage(),
            ]);

            throw new InvalidArgumentException("無法取得Action文件: {$e->getMessage()}");
        }
    }

    /**
     * 標準化Action文件格式
     * 
     * @param array $documentation 原始文件陣列
     * @return array 標準化後的文件陣列
     */
    protected function normalizeActionDocumentation(array $documentation): array
    {
        $defaults = [
            'name' => '未命名Action',
            'description' => '此Action尚未提供描述',
            'version' => '1.0.0',
            'enabled' => true,
            'required_permissions' => [],
            'parameters' => [],
            'responses' => [],
            'examples' => [],
        ];

        $normalized = array_merge($defaults, $documentation);

        // 標準化參數格式
        $normalized['parameters'] = $this->normalizeParameters($normalized['parameters']);

        // 標準化回應格式
        $normalized['responses'] = $this->normalizeResponses($normalized['responses']);

        // 標準化範例格式
        $normalized['examples'] = $this->normalizeExamples($normalized['examples']);

        return $normalized;
    }

    /**
     * 標準化參數格式
     * 
     * @param array $parameters 原始參數陣列
     * @return array 標準化後的參數陣列
     */
    protected function normalizeParameters(array $parameters): array
    {
        $normalized = [];

        foreach ($parameters as $name => $param) {
            if (is_string($param)) {
                // 如果只是字串描述，轉換為標準格式
                $normalized[$name] = [
                    'type' => 'string',
                    'required' => false,
                    'description' => $param,
                ];
            } else {
                // 確保必要欄位存在
                $normalized[$name] = array_merge([
                    'type' => 'string',
                    'required' => false,
                    'description' => '',
                ], $param);
            }
        }

        return $normalized;
    }

    /**
     * 標準化回應格式
     * 
     * @param array $responses 原始回應陣列
     * @return array 標準化後的回應陣列
     */
    protected function normalizeResponses(array $responses): array
    {
        $defaults = [
            'success' => [
                'status' => 'success',
                'message' => '操作成功',
                'data' => null,
            ],
            'error' => [
                'status' => 'error',
                'message' => '操作失敗',
                'error_code' => 'UNKNOWN_ERROR',
            ],
        ];

        return array_merge($defaults, $responses);
    }

    /**
     * 標準化範例格式
     * 
     * @param array $examples 原始範例陣列
     * @return array 標準化後的範例陣列
     */
    protected function normalizeExamples(array $examples): array
    {
        $normalized = [];

        foreach ($examples as $example) {
            if (!isset($example['title']) || !isset($example['request'])) {
                continue;
            }

            $normalized[] = [
                'title' => $example['title'],
                'description' => $example['description'] ?? '',
                'request' => $example['request'],
                'response' => $example['response'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * 匯出為OpenAPI格式
     * 
     * @return string OpenAPI規格的JSON字串
     */
    public function exportToOpenApi(): string
    {
        $documentation = $this->generateDocumentation();
        
        $openApi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $documentation['info']['title'],
                'description' => $documentation['info']['description'],
                'version' => $documentation['info']['version'],
                'contact' => $documentation['info']['contact'],
                'license' => $documentation['info']['license'],
            ],
            'servers' => $documentation['info']['servers'],
            'paths' => $this->generateOpenApiPaths($documentation['actions']),
            'components' => $this->generateOpenApiComponents($documentation['actions']),
        ];

        return json_encode($openApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 生成OpenAPI路徑規格
     * 
     * @param array $actions Action文件陣列
     * @return array OpenAPI路徑陣列
     */
    protected function generateOpenApiPaths(array $actions): array
    {
        $paths = [
            '/' => [
                'post' => [
                    'summary' => '統一API接口',
                    'description' => '所有API請求都透過此接口處理，使用action_type參數指定具體操作',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['action_type'],
                                    'properties' => [
                                        'action_type' => [
                                            'type' => 'string',
                                            'description' => 'Action類型識別碼',
                                            'enum' => array_keys($actions),
                                        ],
                                    ],
                                    'additionalProperties' => true,
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '請求成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/SuccessResponse',
                                    ],
                                ],
                            ],
                        ],
                        '400' => [
                            'description' => '請求參數錯誤',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/ErrorResponse',
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => '未授權',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/ErrorResponse',
                                    ],
                                ],
                            ],
                        ],
                        '404' => [
                            'description' => 'Action不存在',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/ErrorResponse',
                                    ],
                                ],
                            ],
                        ],
                        '500' => [
                            'description' => '伺服器內部錯誤',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/ErrorResponse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'security' => [
                        ['bearerAuth' => []],
                    ],
                ],
            ],
        ];

        return $paths;
    }

    /**
     * 生成OpenAPI組件規格
     * 
     * @param array $actions Action文件陣列
     * @return array OpenAPI組件陣列
     */
    protected function generateOpenApiComponents(array $actions): array
    {
        return [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                    'description' => 'Bearer Token驗證',
                ],
            ],
            'schemas' => [
                'SuccessResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'example' => 'success',
                        ],
                        'message' => [
                            'type' => 'string',
                            'example' => '操作成功',
                        ],
                        'data' => [
                            'type' => 'object',
                            'description' => '回應資料，內容依據不同Action而異',
                        ],
                        'timestamp' => [
                            'type' => 'string',
                            'format' => 'date-time',
                            'example' => '2024-01-01T00:00:00Z',
                        ],
                    ],
                ],
                'ErrorResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'example' => 'error',
                        ],
                        'message' => [
                            'type' => 'string',
                            'example' => '操作失敗',
                        ],
                        'error_code' => [
                            'type' => 'string',
                            'example' => 'VALIDATION_ERROR',
                        ],
                        'details' => [
                            'type' => 'object',
                            'description' => '詳細錯誤資訊',
                        ],
                        'timestamp' => [
                            'type' => 'string',
                            'format' => 'date-time',
                            'example' => '2024-01-01T00:00:00Z',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 取得API文件的基本資訊
     * 
     * @return array 基本資訊陣列
     */
    public function getApiInfo(): array
    {
        return $this->apiInfo;
    }

    /**
     * 設定API文件的基本資訊
     * 
     * @param array $info 基本資訊陣列
     */
    public function setApiInfo(array $info): void
    {
        $this->apiInfo = array_merge($this->apiInfo, $info);
        $this->clearCache();
    }

    /**
     * 取得所有Action的摘要資訊
     * 
     * @return array Action摘要資訊陣列
     */
    public function getActionsSummary(): array
    {
        $actions = $this->actionRegistry->getAllActions();
        $summary = [];

        foreach ($actions as $actionType => $actionClass) {
            try {
                $action = $this->actionRegistry->resolve($actionType);
                $doc = $action->getDocumentation();

                $summary[$actionType] = [
                    'name' => $doc['name'] ?? $actionType,
                    'description' => $doc['description'] ?? '無描述',
                    'version' => $doc['version'] ?? '1.0.0',
                    'enabled' => $doc['enabled'] ?? true,
                    'required_permissions' => $doc['required_permissions'] ?? [],
                    'parameter_count' => count($doc['parameters'] ?? []),
                    'example_count' => count($doc['examples'] ?? []),
                ];
            } catch (\Exception $e) {
                $summary[$actionType] = [
                    'name' => $actionType,
                    'description' => '無法載入Action資訊',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * 驗證Action文件的完整性
     * 
     * @param string $actionType Action類型識別碼
     * @return array 驗證結果陣列
     */
    public function validateActionDocumentation(string $actionType): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ];

        try {
            $documentation = $this->getActionDocumentation($actionType);

            // 檢查必要欄位
            $requiredFields = ['name', 'description', 'parameters', 'responses', 'examples'];
            foreach ($requiredFields as $field) {
                if (!isset($documentation[$field])) {
                    $result['errors'][] = "缺少必要欄位: {$field}";
                    $result['valid'] = false;
                }
            }

            // 檢查描述是否為預設值
            if (isset($documentation['description']) && 
                $documentation['description'] === '此Action尚未提供描述') {
                $result['warnings'][] = '使用預設描述，建議提供具體的Action描述';
            }

            // 檢查參數文件
            if (isset($documentation['parameters']) && is_array($documentation['parameters'])) {
                foreach ($documentation['parameters'] as $paramName => $param) {
                    if (!isset($param['description']) || empty($param['description'])) {
                        $result['warnings'][] = "參數 '{$paramName}' 缺少描述";
                    }
                    if (!isset($param['type'])) {
                        $result['warnings'][] = "參數 '{$paramName}' 缺少類型定義";
                    }
                }
            }

            // 檢查範例
            if (empty($documentation['examples'])) {
                $result['warnings'][] = '缺少使用範例，建議提供至少一個範例';
            }

        } catch (InvalidArgumentException $e) {
            // 重新拋出 InvalidArgumentException，讓控制器處理 404 錯誤
            throw $e;
        } catch (\Exception $e) {
            $result['valid'] = false;
            $result['errors'][] = "驗證過程發生錯誤: {$e->getMessage()}";
        }

        return $result;
    }

    /**
     * 取得文件生成統計資訊
     * 
     * @return array 統計資訊陣列
     */
    public function getGenerationStatistics(): array
    {
        $documentation = $this->generateDocumentation();
        return $documentation['statistics'] ?? [];
    }

    /**
     * 清除文件快取
     */
    public function clearCache(): void
    {
        $this->documentationCache = null;
        Log::info('API文件快取已清除');
    }

    /**
     * 重新生成文件
     * 
     * @return array 重新生成的文件陣列
     */
    public function regenerateDocumentation(): array
    {
        $this->clearCache();
        return $this->generateDocumentation();
    }


}
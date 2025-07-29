<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentationGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;

/**
 * API文件控制器
 * 
 * 負責提供API文件的各種格式輸出
 * 包括JSON格式、OpenAPI規格和Swagger UI介面
 */
class DocumentationController extends Controller
{
    /**
     * 文件生成器實例
     * 
     * @var \App\Services\DocumentationGenerator
     */
    protected DocumentationGenerator $documentationGenerator;

    /**
     * 建構子
     * 
     * @param \App\Services\DocumentationGenerator $documentationGenerator 文件生成器
     */
    public function __construct(DocumentationGenerator $documentationGenerator)
    {
        $this->documentationGenerator = $documentationGenerator;
    }

    /**
     * 顯示Swagger UI介面
     * 
     * @return \Illuminate\Http\Response
     */
    public function swaggerUi(): Response
    {
        try {
            $apiInfo = $this->documentationGenerator->getApiInfo();
            
            return response()->view('documentation.swagger-ui', [
                'apiTitle' => $apiInfo['title'],
                'apiDescription' => $apiInfo['description'],
                'apiVersion' => $apiInfo['version'],
                'openApiUrl' => route('api.docs.openapi'),
            ]);
        } catch (\Exception $e) {
            Log::error('載入Swagger UI時發生錯誤', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->view('documentation.error', [
                'error' => '無法載入API文件介面',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得完整的API文件（JSON格式）
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDocumentation(): JsonResponse
    {
        try {
            $documentation = $this->documentationGenerator->generateDocumentation();
            
            return response()->json([
                'status' => 'success',
                'message' => 'API文件取得成功',
                'data' => $documentation,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('取得API文件時發生錯誤', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => '無法取得API文件',
                'error_code' => 'DOCUMENTATION_ERROR',
                'details' => [
                    'error' => $e->getMessage(),
                ],
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * 取得OpenAPI規格文件
     * 
     * @return \Illuminate\Http\Response
     */
    public function getOpenApiSpec(): Response
    {
        try {
            $openApiSpec = $this->documentationGenerator->exportToOpenApi();
            
            return response($openApiSpec)
                ->header('Content-Type', 'application/json')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            Log::error('取得OpenAPI規格時發生錯誤', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => '無法生成OpenAPI規格',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得Action摘要列表
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActionsSummary(): JsonResponse
    {
        try {
            $summary = $this->documentationGenerator->getActionsSummary();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Action摘要取得成功',
                'data' => [
                    'actions' => $summary,
                    'total_count' => count($summary),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('取得Action摘要時發生錯誤', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => '無法取得Action摘要',
                'error_code' => 'SUMMARY_ERROR',
                'details' => [
                    'error' => $e->getMessage(),
                ],
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * 取得指定Action的詳細文件
     * 
     * @param string $actionType Action類型識別碼
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActionDocumentation(string $actionType): JsonResponse
    {
        try {
            $documentation = $this->documentationGenerator->getActionDocumentation($actionType);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Action文件取得成功',
                'data' => $documentation,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Action不存在',
                'error_code' => 'ACTION_NOT_FOUND',
                'details' => [
                    'action_type' => $actionType,
                    'error' => $e->getMessage(),
                ],
                'timestamp' => now()->toISOString(),
            ], 404);
        } catch (\Exception $e) {
            Log::error('取得Action文件時發生錯誤', [
                'action_type' => $actionType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => '無法取得Action文件',
                'error_code' => 'DOCUMENTATION_ERROR',
                'details' => [
                    'action_type' => $actionType,
                    'error' => $e->getMessage(),
                ],
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * 重新生成API文件
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerateDocumentation(): JsonResponse
    {
        try {
            $documentation = $this->documentationGenerator->regenerateDocumentation();
            $statistics = $this->documentationGenerator->getGenerationStatistics();
            
            return response()->json([
                'status' => 'success',
                'message' => 'API文件重新生成成功',
                'data' => [
                    'documentation' => $documentation,
                    'statistics' => $statistics,
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('重新生成API文件時發生錯誤', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => '無法重新生成API文件',
                'error_code' => 'REGENERATION_ERROR',
                'details' => [
                    'error' => $e->getMessage(),
                ],
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * 取得文件生成統計資訊
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->documentationGenerator->getGenerationStatistics();
            
            return response()->json([
                'status' => 'success',
                'message' => '統計資訊取得成功',
                'data' => $statistics,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('取得統計資訊時發生錯誤', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => '無法取得統計資訊',
                'error_code' => 'STATISTICS_ERROR',
                'details' => [
                    'error' => $e->getMessage(),
                ],
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * 驗證Action文件完整性
     * 
     * @param string $actionType Action類型識別碼
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateActionDocumentation(string $actionType): JsonResponse
    {
        try {
            $validation = $this->documentationGenerator->validateActionDocumentation($actionType);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Action文件驗證完成',
                'data' => $validation,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Action不存在',
                'error_code' => 'ACTION_NOT_FOUND',
                'details' => [
                    'action_type' => $actionType,
                    'error' => $e->getMessage(),
                ],
                'timestamp' => now()->toISOString(),
            ], 404);
        } catch (\Exception $e) {
            Log::error('驗證Action文件時發生錯誤', [
                'action_type' => $actionType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => '無法驗證Action文件',
                'error_code' => 'VALIDATION_ERROR',
                'details' => [
                    'action_type' => $actionType,
                    'error' => $e->getMessage(),
                ],
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * 取得文件更新狀態
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDocumentationStatus(): JsonResponse
    {
        try {
            $statistics = $this->documentationGenerator->getGenerationStatistics();
            $summary = $this->documentationGenerator->getActionsSummary();
            
            $status = [
                'is_up_to_date' => true,
                'last_generated' => $statistics['generated_at'] ?? null,
                'total_actions' => count($summary),
                'successful_generations' => $statistics['successful_generations'] ?? 0,
                'failed_generations' => $statistics['failed_generations'] ?? 0,
                'warnings_count' => count($statistics['warnings'] ?? []),
                'generation_time' => $statistics['generation_time'] ?? null,
                'cache_status' => 'active',
            ];
            
            return response()->json([
                'status' => 'success',
                'message' => '文件狀態取得成功',
                'data' => $status,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('取得文件狀態時發生錯誤', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => '無法取得文件狀態',
                'error_code' => 'STATUS_ERROR',
                'details' => [
                    'error' => $e->getMessage(),
                ],
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * 取得Action變更歷史
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActionChanges(): JsonResponse
    {
        try {
            // 這裡可以實作Action變更歷史的追蹤
            // 目前返回基本的Action列表和狀態
            $summary = $this->documentationGenerator->getActionsSummary();
            $statistics = $this->documentationGenerator->getGenerationStatistics();
            
            $changes = [
                'recent_changes' => [],
                'action_summary' => $summary,
                'last_scan' => $statistics['generated_at'] ?? null,
                'total_actions' => count($summary),
            ];
            
            return response()->json([
                'status' => 'success',
                'message' => 'Action變更歷史取得成功',
                'data' => $changes,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('取得Action變更歷史時發生錯誤', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => '無法取得Action變更歷史',
                'error_code' => 'CHANGES_ERROR',
                'details' => [
                    'error' => $e->getMessage(),
                ],
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }
}
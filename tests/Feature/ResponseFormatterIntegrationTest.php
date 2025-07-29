<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ResponseFormatter;
use App\Contracts\ResponseFormatterInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ResponseFormatter整合測試
 * 
 * 測試ResponseFormatter在實際應用環境中的整合情況
 */
class ResponseFormatterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試ResponseFormatter可以透過依賴注入使用
     * 
     * @return void
     */
    public function test_response_formatter_dependency_injection(): void
    {
        $this->app->bind('test.controller', function ($app) {
            return new class($app->make(ResponseFormatterInterface::class)) {
                public function __construct(
                    private ResponseFormatterInterface $formatter
                ) {}
                
                public function getFormatter(): ResponseFormatterInterface
                {
                    return $this->formatter;
                }
            };
        });

        $controller = $this->app->make('test.controller');
        $formatter = $controller->getFormatter();

        $this->assertInstanceOf(ResponseFormatter::class, $formatter);
    }

    /**
     * 測試ResponseFormatter在HTTP回應中的使用
     * 
     * @return void
     */
    public function test_response_formatter_in_http_response(): void
    {
        // 建立測試路由
        $this->app['router']->post('/test/success', function (ResponseFormatterInterface $formatter) {
            $data = ['message' => '測試成功'];
            return response()->json($formatter->success($data));
        });

        $this->app['router']->post('/test/error', function (ResponseFormatterInterface $formatter) {
            return response()->json($formatter->error('測試錯誤', 'TEST_ERROR'), 400);
        });

        $this->app['router']->post('/test/paginated', function (ResponseFormatterInterface $formatter) {
            $data = [['id' => 1, 'name' => '項目1']];
            $pagination = [
                'current_page' => 1,
                'per_page' => 10,
                'total' => 1,
                'last_page' => 1,
            ];
            return response()->json($formatter->paginated($data, $pagination));
        });

        // 測試成功回應
        $response = $this->postJson('/test/success');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data',
                    'timestamp',
                    'request_id',
                ])
                ->assertJson([
                    'status' => 'success',
                    'data' => ['message' => '測試成功'],
                ]);

        // 測試錯誤回應
        $response = $this->postJson('/test/error');
        $response->assertStatus(400)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'timestamp',
                    'request_id',
                ])
                ->assertJson([
                    'status' => 'error',
                    'message' => '測試錯誤',
                    'error_code' => 'TEST_ERROR',
                ]);

        // 測試分頁回應
        $response = $this->postJson('/test/paginated');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data',
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                        'has_more_pages',
                    ],
                    'timestamp',
                    'request_id',
                ])
                ->assertJson([
                    'status' => 'success',
                    'data' => [['id' => 1, 'name' => '項目1']],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => 10,
                        'total' => 1,
                        'last_page' => 1,
                        'has_more_pages' => false,
                    ],
                ]);
    }

    /**
     * 測試ResponseFormatter與Laravel驗證器的整合
     * 
     * @return void
     */
    public function test_response_formatter_with_laravel_validation(): void
    {
        $this->app['router']->post('/test/validation', function (ResponseFormatterInterface $formatter) {
            request()->validate([
                'email' => 'required|email',
                'password' => 'required|min:8',
            ]);

            return response()->json($formatter->success(['message' => '驗證通過']));
        });

        // 測試驗證失敗的情況
        $response = $this->postJson('/test/validation', [
            'email' => 'invalid-email',
            'password' => '123',
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'message',
                    'errors' => [
                        'email',
                        'password',
                    ],
                ]);
    }

    /**
     * 測試ResponseFormatter的效能
     * 
     * @return void
     */
    public function test_response_formatter_performance(): void
    {
        $formatter = $this->app->make(ResponseFormatterInterface::class);
        
        $startTime = microtime(true);
        
        // 執行1000次格式化操作
        for ($i = 0; $i < 1000; $i++) {
            $formatter->success(['iteration' => $i]);
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // 確保1000次操作在1秒內完成
        $this->assertLessThan(1.0, $executionTime, 'ResponseFormatter效能測試失敗，執行時間過長');
    }

    /**
     * 測試ResponseFormatter的記憶體使用
     * 
     * @return void
     */
    public function test_response_formatter_memory_usage(): void
    {
        $formatter = $this->app->make(ResponseFormatterInterface::class);
        
        $initialMemory = memory_get_usage();
        
        // 建立大量回應資料
        for ($i = 0; $i < 100; $i++) {
            $largeData = array_fill(0, 1000, ['id' => $i, 'data' => str_repeat('x', 100)]);
            $formatter->success($largeData);
        }
        
        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // 確保記憶體增長在合理範圍內（小於50MB）
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'ResponseFormatter記憶體使用過多');
    }

    /**
     * 測試ResponseFormatter與快取的整合
     * 
     * @return void
     */
    public function test_response_formatter_with_cache(): void
    {
        $formatter = $this->app->make(ResponseFormatterInterface::class);
        
        // 建立可快取的回應
        $cacheKey = 'test_response';
        $data = ['cached' => true, 'timestamp' => time()];
        
        $response = $formatter->success($data, '快取測試');
        
        // 將回應存入快取
        cache()->put($cacheKey, $response, 60);
        
        // 從快取取得回應
        $cachedResponse = cache()->get($cacheKey);
        
        $this->assertEquals($response, $cachedResponse);
        $this->assertEquals('success', $cachedResponse['status']);
        $this->assertEquals('快取測試', $cachedResponse['message']);
        $this->assertEquals($data, $cachedResponse['data']);
    }

    /**
     * 測試ResponseFormatter與日誌系統的整合
     * 
     * @return void
     */
    public function test_response_formatter_with_logging(): void
    {
        $formatter = $this->app->make(ResponseFormatterInterface::class);
        
        // 建立測試路由，記錄回應格式
        $this->app['router']->post('/test/logging', function (ResponseFormatterInterface $formatter) {
            $response = $formatter->success(['logged' => true]);
            
            // 記錄回應資訊
            \Log::info('API回應已格式化', [
                'status' => $response['status'],
                'request_id' => $response['request_id'],
                'timestamp' => $response['timestamp'],
            ]);
            
            return response()->json($response);
        });

        // 執行請求
        $response = $this->postJson('/test/logging');
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => ['logged' => true],
                ]);
    }

    /**
     * 測試ResponseFormatter的大量資料處理
     * 
     * @return void
     */
    public function test_response_formatter_large_data_handling(): void
    {
        $formatter = $this->app->make(ResponseFormatterInterface::class);
        
        // 建立測試路由處理大量資料
        $this->app['router']->post('/test/large-data', function (ResponseFormatterInterface $formatter) {
            // 建立大量測試資料
            $largeData = array_fill(0, 500, [
                'id' => rand(1, 1000),
                'name' => str_repeat('test', 50),
                'description' => str_repeat('description', 20),
            ]);
            
            return response()->json($formatter->largeDataResponse($largeData, '大量資料處理測試', [], 100000));
        });

        // 執行請求
        $response = $this->postJson('/test/large-data');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data',
                    'meta' => [
                        'data_size',
                        'compression_recommended',
                        'suggestion',
                    ],
                    'timestamp',
                    'request_id',
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => '大量資料處理測試',
                    'meta' => [
                        'compression_recommended' => true,
                    ],
                ]);

        // 確認 meta 資訊包含正確的建議
        $responseData = $response->json();
        $this->assertArrayHasKey('data_size', $responseData['meta']);
        $this->assertGreaterThan(100000, $responseData['meta']['data_size']);
        $this->assertStringContainsString('建議使用分頁', $responseData['meta']['suggestion']);
    }
}
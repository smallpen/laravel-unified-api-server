<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Services\ResponseFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 例外處理整合測試
 */
class ExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 設定測試環境
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app->bind(\App\Contracts\ResponseFormatterInterface::class, ResponseFormatter::class);
        
        // 建立測試路由
        $this->setupTestRoutes();
    }

    /**
     * 設定測試路由
     */
    protected function setupTestRoutes(): void
    {
        Route::post('/api/test/validation-error', function () {
            throw new ValidationException('驗證失敗', ['email' => ['電子郵件是必填的']]);
        });

        Route::post('/api/test/authentication-error', function () {
            throw new AuthenticationException('認證失敗');
        });

        Route::post('/api/test/not-found-error', function () {
            throw new NotFoundException('資源未找到');
        });

        Route::post('/api/test/api-error', function () {
            throw new ApiException('API 錯誤', 'API_ERROR', 400, ['field' => 'value']);
        });

        Route::post('/api/test/generic-error', function () {
            throw new \Exception('一般例外');
        });

        Route::get('/api/test/method-not-allowed', function () {
            return response()->json(['message' => 'success']);
        });
    }

    /**
     * 測試驗證錯誤處理
     */
    public function test_validation_error_handling(): void
    {
        $response = $this->postJson('/api/test/validation-error');

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'message' => '驗證失敗',
                    'error_code' => 'VALIDATION_ERROR',
                    'details' => [
                        'email' => ['電子郵件是必填的']
                    ]
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'details',
                    'timestamp',
                    'request_id'
                ]);
    }

    /**
     * 測試認證錯誤處理
     */
    public function test_authentication_error_handling(): void
    {
        $response = $this->postJson('/api/test/authentication-error');

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'message' => '認證失敗',
                    'error_code' => 'AUTHENTICATION_ERROR'
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'timestamp',
                    'request_id'
                ]);
    }

    /**
     * 測試未找到錯誤處理
     */
    public function test_not_found_error_handling(): void
    {
        $response = $this->postJson('/api/test/not-found-error');

        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => '資源未找到',
                    'error_code' => 'NOT_FOUND'
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'timestamp',
                    'request_id'
                ]);
    }

    /**
     * 測試 API 錯誤處理
     */
    public function test_api_error_handling(): void
    {
        $response = $this->postJson('/api/test/api-error');

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'API 錯誤',
                    'error_code' => 'API_ERROR',
                    'details' => [
                        'field' => 'value'
                    ]
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'details',
                    'timestamp',
                    'request_id'
                ]);
    }

    /**
     * 測試一般例外處理（開發環境）
     */
    public function test_generic_error_handling_development(): void
    {
        $this->app['env'] = 'local';
        
        $response = $this->postJson('/api/test/generic-error');

        $response->assertStatus(500)
                ->assertJson([
                    'status' => 'error',
                    'message' => '一般例外',
                    'error_code' => 'INTERNAL_SERVER_ERROR'
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'details' => [
                        'exception',
                        'file',
                        'line',
                        'trace'
                    ],
                    'timestamp',
                    'request_id'
                ]);
    }

    /**
     * 測試一般例外處理（生產環境）
     */
    public function test_generic_error_handling_production(): void
    {
        $this->app['env'] = 'production';
        
        $response = $this->postJson('/api/test/generic-error');

        $response->assertStatus(500)
                ->assertJson([
                    'status' => 'error',
                    'message' => '系統發生內部錯誤，請稍後再試',
                    'error_code' => 'INTERNAL_SERVER_ERROR',
                    'details' => []
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'details',
                    'timestamp',
                    'request_id'
                ]);
    }

    /**
     * 測試 404 路由錯誤處理
     */
    public function test_route_not_found_error_handling(): void
    {
        $response = $this->postJson('/api/non-existent-route');

        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => '請求的路由不存在',
                    'error_code' => 'NOT_FOUND'
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'timestamp',
                    'request_id'
                ]);
    }

    /**
     * 測試方法不允許錯誤處理
     */
    public function test_method_not_allowed_error_handling(): void
    {
        $response = $this->postJson('/api/test/method-not-allowed');

        $response->assertStatus(405)
                ->assertJson([
                    'status' => 'error',
                    'message' => '不支援的 HTTP 方法',
                    'error_code' => 'METHOD_NOT_ALLOWED'
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'timestamp',
                    'request_id'
                ]);
    }

    /**
     * 測試 Laravel 驗證例外處理
     */
    public function test_laravel_validation_exception_handling(): void
    {
        Route::post('/api/test/laravel-validation', function () {
            request()->validate([
                'email' => 'required|email',
                'password' => 'required|min:8'
            ]);
        });

        $response = $this->postJson('/api/test/laravel-validation', [
            'email' => 'invalid-email',
            'password' => '123'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'message' => '請求參數驗證失敗',
                    'error_code' => 'VALIDATION_ERROR'
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                    'details' => [
                        'email',
                        'password'
                    ],
                    'timestamp',
                    'request_id'
                ]);
    }

    /**
     * 測試敏感資訊不會洩漏
     */
    public function test_sensitive_information_not_leaked(): void
    {
        $this->app['env'] = 'production';
        
        Route::post('/api/test/database-error', function () {
            throw new \PDOException('SQLSTATE[42S02]: Base table or view not found: 1146 Table \'secret_database.users\' doesn\'t exist');
        });

        $response = $this->postJson('/api/test/database-error');

        $response->assertStatus(500)
                ->assertJson([
                    'status' => 'error',
                    'message' => '系統發生內部錯誤，請稍後再試',
                    'error_code' => 'INTERNAL_SERVER_ERROR',
                    'details' => []
                ]);

        // 確保回應中不包含敏感資訊
        $content = $response->getContent();
        $this->assertStringNotContainsString('secret_database', $content);
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('PDOException', $content);
    }

    /**
     * 測試回應格式一致性
     */
    public function test_response_format_consistency(): void
    {
        $testRoutes = [
            '/api/test/validation-error',
            '/api/test/authentication-error',
            '/api/test/not-found-error',
            '/api/test/api-error'
        ];

        foreach ($testRoutes as $route) {
            $response = $this->postJson($route);
            
            // 所有錯誤回應都應該有相同的基本結構
            $response->assertJsonStructure([
                'status',
                'message',
                'error_code',
                'timestamp',
                'request_id'
            ]);

            $data = $response->json();
            $this->assertEquals('error', $data['status']);
            $this->assertIsString($data['message']);
            $this->assertIsString($data['error_code']);
            $this->assertIsString($data['timestamp']);
            $this->assertIsString($data['request_id']);
        }
    }
}
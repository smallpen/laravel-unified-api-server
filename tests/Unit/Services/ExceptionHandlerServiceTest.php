<?php

namespace Tests\Unit\Services;

use App\Exceptions\ApiException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\RateLimitException;
use App\Exceptions\ValidationException;
use App\Services\ExceptionHandlerService;
use App\Services\ResponseFormatter;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

/**
 * 例外處理服務測試
 */
class ExceptionHandlerServiceTest extends TestCase
{
    /**
     * 例外處理服務實例
     * 
     * @var ExceptionHandlerService
     */
    protected ExceptionHandlerService $service;

    /**
     * 設定測試環境
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app->bind(\App\Contracts\ResponseFormatterInterface::class, ResponseFormatter::class);
        $this->service = new ExceptionHandlerService(
            $this->app->make(\App\Contracts\ResponseFormatterInterface::class)
        );
    }

    /**
     * 測試拋出驗證錯誤
     */
    public function test_throw_validation_error(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('自定義驗證錯誤');

        $errors = ['email' => ['電子郵件格式不正確']];
        $this->service->throwValidationError($errors, '自定義驗證錯誤');
    }

    /**
     * 測試拋出認證錯誤
     */
    public function test_throw_authentication_error(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('自定義認證錯誤');

        $this->service->throwAuthenticationError('自定義認證錯誤');
    }

    /**
     * 測試拋出授權錯誤
     */
    public function test_throw_authorization_error(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('自定義授權錯誤');

        $this->service->throwAuthorizationError('自定義授權錯誤');
    }

    /**
     * 測試拋出未找到錯誤
     */
    public function test_throw_not_found_error(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('自定義未找到錯誤');

        $this->service->throwNotFoundError('自定義未找到錯誤');
    }

    /**
     * 測試拋出速率限制錯誤
     */
    public function test_throw_rate_limit_error(): void
    {
        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('自定義速率限制錯誤');

        $this->service->throwRateLimitError('自定義速率限制錯誤');
    }

    /**
     * 測試拋出一般 API 錯誤
     */
    public function test_throw_api_error(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('自定義 API 錯誤');

        $this->service->throwApiError('自定義 API 錯誤', 'CUSTOM_ERROR', 400);
    }

    /**
     * 測試安全處理 API 例外
     */
    public function test_handle_api_exception_safely(): void
    {
        $exception = new ApiException('測試錯誤', 'TEST_ERROR', 400, ['field' => 'value']);
        $response = $this->service->handleExceptionSafely($exception);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('測試錯誤', $data['message']);
        $this->assertEquals('TEST_ERROR', $data['error_code']);
    }

    /**
     * 測試安全處理一般例外（開發環境）
     */
    public function test_handle_generic_exception_safely_development(): void
    {
        $this->app['env'] = 'local';
        
        $exception = new \Exception('測試例外');
        $response = $this->service->handleExceptionSafely($exception, true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('測試例外', $data['message']);
        $this->assertEquals('INTERNAL_SERVER_ERROR', $data['error_code']);
        $this->assertArrayHasKey('details', $data);
    }

    /**
     * 測試安全處理一般例外（生產環境）
     */
    public function test_handle_generic_exception_safely_production(): void
    {
        $this->app['env'] = 'production';
        
        $exception = new \Exception('敏感錯誤資訊');
        $response = $this->service->handleExceptionSafely($exception);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('系統發生內部錯誤，請稍後再試', $data['message']);
        $this->assertEquals('INTERNAL_SERVER_ERROR', $data['error_code']);
        $this->assertEquals([], $data['details']);
    }

    /**
     * 測試檢查敏感例外
     */
    public function test_is_sensitive_exception(): void
    {
        // PDO 例外是敏感的
        $pdoException = new \PDOException('Database connection failed');
        $this->assertTrue($this->service->isSensitiveException($pdoException));

        // 一般例外不是敏感的
        $generalException = new \Exception('General error');
        $this->assertFalse($this->service->isSensitiveException($generalException));
    }

    /**
     * 測試建立錯誤回應
     */
    public function test_create_error_response(): void
    {
        $response = $this->service->createErrorResponse(
            '測試錯誤',
            'TEST_ERROR',
            400,
            ['field' => 'value']
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('測試錯誤', $data['message']);
        $this->assertEquals('TEST_ERROR', $data['error_code']);
        $this->assertEquals(['field' => 'value'], $data['details']);
    }

    /**
     * 測試清理追蹤資訊
     */
    public function test_sanitize_trace(): void
    {
        $trace = [
            [
                'file' => '/path/to/file.php',
                'line' => 123,
                'function' => 'testFunction',
                'args' => [
                    'short_string',
                    str_repeat('a', 200), // 長字串
                    array_fill(0, 20, 'item'), // 大陣列
                    new \stdClass() // 物件
                ]
            ]
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeTrace');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $trace);

        $this->assertEquals('short_string', $result[0]['args'][0]);
        $this->assertEquals('[長字串已隱藏]', $result[0]['args'][1]);
        $this->assertEquals('[大陣列已隱藏]', $result[0]['args'][2]);
        $this->assertStringContainsString('[物件已隱藏:', $result[0]['args'][3]);
    }
}
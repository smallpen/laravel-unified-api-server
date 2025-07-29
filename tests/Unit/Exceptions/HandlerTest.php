<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ApiException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\Handler;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Services\ResponseFormatter;
use Illuminate\Auth\AuthenticationException as LaravelAuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * 例外處理器測試
 */
class HandlerTest extends TestCase
{
    /**
     * 例外處理器實例
     * 
     * @var Handler
     */
    protected Handler $handler;

    /**
     * 設定測試環境
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app->bind(\App\Contracts\ResponseFormatterInterface::class, ResponseFormatter::class);
        $this->handler = new Handler($this->app);
    }

    /**
     * 測試處理自定義 API 例外
     */
    public function test_handle_api_exception(): void
    {
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Accept', 'application/json');

        $exception = new ApiException('測試錯誤', 'TEST_ERROR', 400, ['field' => 'value']);

        // 使用反射來測試受保護的方法
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('renderApiException');
        $method->setAccessible(true);

        $response = $method->invoke($this->handler, $exception, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('測試錯誤', $data['message']);
        $this->assertEquals('TEST_ERROR', $data['error_code']);
        $this->assertEquals(['field' => 'value'], $data['details']);
    }

    /**
     * 測試處理 Laravel 驗證例外
     */
    public function test_handle_laravel_validation_exception(): void
    {
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Accept', 'application/json');

        // 建立模擬的驗證例外
        $validator = $this->app['validator']->make([], ['email' => 'required']);
        $validator->fails(); // 觸發驗證失敗
        $exception = new LaravelValidationException($validator);

        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('handleBuiltInExceptions');
        $method->setAccessible(true);

        $response = $method->invoke($this->handler, $exception, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('請求參數驗證失敗', $data['message']);
        $this->assertEquals('VALIDATION_ERROR', $data['error_code']);
        $this->assertArrayHasKey('email', $data['details']);
    }

    /**
     * 測試處理 Laravel 認證例外
     */
    public function test_handle_laravel_authentication_exception(): void
    {
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Accept', 'application/json');

        $exception = new LaravelAuthenticationException('Unauthenticated');

        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('handleBuiltInExceptions');
        $method->setAccessible(true);

        $response = $method->invoke($this->handler, $exception, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('身份驗證失敗', $data['message']);
        $this->assertEquals('AUTHENTICATION_ERROR', $data['error_code']);
    }

    /**
     * 測試處理模型未找到例外
     */
    public function test_handle_model_not_found_exception(): void
    {
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Accept', 'application/json');

        $exception = new ModelNotFoundException();
        $exception->setModel(\App\Models\User::class);

        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('handleBuiltInExceptions');
        $method->setAccessible(true);

        $response = $method->invoke($this->handler, $exception, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('請求的資源不存在', $data['message']);
        $this->assertEquals('NOT_FOUND', $data['error_code']);
        $this->assertEquals(['model' => 'User'], $data['details']);
    }

    /**
     * 測試處理 HTTP 404 例外
     */
    public function test_handle_not_found_http_exception(): void
    {
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Accept', 'application/json');

        $exception = new NotFoundHttpException();

        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('handleHttpException');
        $method->setAccessible(true);

        $response = $method->invoke($this->handler, $exception);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('請求的路由不存在', $data['message']);
        $this->assertEquals('NOT_FOUND', $data['error_code']);
    }

    /**
     * 測試處理一般例外（開發環境）
     */
    public function test_handle_generic_exception_development(): void
    {
        $this->app['env'] = 'local';
        
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Accept', 'application/json');

        $exception = new \Exception('測試例外');

        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('handleGenericException');
        $method->setAccessible(true);

        $response = $method->invoke($this->handler, $exception, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('測試例外', $data['message']);
        $this->assertEquals('INTERNAL_SERVER_ERROR', $data['error_code']);
        $this->assertArrayHasKey('details', $data);
        $this->assertArrayHasKey('exception', $data['details']);
    }

    /**
     * 測試處理一般例外（生產環境）
     */
    public function test_handle_generic_exception_production(): void
    {
        $this->app['env'] = 'production';
        
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Accept', 'application/json');

        $exception = new \Exception('敏感錯誤資訊');

        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('handleGenericException');
        $method->setAccessible(true);

        $response = $method->invoke($this->handler, $exception, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('系統發生內部錯誤，請稍後再試', $data['message']);
        $this->assertEquals('INTERNAL_SERVER_ERROR', $data['error_code']);
        $this->assertEquals([], $data['details']);
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

        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('sanitizeTrace');
        $method->setAccessible(true);

        $result = $method->invoke($this->handler, $trace);

        $this->assertEquals('short_string', $result[0]['args'][0]);
        $this->assertEquals('[長字串已隱藏]', $result[0]['args'][1]);
        $this->assertEquals('[大陣列已隱藏]', $result[0]['args'][2]);
    }

    /**
     * 測試是否應該報告例外
     */
    public function test_should_report(): void
    {
        // API 例外（4xx）不應該被報告
        $apiException = new ApiException('測試', 'TEST', 400);
        $this->assertFalse($this->handler->shouldReport($apiException));

        // API 例外（5xx）應該被報告
        $serverException = new ApiException('伺服器錯誤', 'SERVER_ERROR', 500);
        $this->assertTrue($this->handler->shouldReport($serverException));

        // 一般例外應該被報告
        $generalException = new \Exception('一般錯誤');
        $this->assertTrue($this->handler->shouldReport($generalException));
    }
}
<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ApiException;
use App\Services\ResponseFormatter;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

/**
 * API 例外測試
 */
class ApiExceptionTest extends TestCase
{
    /**
     * 測試 API 例外的基本功能
     */
    public function test_api_exception_basic_functionality(): void
    {
        $message = '測試錯誤訊息';
        $errorCode = 'TEST_ERROR';
        $httpStatusCode = 400;
        $details = ['field' => 'value'];

        $exception = new ApiException($message, $errorCode, $httpStatusCode, $details);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($errorCode, $exception->getErrorCode());
        $this->assertEquals($httpStatusCode, $exception->getHttpStatusCode());
        $this->assertEquals($details, $exception->getDetails());
    }

    /**
     * 測試 API 例外的預設值
     */
    public function test_api_exception_default_values(): void
    {
        $exception = new ApiException();

        $this->assertEquals('發生未知錯誤', $exception->getMessage());
        $this->assertEquals('UNKNOWN_ERROR', $exception->getErrorCode());
        $this->assertEquals(500, $exception->getHttpStatusCode());
        $this->assertEquals([], $exception->getDetails());
    }

    /**
     * 測試設定詳細資訊
     */
    public function test_set_details(): void
    {
        $exception = new ApiException();
        $details = ['test' => 'data'];

        $result = $exception->setDetails($details);

        $this->assertSame($exception, $result);
        $this->assertEquals($details, $exception->getDetails());
    }

    /**
     * 測試轉換為 JSON 回應
     */
    public function test_to_json_response(): void
    {
        // 模擬 ResponseFormatter
        $this->app->bind(\App\Contracts\ResponseFormatterInterface::class, ResponseFormatter::class);

        $message = '測試錯誤';
        $errorCode = 'TEST_ERROR';
        $httpStatusCode = 400;
        $details = ['field' => 'error'];

        $exception = new ApiException($message, $errorCode, $httpStatusCode, $details);
        $response = $exception->toJsonResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($httpStatusCode, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals($message, $data['message']);
        $this->assertEquals($errorCode, $data['error_code']);
        $this->assertEquals($details, $data['details']);
    }
}
<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ValidationException;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Validator;
use Tests\TestCase;

/**
 * 驗證例外測試
 */
class ValidationExceptionTest extends TestCase
{
    /**
     * 測試驗證例外的基本功能
     */
    public function test_validation_exception_basic_functionality(): void
    {
        $message = '自定義驗證錯誤';
        $details = ['email' => ['電子郵件格式不正確']];

        $exception = new ValidationException($message, $details);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals('VALIDATION_ERROR', $exception->getErrorCode());
        $this->assertEquals(400, $exception->getHttpStatusCode());
        $this->assertEquals($details, $exception->getDetails());
    }

    /**
     * 測試驗證例外的預設值
     */
    public function test_validation_exception_default_values(): void
    {
        $exception = new ValidationException();

        $this->assertEquals('請求參數驗證失敗', $exception->getMessage());
        $this->assertEquals('VALIDATION_ERROR', $exception->getErrorCode());
        $this->assertEquals(400, $exception->getHttpStatusCode());
        $this->assertEquals([], $exception->getDetails());
    }

    /**
     * 測試從 Laravel 驗證器建立例外
     */
    public function test_from_validator(): void
    {
        // 建立模擬的驗證器
        $messageBag = new MessageBag([
            'email' => ['電子郵件欄位是必填的'],
            'password' => ['密碼長度至少需要 8 個字元']
        ]);

        $validator = $this->createMock(Validator::class);
        $validator->method('errors')->willReturn($messageBag);

        $exception = ValidationException::fromValidator($validator);

        $this->assertEquals('請求參數驗證失敗', $exception->getMessage());
        $this->assertEquals('VALIDATION_ERROR', $exception->getErrorCode());
        $this->assertEquals(400, $exception->getHttpStatusCode());
        
        $expectedDetails = [
            'email' => ['電子郵件欄位是必填的'],
            'password' => ['密碼長度至少需要 8 個字元']
        ];
        $this->assertEquals($expectedDetails, $exception->getDetails());
    }
}
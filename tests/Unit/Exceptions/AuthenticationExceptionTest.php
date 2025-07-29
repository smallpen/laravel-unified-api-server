<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\AuthenticationException;
use Tests\TestCase;

/**
 * 認證例外測試
 */
class AuthenticationExceptionTest extends TestCase
{
    /**
     * 測試認證例外的基本功能
     */
    public function test_authentication_exception_basic_functionality(): void
    {
        $message = '自定義認證錯誤';
        $details = ['token' => 'invalid'];

        $exception = new AuthenticationException($message, $details);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals('AUTHENTICATION_ERROR', $exception->getErrorCode());
        $this->assertEquals(401, $exception->getHttpStatusCode());
        $this->assertEquals($details, $exception->getDetails());
    }

    /**
     * 測試認證例外的預設值
     */
    public function test_authentication_exception_default_values(): void
    {
        $exception = new AuthenticationException();

        $this->assertEquals('身份驗證失敗', $exception->getMessage());
        $this->assertEquals('AUTHENTICATION_ERROR', $exception->getErrorCode());
        $this->assertEquals(401, $exception->getHttpStatusCode());
        $this->assertEquals([], $exception->getDetails());
    }

    /**
     * 測試無效 Token 例外
     */
    public function test_invalid_token(): void
    {
        $exception = AuthenticationException::invalidToken();

        $this->assertEquals('提供的 Token 無效或已過期', $exception->getMessage());
        $this->assertEquals('AUTHENTICATION_ERROR', $exception->getErrorCode());
        $this->assertEquals(401, $exception->getHttpStatusCode());
    }

    /**
     * 測試缺失 Token 例外
     */
    public function test_missing_token(): void
    {
        $exception = AuthenticationException::missingToken();

        $this->assertEquals('請求標頭中缺少 Bearer Token', $exception->getMessage());
        $this->assertEquals('AUTHENTICATION_ERROR', $exception->getErrorCode());
        $this->assertEquals(401, $exception->getHttpStatusCode());
    }

    /**
     * 測試過期 Token 例外
     */
    public function test_expired_token(): void
    {
        $exception = AuthenticationException::expiredToken();

        $this->assertEquals('Token 已過期，請重新取得授權', $exception->getMessage());
        $this->assertEquals('AUTHENTICATION_ERROR', $exception->getErrorCode());
        $this->assertEquals(401, $exception->getHttpStatusCode());
    }
}
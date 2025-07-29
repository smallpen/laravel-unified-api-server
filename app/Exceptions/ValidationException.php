<?php

namespace App\Exceptions;

/**
 * 驗證例外類別
 * 
 * 處理請求參數驗證失敗的情況
 */
class ValidationException extends ApiException
{
    /**
     * 建構函式
     * 
     * @param string $message 錯誤訊息
     * @param array $details 驗證錯誤詳細資訊
     */
    public function __construct(string $message = '請求參數驗證失敗', array $details = [])
    {
        parent::__construct($message, 'VALIDATION_ERROR', 400, $details);
    }

    /**
     * 從 Laravel 驗證器建立例外
     * 
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return static
     */
    public static function fromValidator(\Illuminate\Contracts\Validation\Validator $validator): static
    {
        return new static('請求參數驗證失敗', $validator->errors()->toArray());
    }
}
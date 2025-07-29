<?php

namespace App\Exceptions;

/**
 * 資源未找到例外類別
 * 
 * 處理請求的資源不存在的情況
 */
class NotFoundException extends ApiException
{
    /**
     * 建構函式
     * 
     * @param string $message 錯誤訊息
     * @param array $details 詳細錯誤資訊
     */
    public function __construct(string $message = '請求的資源不存在', array $details = [])
    {
        parent::__construct($message, 'NOT_FOUND', 404, $details);
    }

    /**
     * Action 不存在例外
     * 
     * @param string $actionType Action 類型
     * @return static
     */
    public static function actionNotFound(string $actionType): static
    {
        return new static(
            "找不到指定的 Action: {$actionType}",
            ['action_type' => $actionType]
        );
    }

    /**
     * 路由不存在例外
     * 
     * @param string $route 路由路徑
     * @return static
     */
    public static function routeNotFound(string $route): static
    {
        return new static(
            "找不到指定的路由: {$route}",
            ['route' => $route]
        );
    }
}
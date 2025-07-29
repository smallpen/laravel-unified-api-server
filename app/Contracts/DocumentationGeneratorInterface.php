<?php

namespace App\Contracts;

/**
 * API文件生成器介面
 * 
 * 定義API文件自動生成系統的標準方法
 * 支援從Action註解生成OpenAPI規格文件
 */
interface DocumentationGeneratorInterface
{
    /**
     * 生成完整的API文件
     * 
     * 掃描所有已註冊的Action並生成完整的API文件
     * 
     * @return array 完整的API文件陣列
     */
    public function generateDocumentation(): array;

    /**
     * 取得指定Action的文件資訊
     * 
     * @param string $actionType Action類型識別碼
     * @return array Action文件資訊陣列
     * @throws \InvalidArgumentException 當Action不存在時拋出
     */
    public function getActionDocumentation(string $actionType): array;

    /**
     * 匯出為OpenAPI格式
     * 
     * 將生成的API文件轉換為OpenAPI 3.0規格格式
     * 
     * @return string OpenAPI規格的JSON字串
     */
    public function exportToOpenApi(): string;

    /**
     * 取得API文件的基本資訊
     * 
     * @return array 包含API標題、版本、描述等基本資訊
     */
    public function getApiInfo(): array;

    /**
     * 設定API文件的基本資訊
     * 
     * @param array $info 基本資訊陣列
     */
    public function setApiInfo(array $info): void;

    /**
     * 取得所有Action的摘要資訊
     * 
     * @return array Action摘要資訊陣列
     */
    public function getActionsSummary(): array;

    /**
     * 驗證Action文件的完整性
     * 
     * @param string $actionType Action類型識別碼
     * @return array 驗證結果陣列，包含錯誤和警告
     */
    public function validateActionDocumentation(string $actionType): array;

    /**
     * 取得文件生成統計資訊
     * 
     * @return array 統計資訊陣列
     */
    public function getGenerationStatistics(): array;
}
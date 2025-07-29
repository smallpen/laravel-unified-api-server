<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Contracts\DocumentationGeneratorInterface;
use App\Services\ActionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * 文件生成功能測試
 */
class DocumentationGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentationGeneratorInterface $documentationGenerator;
    protected ActionRegistry $actionRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->documentationGenerator = app(DocumentationGeneratorInterface::class);
        $this->actionRegistry = app(ActionRegistry::class);
    }

    /**
     * 測試文件生成器服務註冊
     */
    public function test_documentation_generator_service_is_registered(): void
    {
        $this->assertInstanceOf(
            DocumentationGeneratorInterface::class,
            $this->documentationGenerator
        );
    }

    /**
     * 測試生成完整API文件
     */
    public function test_generate_full_api_documentation(): void
    {
        // 自動發現Action
        $this->actionRegistry->autoDiscoverActions();
        
        // 生成文件
        $documentation = $this->documentationGenerator->generateDocumentation();
        
        // 驗證文件結構
        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('info', $documentation);
        $this->assertArrayHasKey('actions', $documentation);
        $this->assertArrayHasKey('statistics', $documentation);
        $this->assertArrayHasKey('generated_at', $documentation);
        
        // 驗證API資訊
        $info = $documentation['info'];
        $this->assertArrayHasKey('title', $info);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('version', $info);
        
        // 驗證Action文件
        $this->assertNotEmpty($documentation['actions']);
        
        // 驗證統計資訊
        $statistics = $documentation['statistics'];
        $this->assertArrayHasKey('total_actions', $statistics);
        $this->assertArrayHasKey('successful_generations', $statistics);
        $this->assertArrayHasKey('failed_generations', $statistics);
        $this->assertArrayHasKey('generation_time', $statistics);
    }

    /**
     * 測試OpenAPI格式匯出
     */
    public function test_export_to_openapi_format(): void
    {
        // 自動發現Action
        $this->actionRegistry->autoDiscoverActions();
        
        // 匯出OpenAPI格式
        $openApiJson = $this->documentationGenerator->exportToOpenApi();
        
        // 驗證JSON格式
        $this->assertIsString($openApiJson);
        $openApi = json_decode($openApiJson, true);
        $this->assertIsArray($openApi);
        
        // 驗證OpenAPI結構
        $this->assertArrayHasKey('openapi', $openApi);
        $this->assertArrayHasKey('info', $openApi);
        $this->assertArrayHasKey('paths', $openApi);
        $this->assertArrayHasKey('components', $openApi);
        
        // 驗證OpenAPI版本
        $this->assertEquals('3.0.0', $openApi['openapi']);
        
        // 驗證路徑
        $this->assertArrayHasKey('/', $openApi['paths']);
        $this->assertArrayHasKey('post', $openApi['paths']['/']);
        
        // 驗證組件
        $this->assertArrayHasKey('securitySchemes', $openApi['components']);
        $this->assertArrayHasKey('schemas', $openApi['components']);
    }

    /**
     * 測試Action摘要資訊
     */
    public function test_get_actions_summary(): void
    {
        // 自動發現Action
        $this->actionRegistry->autoDiscoverActions();
        
        // 取得摘要
        $summary = $this->documentationGenerator->getActionsSummary();
        
        $this->assertIsArray($summary);
        $this->assertNotEmpty($summary);
        
        // 檢查每個Action的摘要格式
        foreach ($summary as $actionType => $info) {
            $this->assertIsString($actionType);
            $this->assertIsArray($info);
            $this->assertArrayHasKey('name', $info);
            $this->assertArrayHasKey('description', $info);
            $this->assertArrayHasKey('version', $info);
            $this->assertArrayHasKey('enabled', $info);
        }
    }

    /**
     * 測試文件驗證功能
     */
    public function test_validate_action_documentation(): void
    {
        // 自動發現Action
        $this->actionRegistry->autoDiscoverActions();
        
        $actions = $this->actionRegistry->getAllActions();
        $this->assertNotEmpty($actions);
        
        // 驗證第一個Action
        $actionType = array_key_first($actions);
        $validation = $this->documentationGenerator->validateActionDocumentation($actionType);
        
        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
        $this->assertIsBool($validation['valid']);
        $this->assertIsArray($validation['errors']);
        $this->assertIsArray($validation['warnings']);
    }

    /**
     * 測試Artisan命令 - 摘要模式
     */
    public function test_artisan_command_summary_mode(): void
    {
        $exitCode = Artisan::call('api:generate-docs', ['--summary' => true]);
        
        $this->assertEquals(0, $exitCode);
        
        $output = Artisan::output();
        $this->assertStringContainsString('Action摘要資訊', $output);
        $this->assertStringContainsString('統計資訊', $output);
    }

    /**
     * 測試Artisan命令 - 驗證模式
     */
    public function test_artisan_command_validation_mode(): void
    {
        $exitCode = Artisan::call('api:generate-docs', ['--validate' => true]);
        
        $this->assertEquals(0, $exitCode);
        
        $output = Artisan::output();
        $this->assertStringContainsString('開始驗證Action文件', $output);
        $this->assertStringContainsString('驗證完成', $output);
    }

    /**
     * 測試Artisan命令 - 生成OpenAPI文件
     */
    public function test_artisan_command_generate_openapi(): void
    {
        $outputPath = storage_path('app/test-openapi.json');
        
        // 確保檔案不存在
        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }
        
        $exitCode = Artisan::call('api:generate-docs', [
            '--format' => 'openapi',
            '--output' => $outputPath,
        ]);
        
        $this->assertEquals(0, $exitCode);
        $this->assertTrue(File::exists($outputPath));
        
        // 驗證生成的檔案內容
        $content = File::get($outputPath);
        $openApi = json_decode($content, true);
        
        $this->assertIsArray($openApi);
        $this->assertArrayHasKey('openapi', $openApi);
        $this->assertEquals('3.0.0', $openApi['openapi']);
        
        // 清理測試檔案
        File::delete($outputPath);
    }

    /**
     * 測試快取功能
     */
    public function test_documentation_caching(): void
    {
        // 自動發現Action
        $this->actionRegistry->autoDiscoverActions();
        
        // 第一次生成（應該會建立快取）
        $startTime1 = microtime(true);
        $doc1 = $this->documentationGenerator->generateDocumentation();
        $time1 = microtime(true) - $startTime1;
        
        // 第二次生成（應該使用快取）
        $startTime2 = microtime(true);
        $doc2 = $this->documentationGenerator->generateDocumentation();
        $time2 = microtime(true) - $startTime2;
        
        // 驗證快取效果（第二次應該更快）
        $this->assertLessThan($time1, $time2);
        $this->assertEquals($doc1['generated_at'], $doc2['generated_at']);
        
        // 清除快取後重新生成
        $this->documentationGenerator->clearCache();
        $doc3 = $this->documentationGenerator->generateDocumentation();
        
        // 時間戳應該不同
        $this->assertNotEquals($doc1['generated_at'], $doc3['generated_at']);
    }

    /**
     * 測試API資訊設定
     */
    public function test_api_info_configuration(): void
    {
        $customInfo = [
            'title' => '自訂API標題',
            'version' => '2.0.0',
            'description' => '這是自訂的API描述',
        ];
        
        $this->documentationGenerator->setApiInfo($customInfo);
        $apiInfo = $this->documentationGenerator->getApiInfo();
        
        $this->assertEquals('自訂API標題', $apiInfo['title']);
        $this->assertEquals('2.0.0', $apiInfo['version']);
        $this->assertEquals('這是自訂的API描述', $apiInfo['description']);
        
        // 驗證設定後的文件生成
        $documentation = $this->documentationGenerator->generateDocumentation();
        $this->assertEquals('自訂API標題', $documentation['info']['title']);
        $this->assertEquals('2.0.0', $documentation['info']['version']);
    }

    /**
     * 測試統計資訊
     */
    public function test_generation_statistics(): void
    {
        // 自動發現Action
        $this->actionRegistry->autoDiscoverActions();
        
        // 生成文件
        $this->documentationGenerator->generateDocumentation();
        
        // 取得統計資訊
        $statistics = $this->documentationGenerator->getGenerationStatistics();
        
        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('total_actions', $statistics);
        $this->assertArrayHasKey('successful_generations', $statistics);
        $this->assertArrayHasKey('failed_generations', $statistics);
        $this->assertArrayHasKey('generation_time', $statistics);
        
        $this->assertIsInt($statistics['total_actions']);
        $this->assertIsInt($statistics['successful_generations']);
        $this->assertIsInt($statistics['failed_generations']);
        $this->assertIsString($statistics['generation_time']);
    }
}
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\DocumentationGenerator;
use App\Services\ActionRegistry;
use App\Contracts\ActionInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Mockery;

/**
 * DocumentationGenerator單元測試
 */
class DocumentationGeneratorTest extends TestCase
{
    protected DocumentationGenerator $documentationGenerator;
    protected ActionRegistry $actionRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->actionRegistry = Mockery::mock(ActionRegistry::class);
        $this->documentationGenerator = new DocumentationGenerator($this->actionRegistry);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 測試建構子初始化
     */
    public function test_constructor_initializes_correctly(): void
    {
        $generator = new DocumentationGenerator($this->actionRegistry);
        
        $apiInfo = $generator->getApiInfo();
        
        $this->assertIsArray($apiInfo);
        $this->assertArrayHasKey('title', $apiInfo);
        $this->assertArrayHasKey('description', $apiInfo);
        $this->assertArrayHasKey('version', $apiInfo);
    }

    /**
     * 測試取得API基本資訊
     */
    public function test_get_api_info(): void
    {
        $apiInfo = $this->documentationGenerator->getApiInfo();
        
        $this->assertIsArray($apiInfo);
        $this->assertArrayHasKey('title', $apiInfo);
        $this->assertArrayHasKey('description', $apiInfo);
        $this->assertArrayHasKey('version', $apiInfo);
        $this->assertArrayHasKey('contact', $apiInfo);
        $this->assertArrayHasKey('license', $apiInfo);
        $this->assertArrayHasKey('servers', $apiInfo);
    }

    /**
     * 測試設定API基本資訊
     */
    public function test_set_api_info(): void
    {
        $newInfo = [
            'title' => '測試API',
            'version' => '2.0.0',
            'description' => '這是測試用的API',
        ];

        $this->documentationGenerator->setApiInfo($newInfo);
        $apiInfo = $this->documentationGenerator->getApiInfo();

        $this->assertEquals('測試API', $apiInfo['title']);
        $this->assertEquals('2.0.0', $apiInfo['version']);
        $this->assertEquals('這是測試用的API', $apiInfo['description']);
    }

    /**
     * 測試取得Action文件資訊
     */
    public function test_get_action_documentation(): void
    {
        $mockAction = $this->createMockAction();
        
        $this->actionRegistry
            ->shouldReceive('hasAction')
            ->with('test.action')
            ->andReturn(true);
            
        $this->actionRegistry
            ->shouldReceive('resolve')
            ->with('test.action')
            ->andReturn($mockAction);

        $documentation = $this->documentationGenerator->getActionDocumentation('test.action');

        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('name', $documentation);
        $this->assertArrayHasKey('description', $documentation);
        $this->assertArrayHasKey('parameters', $documentation);
        $this->assertArrayHasKey('responses', $documentation);
        $this->assertArrayHasKey('examples', $documentation);
        $this->assertArrayHasKey('action_type', $documentation);
        $this->assertArrayHasKey('class_name', $documentation);
        $this->assertArrayHasKey('generated_at', $documentation);
    }

    /**
     * 測試取得不存在的Action文件
     */
    public function test_get_action_documentation_not_found(): void
    {
        $this->actionRegistry
            ->shouldReceive('hasAction')
            ->with('nonexistent.action')
            ->andReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('找不到指定的Action: nonexistent.action');

        $this->documentationGenerator->getActionDocumentation('nonexistent.action');
    }

    /**
     * 測試生成完整文件
     */
    public function test_generate_documentation(): void
    {
        $mockAction = $this->createMockAction();
        
        $this->actionRegistry
            ->shouldReceive('getAllActions')
            ->andReturn(['test.action' => 'TestAction']);
            
        $this->actionRegistry
            ->shouldReceive('hasAction')
            ->with('test.action')
            ->andReturn(true);
            
        $this->actionRegistry
            ->shouldReceive('resolve')
            ->with('test.action')
            ->andReturn($mockAction);

        $documentation = $this->documentationGenerator->generateDocumentation();

        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('info', $documentation);
        $this->assertArrayHasKey('actions', $documentation);
        $this->assertArrayHasKey('statistics', $documentation);
        $this->assertArrayHasKey('generated_at', $documentation);
        
        $this->assertArrayHasKey('test.action', $documentation['actions']);
        
        $statistics = $documentation['statistics'];
        $this->assertArrayHasKey('total_actions', $statistics);
        $this->assertArrayHasKey('successful_generations', $statistics);
        $this->assertArrayHasKey('failed_generations', $statistics);
        $this->assertArrayHasKey('generation_time', $statistics);
    }

    /**
     * 測試匯出OpenAPI格式
     */
    public function test_export_to_open_api(): void
    {
        $mockAction = $this->createMockAction();
        
        $this->actionRegistry
            ->shouldReceive('getAllActions')
            ->andReturn(['test.action' => 'TestAction']);
            
        $this->actionRegistry
            ->shouldReceive('hasAction')
            ->with('test.action')
            ->andReturn(true);
            
        $this->actionRegistry
            ->shouldReceive('resolve')
            ->with('test.action')
            ->andReturn($mockAction);

        $openApiJson = $this->documentationGenerator->exportToOpenApi();

        $this->assertIsString($openApiJson);
        
        $openApi = json_decode($openApiJson, true);
        $this->assertIsArray($openApi);
        $this->assertArrayHasKey('openapi', $openApi);
        $this->assertArrayHasKey('info', $openApi);
        $this->assertArrayHasKey('paths', $openApi);
        $this->assertArrayHasKey('components', $openApi);
        
        $this->assertEquals('3.0.0', $openApi['openapi']);
        $this->assertArrayHasKey('/', $openApi['paths']);
        $this->assertArrayHasKey('post', $openApi['paths']['/']);
    }

    /**
     * 測試取得Action摘要
     */
    public function test_get_actions_summary(): void
    {
        $mockAction = $this->createMockAction();
        
        $this->actionRegistry
            ->shouldReceive('getAllActions')
            ->andReturn(['test.action' => 'TestAction']);
            
        $this->actionRegistry
            ->shouldReceive('resolve')
            ->with('test.action')
            ->andReturn($mockAction);

        $summary = $this->documentationGenerator->getActionsSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('test.action', $summary);
        
        $actionSummary = $summary['test.action'];
        $this->assertArrayHasKey('name', $actionSummary);
        $this->assertArrayHasKey('description', $actionSummary);
        $this->assertArrayHasKey('version', $actionSummary);
        $this->assertArrayHasKey('enabled', $actionSummary);
        $this->assertArrayHasKey('required_permissions', $actionSummary);
        $this->assertArrayHasKey('parameter_count', $actionSummary);
        $this->assertArrayHasKey('example_count', $actionSummary);
    }

    /**
     * 測試驗證Action文件
     */
    public function test_validate_action_documentation(): void
    {
        $mockAction = $this->createMockAction();
        
        $this->actionRegistry
            ->shouldReceive('hasAction')
            ->with('test.action')
            ->andReturn(true);
            
        $this->actionRegistry
            ->shouldReceive('resolve')
            ->with('test.action')
            ->andReturn($mockAction);

        $validation = $this->documentationGenerator->validateActionDocumentation('test.action');

        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
        $this->assertIsBool($validation['valid']);
        $this->assertIsArray($validation['errors']);
        $this->assertIsArray($validation['warnings']);
    }

    /**
     * 測試驗證缺少描述的Action
     */
    public function test_validate_action_documentation_with_default_description(): void
    {
        $mockAction = Mockery::mock(ActionInterface::class);
        $mockAction->shouldReceive('getDocumentation')->andReturn([
            'name' => '測試Action',
            'description' => '此Action尚未提供描述', // 預設描述
            'version' => '1.0.0',
            'enabled' => true,
            'required_permissions' => [],
            'parameters' => [],
            'responses' => [],
            'examples' => [],
        ]);
        
        $this->actionRegistry
            ->shouldReceive('hasAction')
            ->with('test.action')
            ->andReturn(true);
            
        $this->actionRegistry
            ->shouldReceive('resolve')
            ->with('test.action')
            ->andReturn($mockAction);

        $validation = $this->documentationGenerator->validateActionDocumentation('test.action');

        $this->assertTrue($validation['valid']);
        $this->assertContains('使用預設描述，建議提供具體的Action描述', $validation['warnings']);
    }

    /**
     * 測試取得生成統計資訊
     */
    public function test_get_generation_statistics(): void
    {
        $mockAction = $this->createMockAction();
        
        $this->actionRegistry
            ->shouldReceive('getAllActions')
            ->andReturn(['test.action' => 'TestAction']);
            
        $this->actionRegistry
            ->shouldReceive('hasAction')
            ->with('test.action')
            ->andReturn(true);
            
        $this->actionRegistry
            ->shouldReceive('resolve')
            ->with('test.action')
            ->andReturn($mockAction);

        $statistics = $this->documentationGenerator->getGenerationStatistics();

        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('total_actions', $statistics);
        $this->assertArrayHasKey('successful_generations', $statistics);
        $this->assertArrayHasKey('failed_generations', $statistics);
        $this->assertArrayHasKey('generation_time', $statistics);
    }

    /**
     * 測試清除快取
     */
    public function test_clear_cache(): void
    {
        $mockAction = $this->createMockAction();
        
        $this->actionRegistry
            ->shouldReceive('getAllActions')
            ->andReturn(['test.action' => 'TestAction']);
            
        $this->actionRegistry
            ->shouldReceive('hasAction')
            ->with('test.action')
            ->andReturn(true);
            
        $this->actionRegistry
            ->shouldReceive('resolve')
            ->with('test.action')
            ->andReturn($mockAction);

        // 第一次生成文件
        $doc1 = $this->documentationGenerator->generateDocumentation();
        
        // 清除快取
        $this->documentationGenerator->clearCache();
        
        // 第二次生成文件（應該重新生成）
        $doc2 = $this->documentationGenerator->generateDocumentation();
        
        // 兩次生成的時間戳應該不同
        $this->assertNotEquals($doc1['generated_at'], $doc2['generated_at']);
    }

    /**
     * 測試重新生成文件
     */
    public function test_regenerate_documentation(): void
    {
        $mockAction = $this->createMockAction();
        
        $this->actionRegistry
            ->shouldReceive('getAllActions')
            ->andReturn(['test.action' => 'TestAction']);
            
        $this->actionRegistry
            ->shouldReceive('hasAction')
            ->with('test.action')
            ->andReturn(true);
            
        $this->actionRegistry
            ->shouldReceive('resolve')
            ->with('test.action')
            ->andReturn($mockAction);

        $documentation = $this->documentationGenerator->regenerateDocumentation();

        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('info', $documentation);
        $this->assertArrayHasKey('actions', $documentation);
        $this->assertArrayHasKey('statistics', $documentation);
        $this->assertArrayHasKey('generated_at', $documentation);
    }

    /**
     * 建立模擬Action實例
     * 
     * @return \Mockery\MockInterface
     */
    protected function createMockAction()
    {
        $mockAction = Mockery::mock(ActionInterface::class);
        
        $mockAction->shouldReceive('getDocumentation')->andReturn([
            'name' => '測試Action',
            'description' => '這是一個測試用的Action',
            'version' => '1.0.0',
            'enabled' => true,
            'required_permissions' => ['test.permission'],
            'parameters' => [
                'test_param' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => '測試參數',
                    'example' => 'test_value',
                ],
            ],
            'responses' => [
                'success' => [
                    'status' => 'success',
                    'data' => ['result' => 'test'],
                ],
                'error' => [
                    'status' => 'error',
                    'message' => '測試錯誤',
                    'error_code' => 'TEST_ERROR',
                ],
            ],
            'examples' => [
                [
                    'title' => '測試範例',
                    'request' => [
                        'action_type' => 'test.action',
                        'test_param' => 'test_value',
                    ],
                    'response' => [
                        'status' => 'success',
                        'data' => ['result' => 'test'],
                    ],
                ],
            ],
        ]);

        return $mockAction;
    }
}
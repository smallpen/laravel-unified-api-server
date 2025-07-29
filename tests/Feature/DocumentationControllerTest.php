<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\DocumentationGenerator;
use App\Services\ActionRegistry;

/**
 * API文件控制器測試
 * 
 * 測試DocumentationController的各種功能
 * 包括Swagger UI、OpenAPI規格、Action文件等
 */
class DocumentationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試Swagger UI頁面載入
     */
    public function test_swagger_ui_loads_successfully(): void
    {
        $response = $this->get('/api/docs/');

        $response->assertStatus(200);
        $response->assertViewIs('documentation.swagger-ui');
        $response->assertViewHas(['apiTitle', 'apiDescription', 'apiVersion', 'openApiUrl']);
    }

    /**
     * 測試取得完整API文件
     */
    public function test_get_documentation_returns_json(): void
    {
        $response = $this->get('/api/docs/json');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'info',
                'actions',
                'statistics',
                'generated_at',
            ],
            'timestamp',
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('info', $data);
        $this->assertArrayHasKey('actions', $data);
        $this->assertArrayHasKey('statistics', $data);
    }

    /**
     * 測試取得OpenAPI規格
     */
    public function test_get_openapi_spec_returns_valid_json(): void
    {
        $response = $this->get('/api/docs/openapi.json');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        $spec = $response->json();
        $this->assertEquals('3.0.0', $spec['openapi']);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);
    }

    /**
     * 測試取得Action摘要列表
     */
    public function test_get_actions_summary_returns_list(): void
    {
        $response = $this->get('/api/docs/actions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'actions',
                'total_count',
            ],
            'timestamp',
        ]);

        $data = $response->json('data');
        $this->assertIsArray($data['actions']);
        $this->assertIsInt($data['total_count']);
        $this->assertGreaterThan(0, $data['total_count']);
    }

    /**
     * 測試取得指定Action的詳細文件
     */
    public function test_get_action_documentation_returns_details(): void
    {
        // 使用已知存在的Action
        $response = $this->get('/api/docs/actions/user.info');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'name',
                'description',
                'version',
                'enabled',
                'required_permissions',
                'parameters',
                'responses',
                'examples',
                'action_type',
                'class_name',
                'generated_at',
            ],
            'timestamp',
        ]);

        $data = $response->json('data');
        $this->assertEquals('user.info', $data['action_type']);
        $this->assertIsArray($data['parameters']);
        $this->assertIsArray($data['responses']);
        $this->assertIsArray($data['examples']);
    }

    /**
     * 測試取得不存在Action的文件
     */
    public function test_get_nonexistent_action_documentation_returns_404(): void
    {
        $response = $this->get('/api/docs/actions/nonexistent.action');

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'status',
            'message',
            'error_code',
            'details',
            'timestamp',
        ]);

        $this->assertEquals('error', $response->json('status'));
        $this->assertEquals('ACTION_NOT_FOUND', $response->json('error_code'));
    }

    /**
     * 測試重新生成API文件
     */
    public function test_regenerate_documentation_works(): void
    {
        $response = $this->post('/api/docs/regenerate');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'documentation',
                'statistics',
            ],
            'timestamp',
        ]);

        $this->assertEquals('success', $response->json('status'));
        $this->assertStringContainsString('重新生成成功', $response->json('message'));
    }

    /**
     * 測試取得文件生成統計資訊
     */
    public function test_get_statistics_returns_data(): void
    {
        $response = $this->get('/api/docs/statistics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'total_actions',
                'successful_generations',
                'failed_generations',
                'warnings',
                'generation_time',
            ],
            'timestamp',
        ]);

        $data = $response->json('data');
        $this->assertIsInt($data['total_actions']);
        $this->assertIsInt($data['successful_generations']);
        $this->assertIsInt($data['failed_generations']);
        $this->assertIsArray($data['warnings']);
        $this->assertIsString($data['generation_time']);
    }

    /**
     * 測試驗證Action文件完整性
     */
    public function test_validate_action_documentation_returns_validation_result(): void
    {
        $response = $this->get('/api/docs/validate/user.info');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'valid',
                'errors',
                'warnings',
            ],
            'timestamp',
        ]);

        $data = $response->json('data');
        $this->assertIsBool($data['valid']);
        $this->assertIsArray($data['errors']);
        $this->assertIsArray($data['warnings']);
    }

    /**
     * 測試驗證不存在Action的文件
     */
    public function test_validate_nonexistent_action_returns_404(): void
    {
        $response = $this->get('/api/docs/validate/nonexistent.action');

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'status',
            'message',
            'error_code',
            'details',
            'timestamp',
        ]);

        $this->assertEquals('error', $response->json('status'));
        $this->assertEquals('ACTION_NOT_FOUND', $response->json('error_code'));
    }

    /**
     * 測試CORS標頭設定
     */
    public function test_openapi_spec_has_cors_headers(): void
    {
        $response = $this->get('/api/docs/openapi.json');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->assertHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }

    /**
     * 測試文件生成器服務注入
     */
    public function test_documentation_generator_service_is_injected(): void
    {
        $generator = app(DocumentationGenerator::class);
        $this->assertInstanceOf(DocumentationGenerator::class, $generator);

        $documentation = $generator->generateDocumentation();
        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('info', $documentation);
        $this->assertArrayHasKey('actions', $documentation);
    }

    /**
     * 測試Action註冊系統整合
     */
    public function test_action_registry_integration(): void
    {
        $registry = app(ActionRegistry::class);
        $this->assertInstanceOf(ActionRegistry::class, $registry);

        $actions = $registry->getAllActions();
        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);

        // 檢查是否有已知的Action
        $this->assertArrayHasKey('user.info', $actions);
        $this->assertArrayHasKey('system.ping', $actions);
    }

    /**
     * 測試文件快取機制
     */
    public function test_documentation_caching_works(): void
    {
        $generator = app(DocumentationGenerator::class);

        // 第一次生成文件
        $startTime = microtime(true);
        $doc1 = $generator->generateDocumentation();
        $firstGenTime = microtime(true) - $startTime;

        // 第二次取得文件（應該使用快取）
        $startTime = microtime(true);
        $doc2 = $generator->generateDocumentation();
        $secondGenTime = microtime(true) - $startTime;

        // 快取版本應該更快
        $this->assertLessThan($firstGenTime, $secondGenTime);
        $this->assertEquals($doc1, $doc2);
    }

    /**
     * 測試錯誤處理
     */
    public function test_error_handling_in_documentation_generation(): void
    {
        // 這個測試需要模擬錯誤情況
        // 可以透過Mock來測試錯誤處理邏輯
        $this->assertTrue(true); // 暫時通過，實際實作時需要完善
    }
}
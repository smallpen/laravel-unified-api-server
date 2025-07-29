<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Swagger UI整合測試
 * 
 * 測試API文件系統的各種功能
 */
class SwaggerUIIntegrationTest extends TestCase
{
    /**
     * 測試Swagger UI頁面載入
     */
    public function test_swagger_ui_page_loads_successfully(): void
    {
        $response = $this->get('/api/docs');
        
        $response->assertStatus(200);
        $response->assertSee('API文件');
        $response->assertSee('swagger-ui');
    }

    /**
     * 測試OpenAPI規格端點
     */
    public function test_openapi_spec_endpoint_returns_valid_json(): void
    {
        $response = $this->get('/api/docs/openapi.json');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        
        $data = $response->json();
        $this->assertArrayHasKey('openapi', $data);
        $this->assertArrayHasKey('info', $data);
        $this->assertArrayHasKey('paths', $data);
        $this->assertArrayHasKey('components', $data);
        $this->assertEquals('3.0.0', $data['openapi']);
    }

    /**
     * 測試API文件JSON端點
     */
    public function test_documentation_json_endpoint_returns_complete_data(): void
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
        
        $data = $response->json();
        $this->assertEquals('success', $data['status']);
        $this->assertIsArray($data['data']['actions']);
    }

    /**
     * 測試Action摘要端點
     */
    public function test_actions_summary_endpoint_returns_action_list(): void
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
        
        $data = $response->json();
        $this->assertEquals('success', $data['status']);
        $this->assertIsInt($data['data']['total_count']);
        $this->assertGreaterThan(0, $data['data']['total_count']);
    }

    /**
     * 測試文件狀態端點
     */
    public function test_documentation_status_endpoint_returns_status_info(): void
    {
        $response = $this->get('/api/docs/status');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'is_up_to_date',
                'total_actions',
                'successful_generations',
                'failed_generations',
                'warnings_count',
                'cache_status',
            ],
            'timestamp',
        ]);
        
        $data = $response->json();
        $this->assertEquals('success', $data['status']);
        $this->assertIsBool($data['data']['is_up_to_date']);
        $this->assertIsInt($data['data']['total_actions']);
    }

    /**
     * 測試文件重新生成端點
     */
    public function test_documentation_regeneration_endpoint_works(): void
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
        
        $data = $response->json();
        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('actions', $data['data']['documentation']);
    }

    /**
     * 測試Action變更歷史端點
     */
    public function test_action_changes_endpoint_returns_change_history(): void
    {
        $response = $this->get('/api/docs/changes');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'recent_changes',
                'action_summary',
                'total_actions',
            ],
            'timestamp',
        ]);
        
        $data = $response->json();
        $this->assertEquals('success', $data['status']);
        $this->assertIsArray($data['data']['recent_changes']);
        $this->assertIsInt($data['data']['total_actions']);
    }

    /**
     * 測試特定Action文件端點
     */
    public function test_specific_action_documentation_endpoint(): void
    {
        // 先取得可用的Action列表
        $actionsResponse = $this->get('/api/docs/actions');
        $actionsData = $actionsResponse->json();
        
        if (empty($actionsData['data']['actions'])) {
            $this->markTestSkipped('沒有可用的Action進行測試');
        }
        
        $firstActionType = array_key_first($actionsData['data']['actions']);
        
        $response = $this->get("/api/docs/actions/{$firstActionType}");
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'action_type',
                'class_name',
                'name',
                'description',
                'parameters',
                'responses',
                'examples',
                'generated_at',
            ],
            'timestamp',
        ]);
        
        $data = $response->json();
        $this->assertEquals('success', $data['status']);
        $this->assertEquals($firstActionType, $data['data']['action_type']);
    }

    /**
     * 測試不存在的Action文件端點
     */
    public function test_nonexistent_action_documentation_returns_404(): void
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
        
        $data = $response->json();
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('ACTION_NOT_FOUND', $data['error_code']);
    }

    /**
     * 測試Action文件驗證端點
     */
    public function test_action_validation_endpoint(): void
    {
        // 先取得可用的Action列表
        $actionsResponse = $this->get('/api/docs/actions');
        $actionsData = $actionsResponse->json();
        
        if (empty($actionsData['data']['actions'])) {
            $this->markTestSkipped('沒有可用的Action進行測試');
        }
        
        $firstActionType = array_key_first($actionsData['data']['actions']);
        
        $response = $this->get("/api/docs/validate/{$firstActionType}");
        
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
        
        $data = $response->json();
        $this->assertEquals('success', $data['status']);
        $this->assertIsBool($data['data']['valid']);
        $this->assertIsArray($data['data']['errors']);
        $this->assertIsArray($data['data']['warnings']);
    }

    /**
     * 測試統計資訊端點
     */
    public function test_statistics_endpoint_returns_generation_stats(): void
    {
        $response = $this->get('/api/docs/statistics');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data',
            'timestamp',
        ]);
        
        $data = $response->json();
        $this->assertEquals('success', $data['status']);
        $this->assertIsArray($data['data']);
    }
}
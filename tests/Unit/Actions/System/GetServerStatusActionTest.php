<?php

namespace Tests\Unit\Actions\System;

use Tests\TestCase;
use App\Actions\System\GetServerStatusAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * GetServerStatusAction單元測試
 */
class GetServerStatusActionTest extends TestCase
{
    use RefreshDatabase;

    private GetServerStatusAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetServerStatusAction();
    }

    /**
     * 測試Action類型
     */
    public function test_action_type(): void
    {
        $this->assertEquals('system.server_status', $this->action->getActionType());
    }

    /**
     * 測試所需權限
     */
    public function test_required_permissions(): void
    {
        $permissions = $this->action->getRequiredPermissions();
        
        $this->assertEquals(['system.server_status'], $permissions);
    }

    /**
     * 測試基本伺服器狀態查詢
     */
    public function test_basic_server_status(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/', 'POST', []);

        $result = $this->action->execute($request, $user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('server_status', $result);

        $status = $result['server_status'];
        $this->assertArrayHasKey('uptime', $status);
        $this->assertArrayHasKey('memory_usage', $status);
        $this->assertArrayHasKey('disk_usage', $status);
        $this->assertArrayHasKey('load_average', $status);
        $this->assertArrayHasKey('timestamp', $status);

        // 檢查timestamp格式
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $status['timestamp']);
    }

    /**
     * 測試包含詳細資訊的伺服器狀態查詢
     */
    public function test_server_status_with_details(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/', 'POST', [
            'include_details' => true
        ]);

        $result = $this->action->execute($request, $user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('server_status', $result);

        $status = $result['server_status'];
        $this->assertArrayHasKey('details', $status);

        $details = $status['details'];
        $this->assertArrayHasKey('php_version', $details);
        $this->assertArrayHasKey('laravel_version', $details);
        $this->assertArrayHasKey('server_software', $details);
        $this->assertArrayHasKey('database_status', $details);
        $this->assertArrayHasKey('cache_status', $details);
        $this->assertArrayHasKey('queue_status', $details);
    }

    /**
     * 測試記憶體使用資訊格式
     */
    public function test_memory_usage_format(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/', 'POST', []);

        $result = $this->action->execute($request, $user);

        $memoryUsage = $result['server_status']['memory_usage'];
        
        $this->assertArrayHasKey('current', $memoryUsage);
        $this->assertArrayHasKey('peak', $memoryUsage);
        $this->assertArrayHasKey('limit', $memoryUsage);
        $this->assertArrayHasKey('current_bytes', $memoryUsage);
        $this->assertArrayHasKey('peak_bytes', $memoryUsage);

        // 檢查格式化的記憶體大小包含單位
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)?\s+(B|KB|MB|GB|TB)$/', $memoryUsage['current']);
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)?\s+(B|KB|MB|GB|TB)$/', $memoryUsage['peak']);

        // 檢查位元組數是數字
        $this->assertIsInt($memoryUsage['current_bytes']);
        $this->assertIsInt($memoryUsage['peak_bytes']);
    }

    /**
     * 測試磁碟使用資訊格式
     */
    public function test_disk_usage_format(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/', 'POST', []);

        $result = $this->action->execute($request, $user);

        $diskUsage = $result['server_status']['disk_usage'];
        
        $this->assertArrayHasKey('total', $diskUsage);
        $this->assertArrayHasKey('used', $diskUsage);
        $this->assertArrayHasKey('free', $diskUsage);
        $this->assertArrayHasKey('usage_percent', $diskUsage);
        $this->assertArrayHasKey('status', $diskUsage);

        // 檢查使用百分比是數字
        $this->assertIsFloat($diskUsage['usage_percent']);
        $this->assertGreaterThanOrEqual(0, $diskUsage['usage_percent']);
        $this->assertLessThanOrEqual(100, $diskUsage['usage_percent']);

        // 檢查狀態值
        $this->assertContains($diskUsage['status'], ['normal', 'warning', 'error']);
    }

    /**
     * 測試負載平均值格式
     */
    public function test_load_average_format(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/', 'POST', []);

        $result = $this->action->execute($request, $user);

        $loadAverage = $result['server_status']['load_average'];
        
        $this->assertArrayHasKey('1min', $loadAverage);
        $this->assertArrayHasKey('5min', $loadAverage);
        $this->assertArrayHasKey('15min', $loadAverage);
        $this->assertArrayHasKey('status', $loadAverage);

        // 檢查狀態值
        $this->assertContains($loadAverage['status'], ['available', 'unavailable', 'error']);

        if ($loadAverage['status'] === 'available') {
            $this->assertIsFloat($loadAverage['1min']);
            $this->assertIsFloat($loadAverage['5min']);
            $this->assertIsFloat($loadAverage['15min']);
        }
    }

    /**
     * 測試資料庫狀態檢查
     */
    public function test_database_status_check(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/', 'POST', [
            'include_details' => true
        ]);

        $result = $this->action->execute($request, $user);

        $databaseStatus = $result['server_status']['details']['database_status'];
        
        $this->assertArrayHasKey('status', $databaseStatus);
        $this->assertArrayHasKey('response_time_ms', $databaseStatus);

        // 在測試環境中，資料庫應該是連接的
        $this->assertEquals('connected', $databaseStatus['status']);
        $this->assertIsFloat($databaseStatus['response_time_ms']);
        $this->assertGreaterThanOrEqual(0, $databaseStatus['response_time_ms']);
    }

    /**
     * 測試快取狀態檢查
     */
    public function test_cache_status_check(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/', 'POST', [
            'include_details' => true
        ]);

        $result = $this->action->execute($request, $user);

        $cacheStatus = $result['server_status']['details']['cache_status'];
        
        $this->assertArrayHasKey('status', $cacheStatus);
        $this->assertArrayHasKey('response_time_ms', $cacheStatus);
        $this->assertArrayHasKey('driver', $cacheStatus);

        $this->assertContains($cacheStatus['status'], ['working', 'error']);
        $this->assertIsFloat($cacheStatus['response_time_ms']);
        $this->assertGreaterThanOrEqual(0, $cacheStatus['response_time_ms']);
    }

    /**
     * 測試參數驗證 - 有效參數
     */
    public function test_validation_passes_with_valid_parameters(): void
    {
        $request = Request::create('/', 'POST', [
            'include_details' => true
        ]);

        $result = $this->action->validate($request);

        $this->assertTrue($result);
    }

    /**
     * 測試參數驗證 - 布林值參數
     */
    public function test_validation_with_boolean_parameter(): void
    {
        $request = Request::create('/', 'POST', [
            'include_details' => false
        ]);

        $result = $this->action->validate($request);

        $this->assertTrue($result);
    }

    /**
     * 測試參數驗證 - 無效的布林值
     */
    public function test_validation_fails_with_invalid_boolean(): void
    {
        $request = Request::create('/', 'POST', [
            'include_details' => 'invalid'
        ]);

        $this->expectException(ValidationException::class);

        $this->action->validate($request);
    }

    /**
     * 測試無參數的請求
     */
    public function test_request_without_parameters(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/', 'POST', []);

        $result = $this->action->execute($request, $user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('server_status', $result);
        
        // 不應該包含詳細資訊
        $this->assertArrayNotHasKey('details', $result['server_status']);
    }

    /**
     * 測試文件資訊
     */
    public function test_documentation(): void
    {
        $documentation = $this->action->getDocumentation();

        $this->assertIsArray($documentation);
        $this->assertEquals('取得伺服器狀態', $documentation['name']);
        $this->assertEquals('取得伺服器資源使用狀況和效能指標', $documentation['description']);
        $this->assertArrayHasKey('parameters', $documentation);
        $this->assertArrayHasKey('responses', $documentation);
        $this->assertArrayHasKey('examples', $documentation);

        // 檢查參數文件
        $parameters = $documentation['parameters'];
        $this->assertArrayHasKey('include_details', $parameters);
        $this->assertEquals('boolean', $parameters['include_details']['type']);
        $this->assertFalse($parameters['include_details']['required']);
    }

    /**
     * 測試位元組格式化功能
     */
    public function test_bytes_formatting(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/', 'POST', []);

        $result = $this->action->execute($request, $user);

        $memoryUsage = $result['server_status']['memory_usage'];
        
        // 檢查格式化的記憶體大小
        $this->assertIsString($memoryUsage['current']);
        $this->assertIsString($memoryUsage['peak']);
        
        // 應該包含數字和單位
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)?\s+(B|KB|MB|GB|TB)$/', $memoryUsage['current']);
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)?\s+(B|KB|MB|GB|TB)$/', $memoryUsage['peak']);
    }
}
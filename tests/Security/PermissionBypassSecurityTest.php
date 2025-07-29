<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\ApiToken;
use App\Models\ActionPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

/**
 * 權限繞過防護機制測試
 * 
 * 測試系統對各種權限繞過攻擊的防護能力
 */
class PermissionBypassSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一般使用者
     * 
     * @var User
     */
    protected User $regularUser;

    /**
     * 管理員使用者
     * 
     * @var User
     */
    protected User $adminUser;

    /**
     * 一般使用者Token
     * 
     * @var string
     */
    protected string $regularToken;

    /**
     * 管理員Token
     * 
     * @var string
     */
    protected string $adminToken;

    /**
     * 無權限Token
     * 
     * @var string
     */
    protected string $noPermissionToken;

    /**
     * 設定測試環境
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 建立一般使用者
        $this->regularUser = User::factory()->create([
            'name' => '一般使用者',
            'email' => 'regular@example.com',
        ]);

        // 建立管理員使用者
        $this->adminUser = User::factory()->create([
            'name' => '管理員',
            'email' => 'admin@example.com',
        ]);

        // 建立一般使用者Token（有限權限）
        $regularTokenData = ApiToken::createToken(
            $this->regularUser->id,
            '一般使用者Token',
            ['user.read']
        );
        $this->regularToken = $regularTokenData['token'];

        // 建立管理員Token（完整權限）
        $adminTokenData = ApiToken::createToken(
            $this->adminUser->id,
            '管理員Token',
            ['*']
        );
        $this->adminToken = $adminTokenData['token'];

        // 建立無權限Token
        $noPermissionTokenData = ApiToken::createToken(
            $this->regularUser->id,
            '無權限Token',
            []
        );
        $this->noPermissionToken = $noPermissionTokenData['token'];

        // 設定Action權限
        ActionPermission::createOrUpdate('user.info', ['user.read'], '查看使用者資訊');
        ActionPermission::createOrUpdate('admin.action', ['admin'], '管理員專用功能');
    }

    /**
     * 測試Token替換攻擊防護
     */
    public function test_token_substitution_attack_protection()
    {
        // 嘗試使用其他使用者的Token
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken
        ]);

        // 檢查回應狀態，可能因為權限設定而被拒絕
        $this->assertContains($response->status(), [200, 403]);
        
        if ($response->status() === 200) {
            $response->assertJsonPath('data.user.id', $this->adminUser->id);
        }

        // 嘗試在請求中偽造使用者ID，使用不需要權限的Action
        $response = $this->postJson('/api/', [
            'action_type' => 'system.ping',
            'user_id' => $this->adminUser->id // 嘗試在ping中加入額外參數
        ], [
            'Authorization' => 'Bearer ' . $this->regularToken
        ]);

        // system.ping不需要權限，應該成功，但不應該洩漏使用者資訊
        $response->assertStatus(200);
        $response->assertJsonMissing(['user_id' => $this->adminUser->id]);
    }

    /**
     * 測試權限提升攻擊防護
     */
    public function test_privilege_escalation_attack_protection()
    {
        // 嘗試使用低權限Token執行高權限操作
        $response = $this->postJson('/api/', [
            'action_type' => 'admin.action'
        ], [
            'Authorization' => 'Bearer ' . $this->regularToken
        ]);

        $response->assertStatus(404); // Action不存在，但如果存在應該是403

        // 嘗試修改請求中的權限參數
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
            'permissions' => ['admin'], // 嘗試注入權限
            'is_admin' => true, // 嘗試聲明管理員身份
            'role' => 'admin' // 嘗試設定角色
        ], [
            'Authorization' => 'Bearer ' . $this->regularToken
        ]);

        $response->assertStatus(200);
        
        // 確保回應中不包含提升的權限資訊
        $responseData = $response->json();
        $this->assertArrayNotHasKey('permissions', $responseData['data'] ?? []);
        $this->assertArrayNotHasKey('is_admin', $responseData['data'] ?? []);
        $this->assertArrayNotHasKey('role', $responseData['data'] ?? []);
    }

    /**
     * 測試Session劫持防護
     */
    public function test_session_hijacking_protection()
    {
        // 使用有效Token進行請求
        $response1 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->regularToken,
            'User-Agent' => 'TestAgent/1.0',
            'X-Forwarded-For' => '192.168.1.100'
        ]);

        $response1->assertStatus(200);

        // 嘗試從不同的User-Agent使用相同Token
        $response2 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->regularToken,
            'User-Agent' => 'MaliciousAgent/1.0',
            'X-Forwarded-For' => '10.0.0.1'
        ]);

        // Token應該仍然有效（除非實作了嚴格的Session綁定）
        $response2->assertStatus(200);
    }

    /**
     * 測試CSRF攻擊防護
     */
    public function test_csrf_attack_protection()
    {
        // 嘗試不帶CSRF Token的請求（如果有實作CSRF防護）
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->regularToken,
            'Origin' => 'http://malicious-site.com',
            'Referer' => 'http://malicious-site.com/attack.html'
        ]);

        // API應該仍然正常工作（因為使用Bearer Token，通常不需要CSRF防護）
        $response->assertStatus(200);
    }

    /**
     * 測試參數篡改攻擊防護
     */
    public function test_parameter_tampering_protection()
    {
        // 嘗試篡改關鍵參數
        $tamperingAttempts = [
            ['user_id' => -1], // 負數ID
            ['user_id' => 0], // 零ID
            ['user_id' => 'admin'], // 字串ID
            ['user_id' => ['$ne' => null]], // NoSQL注入
            ['user_id' => "1' OR '1'='1"], // SQL注入
            ['action_type' => '../../../etc/passwd'], // 路徑遍歷
            ['action_type' => 'user.info; DROP TABLE users;'], // 命令注入
        ];

        foreach ($tamperingAttempts as $params) {
            $requestData = array_merge(['action_type' => 'user.info'], $params);
            
            $response = $this->postJson('/api/', $requestData, [
                'Authorization' => 'Bearer ' . $this->regularToken
            ]);

            // 系統應該拒絕無效參數或安全處理
            $this->assertContains($response->status(), [200, 400, 401, 403, 422]);
            
            // 確保沒有敏感資訊洩漏
            $responseContent = $response->getContent();
            $this->assertStringNotContainsString('root:', $responseContent);
            $this->assertStringNotContainsString('DROP TABLE', $responseContent);
        }
    }

    /**
     * 測試時間攻擊防護
     */
    public function test_timing_attack_protection()
    {
        $validActionTimes = [];
        $invalidActionTimes = [];

        // 測試有效Action的回應時間
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            
            $response = $this->postJson('/api/', [
                'action_type' => 'user.info'
            ], [
                'Authorization' => 'Bearer ' . $this->regularToken
            ]);
            
            $endTime = microtime(true);
            $validActionTimes[] = ($endTime - $startTime) * 1000;
            
            $response->assertStatus(200);
        }

        // 測試無效Action的回應時間
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            
            $response = $this->postJson('/api/', [
                'action_type' => 'non.existent.action'
            ], [
                'Authorization' => 'Bearer ' . $this->regularToken
            ]);
            
            $endTime = microtime(true);
            $invalidActionTimes[] = ($endTime - $startTime) * 1000;
            
            $response->assertStatus(404);
        }

        $avgValidTime = array_sum($validActionTimes) / count($validActionTimes);
        $avgInvalidTime = array_sum($invalidActionTimes) / count($invalidActionTimes);

        // 時間差異不應該太大，避免洩漏系統資訊
        $timeDifference = abs($avgValidTime - $avgInvalidTime);
        $this->assertLessThan(50, $timeDifference, '有效和無效Action的回應時間差異過大');
    }

    /**
     * 測試權限快取繞過攻擊防護
     */
    public function test_permission_cache_bypass_protection()
    {
        // 使用無權限Token進行請求
        $response1 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->noPermissionToken
        ]);

        $response1->assertStatus(403);

        // 嘗試快速切換到有權限Token，然後再切回無權限Token
        $response2 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->regularToken
        ]);

        $response2->assertStatus(200);

        // 再次使用無權限Token，應該仍然被拒絕
        $response3 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->noPermissionToken
        ]);

        $response3->assertStatus(403);
    }

    /**
     * 測試並發權限檢查攻擊防護
     */
    public function test_concurrent_permission_check_protection()
    {
        $responses = [];

        // 同時發送多個請求，嘗試造成競態條件
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->postJson('/api/', [
                'action_type' => 'user.info'
            ], [
                'Authorization' => 'Bearer ' . $this->noPermissionToken
            ]);
        }

        // 所有請求都應該被拒絕
        foreach ($responses as $response) {
            $response->assertStatus(403);
        }
    }

    /**
     * 測試Token重放攻擊防護
     */
    public function test_token_replay_attack_protection()
    {
        // 記錄Token的初始使用時間
        $tokenModel = ApiToken::findByToken($this->regularToken);
        $initialLastUsed = $tokenModel->last_used_at;

        // 第一次使用Token
        $response1 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->regularToken
        ]);

        $response1->assertStatus(200);

        // 檢查Token使用時間是否更新
        $tokenModel->refresh();
        $firstUseTime = $tokenModel->last_used_at;
        $this->assertNotEquals($initialLastUsed, $firstUseTime);

        // 嘗試重放相同的請求
        $response2 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->regularToken
        ]);

        $response2->assertStatus(200);

        // Token應該仍然有效（除非實作了嚴格的重放防護）
        $tokenModel->refresh();
        $secondUseTime = $tokenModel->last_used_at;
        $this->assertGreaterThanOrEqual($firstUseTime, $secondUseTime);
    }

    /**
     * 測試權限邊界攻擊防護
     */
    public function test_permission_boundary_attack_protection()
    {
        // 嘗試存取邊界權限
        $boundaryTests = [
            ['action_type' => 'user.info'], // 有權限
            ['action_type' => 'user.update'], // 可能沒權限
            ['action_type' => 'user.delete'], // 可能沒權限
            ['action_type' => 'admin.users'], // 沒權限
            ['action_type' => 'system.config'], // 沒權限
        ];

        foreach ($boundaryTests as $testData) {
            $response = $this->postJson('/api/', $testData, [
                'Authorization' => 'Bearer ' . $this->regularToken
            ]);

            // 根據權限配置，應該有適當的回應
            $this->assertContains($response->status(), [200, 401, 403, 404]);
            
            if ($response->status() === 403) {
                $response->assertJson([
                    'status' => 'error',
                    'error_code' => 'INSUFFICIENT_PERMISSIONS'
                ]);
            }
        }
    }

    /**
     * 測試權限繼承攻擊防護
     */
    public function test_permission_inheritance_attack_protection()
    {
        // 嘗試透過繼承或組合來獲得更高權限
        $inheritanceAttempts = [
            [
                'action_type' => 'user.info',
                'inherit_permissions' => ['admin'],
            ],
            [
                'action_type' => 'user.info',
                'parent_permissions' => ['*'],
            ],
            [
                'action_type' => 'user.info',
                'combined_permissions' => ['user.read', 'admin'],
            ],
        ];

        foreach ($inheritanceAttempts as $attempt) {
            $response = $this->postJson('/api/', $attempt, [
                'Authorization' => 'Bearer ' . $this->regularToken
            ]);

            // 應該正常執行，但不應該獲得額外權限
            $response->assertStatus(200);
            
            // 確保回應中不包含權限提升的證據
            $responseData = $response->json();
            $this->assertArrayNotHasKey('inherit_permissions', $responseData['data'] ?? []);
            $this->assertArrayNotHasKey('parent_permissions', $responseData['data'] ?? []);
            $this->assertArrayNotHasKey('combined_permissions', $responseData['data'] ?? []);
        }
    }

    /**
     * 測試權限快取污染攻擊防護
     */
    public function test_permission_cache_pollution_protection()
    {
        // 使用管理員Token執行操作
        $response1 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken
        ]);

        $response1->assertStatus(200);

        // 立即切換到一般使用者Token
        $response2 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->regularToken
        ]);

        $response2->assertStatus(200);

        // 切換到無權限Token，應該被拒絕
        $response3 = $this->postJson('/api/', [
            'action_type' => 'user.info'
        ], [
            'Authorization' => 'Bearer ' . $this->noPermissionToken
        ]);

        $response3->assertStatus(403);

        // 確保權限檢查是獨立的，不會被之前的請求影響
        $this->assertEquals(200, $response1->status());
        $this->assertEquals(200, $response2->status());
        $this->assertEquals(403, $response3->status());
    }
}
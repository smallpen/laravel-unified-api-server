<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Middleware\BearerTokenMiddleware;
use App\Models\User;
use App\Models\ApiToken;
use Carbon\Carbon;

/**
 * Bearer Token 中介軟體測試
 */
class BearerTokenMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private BearerTokenMiddleware $middleware;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->middleware = new BearerTokenMiddleware();
        
        // 建立測試使用者
        $this->user = User::factory()->create([
            'name' => '測試使用者',
            'email' => 'test@example.com',
        ]);
    }

    /**
     * 測試缺少 Bearer Token 時回傳 401 錯誤
     */
    public function test_missing_bearer_token_returns_401(): void
    {
        $request = Request::create('/api/test', 'POST');
        
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('缺少 Bearer Token', $data['message']);
        $this->assertEquals('UNAUTHORIZED', $data['error_code']);
    }

    /**
     * 測試無效的 Authorization 標頭格式
     */
    public function test_invalid_authorization_header_format_returns_401(): void
    {
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Authorization', 'Basic dGVzdDp0ZXN0');
        
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('缺少 Bearer Token', $data['message']);
    }

    /**
     * 測試空的 Bearer Token
     */
    public function test_empty_bearer_token_returns_401(): void
    {
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Authorization', 'Bearer ');
        
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * 測試無效的 Bearer Token
     */
    public function test_invalid_bearer_token_returns_401(): void
    {
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Authorization', 'Bearer invalid_token_12345');
        
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('無效或過期的 Bearer Token', $data['message']);
    }

    /**
     * 測試有效的 Bearer Token 通過驗證
     */
    public function test_valid_bearer_token_passes_authentication(): void
    {
        // 建立有效的 API Token
        $tokenData = ApiToken::createToken(
            $this->user->id,
            '測試 Token',
            ['read', 'write']
        );
        
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $tokenData['token']);
        
        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            
            // 驗證使用者已正確設定到請求中
            $this->assertInstanceOf(User::class, $req->user());
            $this->assertEquals($this->user->id, $req->user()->id);
            
            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        
        // 驗證 Token 的最後使用時間已更新
        $apiToken = ApiToken::findByToken($tokenData['token']);
        $this->assertNotNull($apiToken->last_used_at);
    }

    /**
     * 測試過期的 Bearer Token
     */
    public function test_expired_bearer_token_returns_401(): void
    {
        // 建立已過期的 API Token
        $tokenData = ApiToken::createToken(
            $this->user->id,
            '過期 Token',
            ['read'],
            Carbon::now()->subHour() // 一小時前過期
        );
        
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $tokenData['token']);
        
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('無效或過期的 Bearer Token', $data['message']);
    }

    /**
     * 測試已撤銷的 Bearer Token
     */
    public function test_revoked_bearer_token_returns_401(): void
    {
        // 建立 API Token 然後撤銷它
        $tokenData = ApiToken::createToken(
            $this->user->id,
            '撤銷 Token',
            ['read']
        );
        
        $apiToken = $tokenData['model'];
        $apiToken->revoke();
        
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $tokenData['token']);
        
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('無效或過期的 Bearer Token', $data['message']);
    }

    /**
     * 測試 Token 最後使用時間的更新
     */
    public function test_token_last_used_time_is_updated(): void
    {
        // 建立 API Token
        $tokenData = ApiToken::createToken(
            $this->user->id,
            '測試 Token',
            ['read']
        );
        
        $apiToken = $tokenData['model'];
        $originalLastUsed = $apiToken->last_used_at;
        
        // 等待一秒確保時間差異
        sleep(1);
        
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $tokenData['token']);
        
        $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });
        
        // 重新載入模型並檢查最後使用時間
        $apiToken->refresh();
        $this->assertNotNull($apiToken->last_used_at);
        
        if ($originalLastUsed) {
            $this->assertTrue($apiToken->last_used_at->isAfter($originalLastUsed));
        } else {
            // 如果原本沒有最後使用時間，現在應該有了
            $this->assertNotNull($apiToken->last_used_at);
        }
    }

    /**
     * 測試使用者解析器的設定
     */
    public function test_user_resolver_is_set_correctly(): void
    {
        // 建立 API Token
        $tokenData = ApiToken::createToken(
            $this->user->id,
            '測試 Token',
            ['read']
        );
        
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $tokenData['token']);
        
        $this->middleware->handle($request, function ($req) {
            // 驗證使用者解析器已正確設定
            $resolvedUser = $req->user();
            $this->assertInstanceOf(User::class, $resolvedUser);
            $this->assertEquals($this->user->id, $resolvedUser->id);
            $this->assertEquals($this->user->email, $resolvedUser->email);
            
            return response()->json(['success' => true]);
        });
    }

    /**
     * 測試回應格式的正確性
     */
    public function test_unauthorized_response_format(): void
    {
        $request = Request::create('/api/test', 'POST');
        
        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        
        // 驗證回應格式包含所有必要欄位
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('error_code', $data);
        $this->assertArrayHasKey('timestamp', $data);
        
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('UNAUTHORIZED', $data['error_code']);
        $this->assertNotEmpty($data['timestamp']);
    }
}
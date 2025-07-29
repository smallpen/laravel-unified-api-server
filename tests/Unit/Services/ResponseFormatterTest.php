<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ResponseFormatter;
use App\Contracts\ResponseFormatterInterface;
use Illuminate\Support\Str;

/**
 * ResponseFormatter單元測試
 * 
 * 測試回應格式化器的各種功能
 * 確保所有回應格式都符合API規範
 */
class ResponseFormatterTest extends TestCase
{
    /**
     * ResponseFormatter實例
     * 
     * @var ResponseFormatter
     */
    protected ResponseFormatter $formatter;

    /**
     * 設定測試環境
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new ResponseFormatter();
    }

    /**
     * 測試ResponseFormatter實作了正確的介面
     * 
     * @return void
     */
    public function test_implements_response_formatter_interface(): void
    {
        $this->assertInstanceOf(ResponseFormatterInterface::class, $this->formatter);
    }

    /**
     * 測試成功回應格式
     * 
     * @return void
     */
    public function test_success_response_format(): void
    {
        $data = ['user_id' => 1, 'name' => '測試使用者'];
        $message = '操作成功';
        $meta = ['version' => '1.0'];

        $response = $this->formatter->success($data, $message, $meta);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals($message, $response['message']);
        $this->assertEquals($data, $response['data']);
        $this->assertEquals($meta, $response['meta']);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('request_id', $response);
        $this->assertTrue(Str::isUuid($response['request_id']));
    }

    /**
     * 測試成功回應的預設值
     * 
     * @return void
     */
    public function test_success_response_defaults(): void
    {
        $response = $this->formatter->success();

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('操作成功', $response['message']);
        $this->assertEquals([], $response['data']);
        $this->assertArrayNotHasKey('meta', $response);
    }

    /**
     * 測試錯誤回應格式
     * 
     * @return void
     */
    public function test_error_response_format(): void
    {
        $message = '發生錯誤';
        $errorCode = 'TEST_ERROR';
        $details = ['field' => '欄位錯誤'];
        $meta = ['debug' => true];

        $response = $this->formatter->error($message, $errorCode, $details, $meta);

        $this->assertEquals('error', $response['status']);
        $this->assertEquals($message, $response['message']);
        $this->assertEquals($errorCode, $response['error_code']);
        $this->assertEquals($details, $response['details']);
        $this->assertEquals($meta, $response['meta']);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('request_id', $response);
    }

    /**
     * 測試錯誤回應的預設值
     * 
     * @return void
     */
    public function test_error_response_defaults(): void
    {
        $message = '發生錯誤';
        $errorCode = 'TEST_ERROR';

        $response = $this->formatter->error($message, $errorCode);

        $this->assertEquals('error', $response['status']);
        $this->assertEquals($message, $response['message']);
        $this->assertEquals($errorCode, $response['error_code']);
        $this->assertArrayNotHasKey('details', $response);
        $this->assertArrayNotHasKey('meta', $response);
    }

    /**
     * 測試分頁回應格式
     * 
     * @return void
     */
    public function test_paginated_response_format(): void
    {
        $data = [
            ['id' => 1, 'name' => '項目1'],
            ['id' => 2, 'name' => '項目2'],
        ];
        $pagination = [
            'current_page' => 1,
            'per_page' => 10,
            'total' => 25,
            'last_page' => 3,
            'from' => 1,
            'to' => 10,
        ];
        $message = '資料取得成功';
        $meta = ['cache' => true];

        $response = $this->formatter->paginated($data, $pagination, $message, $meta);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals($message, $response['message']);
        $this->assertEquals($data, $response['data']);
        $this->assertEquals($meta, $response['meta']);
        
        // 檢查分頁資訊
        $this->assertArrayHasKey('pagination', $response);
        $paginationData = $response['pagination'];
        $this->assertEquals(1, $paginationData['current_page']);
        $this->assertEquals(10, $paginationData['per_page']);
        $this->assertEquals(25, $paginationData['total']);
        $this->assertEquals(3, $paginationData['last_page']);
        $this->assertEquals(1, $paginationData['from']);
        $this->assertEquals(10, $paginationData['to']);
        $this->assertTrue($paginationData['has_more_pages']);
    }

    /**
     * 測試分頁回應的預設值
     * 
     * @return void
     */
    public function test_paginated_response_defaults(): void
    {
        $data = [];
        $pagination = [
            'current_page' => 1,
            'per_page' => 10,
            'total' => 0,
            'last_page' => 1,
        ];

        $response = $this->formatter->paginated($data, $pagination);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('資料取得成功', $response['message']);
        $this->assertEquals($data, $response['data']);
        $this->assertArrayNotHasKey('meta', $response);
        $this->assertFalse($response['pagination']['has_more_pages']);
    }

    /**
     * 測試分頁回應缺少必要欄位時拋出例外
     * 
     * @return void
     */
    public function test_paginated_response_throws_exception_for_missing_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('分頁資訊缺少必要欄位: per_page');

        $data = [];
        $pagination = [
            'current_page' => 1,
            'total' => 0,
            'last_page' => 1,
        ];

        $this->formatter->paginated($data, $pagination);
    }

    /**
     * 測試驗證錯誤回應格式
     * 
     * @return void
     */
    public function test_validation_error_response_format(): void
    {
        $errors = [
            'email' => ['電子郵件格式不正確'],
            'password' => ['密碼長度至少8個字元'],
        ];
        $message = '驗證失敗';

        $response = $this->formatter->validationError($errors, $message);

        $this->assertEquals('error', $response['status']);
        $this->assertEquals($message, $response['message']);
        $this->assertEquals('VALIDATION_ERROR', $response['error_code']);
        $this->assertEquals($errors, $response['details']);
    }

    /**
     * 測試驗證錯誤回應的預設值
     * 
     * @return void
     */
    public function test_validation_error_response_defaults(): void
    {
        $errors = ['field' => ['錯誤訊息']];

        $response = $this->formatter->validationError($errors);

        $this->assertEquals('請求參數驗證失敗', $response['message']);
        $this->assertEquals('VALIDATION_ERROR', $response['error_code']);
    }

    /**
     * 測試設定和取得請求ID
     * 
     * @return void
     */
    public function test_set_and_get_request_id(): void
    {
        $requestId = 'test-request-id-123';
        
        $result = $this->formatter->setRequestId($requestId);
        
        $this->assertSame($this->formatter, $result); // 測試流暢介面
        $this->assertEquals($requestId, $this->formatter->getRequestId());
        
        // 測試在回應中是否正確使用
        $response = $this->formatter->success();
        $this->assertEquals($requestId, $response['request_id']);
    }

    /**
     * 測試設定和取得時間戳記
     * 
     * @return void
     */
    public function test_set_and_get_timestamp(): void
    {
        $timestamp = '2024-01-01T12:00:00Z';
        
        $result = $this->formatter->setTimestamp($timestamp);
        
        $this->assertSame($this->formatter, $result); // 測試流暢介面
        $this->assertEquals($timestamp, $this->formatter->getTimestamp());
        
        // 測試在回應中是否正確使用
        $response = $this->formatter->success();
        $this->assertEquals($timestamp, $response['timestamp']);
    }

    /**
     * 測試靜態make方法
     * 
     * @return void
     */
    public function test_static_make_method(): void
    {
        $formatter = ResponseFormatter::make();
        
        $this->assertInstanceOf(ResponseFormatter::class, $formatter);
        $this->assertNotSame($this->formatter, $formatter); // 應該是新實例
    }

    /**
     * 測試靜態makeSuccess方法
     * 
     * @return void
     */
    public function test_static_make_success_method(): void
    {
        $data = ['test' => 'data'];
        $message = '測試成功';
        $meta = ['version' => '1.0'];

        $response = ResponseFormatter::makeSuccess($data, $message, $meta);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals($message, $response['message']);
        $this->assertEquals($data, $response['data']);
        $this->assertEquals($meta, $response['meta']);
    }

    /**
     * 測試靜態makeError方法
     * 
     * @return void
     */
    public function test_static_make_error_method(): void
    {
        $message = '測試錯誤';
        $errorCode = 'TEST_ERROR';
        $details = ['field' => '錯誤'];
        $meta = ['debug' => true];

        $response = ResponseFormatter::makeError($message, $errorCode, $details, $meta);

        $this->assertEquals('error', $response['status']);
        $this->assertEquals($message, $response['message']);
        $this->assertEquals($errorCode, $response['error_code']);
        $this->assertEquals($details, $response['details']);
        $this->assertEquals($meta, $response['meta']);
    }

    /**
     * 測試靜態makePaginated方法
     * 
     * @return void
     */
    public function test_static_make_paginated_method(): void
    {
        $data = [['id' => 1]];
        $pagination = [
            'current_page' => 1,
            'per_page' => 10,
            'total' => 1,
            'last_page' => 1,
        ];
        $message = '測試分頁';
        $meta = ['cache' => true];

        $response = ResponseFormatter::makePaginated($data, $pagination, $message, $meta);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals($message, $response['message']);
        $this->assertEquals($data, $response['data']);
        $this->assertEquals($meta, $response['meta']);
        $this->assertArrayHasKey('pagination', $response);
    }

    /**
     * 測試靜態makeValidationError方法
     * 
     * @return void
     */
    public function test_static_make_validation_error_method(): void
    {
        $errors = ['field' => ['錯誤訊息']];
        $message = '測試驗證錯誤';

        $response = ResponseFormatter::makeValidationError($errors, $message);

        $this->assertEquals('error', $response['status']);
        $this->assertEquals($message, $response['message']);
        $this->assertEquals('VALIDATION_ERROR', $response['error_code']);
        $this->assertEquals($errors, $response['details']);
    }

    /**
     * 測試回應格式的一致性
     * 
     * @return void
     */
    public function test_response_format_consistency(): void
    {
        $successResponse = $this->formatter->success();
        $errorResponse = $this->formatter->error('錯誤', 'ERROR');
        $paginatedResponse = $this->formatter->paginated([], [
            'current_page' => 1,
            'per_page' => 10,
            'total' => 0,
            'last_page' => 1,
        ]);

        // 所有回應都應該有這些基本欄位
        $requiredFields = ['status', 'message', 'timestamp', 'request_id'];
        
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $successResponse);
            $this->assertArrayHasKey($field, $errorResponse);
            $this->assertArrayHasKey($field, $paginatedResponse);
        }

        // 檢查request_id在同一個實例中是否一致
        $this->assertEquals($successResponse['request_id'], $errorResponse['request_id']);
        $this->assertEquals($successResponse['request_id'], $paginatedResponse['request_id']);
    }

    /**
     * 測試大量資料回應處理
     * 
     * @return void
     */
    public function test_large_data_response(): void
    {
        // 建立小量資料（不會觸發壓縮建議）
        $smallData = ['item' => 'small'];
        $response = $this->formatter->largeDataResponse($smallData);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('資料取得成功', $response['message']);
        $this->assertEquals($smallData, $response['data']);
        $this->assertArrayNotHasKey('compression_recommended', $response['meta'] ?? []);

        // 建立大量資料（會觸發壓縮建議）
        $largeData = array_fill(0, 1000, str_repeat('x', 1000)); // 約1MB資料
        $response = $this->formatter->largeDataResponse($largeData, '大量資料', [], 500000); // 設定較小的限制

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('大量資料', $response['message']);
        $this->assertEquals($largeData, $response['data']);
        $this->assertArrayHasKey('meta', $response);
        $this->assertTrue($response['meta']['compression_recommended']);
        $this->assertArrayHasKey('data_size', $response['meta']);
        $this->assertArrayHasKey('suggestion', $response['meta']);
    }

    /**
     * 測試靜態makeLargeDataResponse方法
     * 
     * @return void
     */
    public function test_static_make_large_data_response_method(): void
    {
        $data = ['test' => 'data'];
        $message = '測試大量資料';
        $meta = ['version' => '1.0'];

        $response = ResponseFormatter::makeLargeDataResponse($data, $message, $meta);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals($message, $response['message']);
        $this->assertEquals($data, $response['data']);
        $this->assertEquals($meta, $response['meta']);
    }
}
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UnifiedApiController;
use App\Http\Controllers\Api\DocumentationController;
use App\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 這裡是註冊應用程式API路由的地方。這些路由會被RouteServiceProvider載入
| 並且會被指派到"api"中介軟體群組。
|
*/

// 健康檢查路由 - 不需要Bearer Token驗證
Route::prefix('health')->name('api.health.')->group(function () {
    // 基本健康檢查
    Route::get('/', [HealthController::class, 'basic'])
        ->name('basic');
    
    // 詳細健康檢查
    Route::get('/detailed', [HealthController::class, 'detailed'])
        ->name('detailed');
});

// API文件相關路由 - 不需要Bearer Token驗證
Route::prefix('docs')->name('api.docs.')->group(function () {
    // Swagger UI介面
    Route::get('/', [DocumentationController::class, 'swaggerUi'])
        ->name('swagger');
    
    // 取得完整API文件（JSON格式）
    Route::get('/json', [DocumentationController::class, 'getDocumentation'])
        ->name('json');
    
    // 取得OpenAPI規格文件
    Route::get('/openapi.json', [DocumentationController::class, 'getOpenApiSpec'])
        ->name('openapi');
    
    // 取得Action摘要列表
    Route::get('/actions', [DocumentationController::class, 'getActionsSummary'])
        ->name('actions');
    
    // 取得指定Action的詳細文件
    Route::get('/actions/{actionType}', [DocumentationController::class, 'getActionDocumentation'])
        ->name('action');
    
    // 重新生成API文件
    Route::post('/regenerate', [DocumentationController::class, 'regenerateDocumentation'])
        ->name('regenerate');
    
    // 取得文件生成統計資訊
    Route::get('/statistics', [DocumentationController::class, 'getStatistics'])
        ->name('statistics');
    
    // 驗證Action文件完整性
    Route::get('/validate/{actionType}', [DocumentationController::class, 'validateActionDocumentation'])
        ->name('validate');
    
    // 檢查文件更新狀態
    Route::get('/status', [DocumentationController::class, 'getDocumentationStatus'])
        ->name('status');
    
    // 取得Action變更歷史
    Route::get('/changes', [DocumentationController::class, 'getActionChanges'])
        ->name('changes');
});

// 統一API接口路徑 - 所有API請求都透過此路徑進入
Route::any('/', [UnifiedApiController::class, 'handle'])
    ->middleware('bearer.token')
    ->name('api.unified');

// 保留原有的測試路由
Route::get('/user', function (Request $request) {
    return ['message' => 'API 運作正常'];
});

// 簡單的測試路由來驗證日誌中介軟體
Route::post('/test-logging', function (Request $request) {
    return response()->json([
        'status' => 'success',
        'message' => '日誌測試成功',
        'data' => $request->all(),
    ]);
})->name('api.test.logging');
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| 這裡是註冊應用程式web路由的地方。這些路由會被RouteServiceProvider載入
| 並且會被指派到"web"中介軟體群組。
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| 健康檢查路由
|--------------------------------------------------------------------------
|
| 這些路由用於系統健康檢查和監控
|
*/

// 基本健康檢查
Route::get('/health', [HealthController::class, 'basic']);

// 詳細健康檢查
Route::get('/health/detailed', [HealthController::class, 'detailed']);

// API端點健康檢查
Route::get('/health/api', [HealthController::class, 'api']);

// 資料庫健康檢查
Route::get('/health/database', [HealthController::class, 'database']);
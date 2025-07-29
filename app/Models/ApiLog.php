<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * API 請求日誌模型
 * 
 * @property int $id
 * @property int|null $user_id 使用者ID
 * @property string $action_type 動作類型
 * @property array|null $request_data 請求資料
 * @property array|null $response_data 回應資料
 * @property float $response_time 回應時間（毫秒）
 * @property string $ip_address IP位址
 * @property string|null $user_agent 使用者代理
 * @property int $status_code HTTP狀態碼
 * @property string $request_id 請求ID
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ApiLog extends Model
{
    use HasFactory;

    /**
     * 資料表名稱
     */
    protected $table = 'api_logs';

    /**
     * 可批量賦值的屬性
     */
    protected $fillable = [
        'user_id',
        'action_type',
        'request_data',
        'response_data',
        'response_time',
        'ip_address',
        'user_agent',
        'status_code',
        'request_id',
    ];

    /**
     * 屬性轉換
     */
    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'response_time' => 'float',
        'status_code' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 與使用者的關聯
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 根據動作類型查詢
     */
    public function scopeByActionType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * 根據使用者查詢
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 根據狀態碼查詢
     */
    public function scopeByStatusCode($query, int $statusCode)
    {
        return $query->where('status_code', $statusCode);
    }

    /**
     * 查詢錯誤日誌（狀態碼 >= 400）
     */
    public function scopeErrors($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    /**
     * 查詢成功日誌（狀態碼 < 400）
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status_code', '<', 400);
    }

    /**
     * 根據日期範圍查詢
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * 計算平均回應時間
     */
    public function scopeAverageResponseTime($query)
    {
        return $query->avg('response_time');
    }
}

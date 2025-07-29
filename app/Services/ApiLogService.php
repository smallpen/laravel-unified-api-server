<?php

namespace App\Services;

use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * API 日誌服務
 * 
 * 提供 API 日誌的進階查詢和統計功能
 */
class ApiLogService
{
    /**
     * 取得 API 使用統計
     */
    public function getApiUsageStats(array $filters = []): array
    {
        $baseQuery = ApiLog::query();
        $this->applyFilters($baseQuery, $filters);
        
        // 建立獨立的查詢副本以避免條件累積
        $totalQuery = clone $baseQuery;
        $successQuery = clone $baseQuery;
        $failedQuery = clone $baseQuery;
        $avgQuery = clone $baseQuery;
        $uniqueUsersQuery = clone $baseQuery;
        
        return [
            'total_requests' => $totalQuery->count(),
            'successful_requests' => $successQuery->where('status_code', '<', 400)->count(),
            'failed_requests' => $failedQuery->where('status_code', '>=', 400)->count(),
            'average_response_time' => round($avgQuery->avg('response_time'), 2),
            'unique_users' => $uniqueUsersQuery->whereNotNull('user_id')->distinct('user_id')->count(),
            'top_actions' => $this->getTopActions($filters),
            'error_distribution' => $this->getErrorDistribution($filters),
        ];
    }

    /**
     * 取得最常使用的動作
     */
    public function getTopActions(array $filters = [], int $limit = 10): array
    {
        $query = ApiLog::select('action_type', DB::raw('COUNT(*) as count'))
            ->groupBy('action_type')
            ->orderBy('count', 'desc')
            ->limit($limit);
            
        $this->applyFilters($query, $filters);
        
        return $query->get()->toArray();
    }

    /**
     * 取得錯誤分佈
     */
    public function getErrorDistribution(array $filters = []): array
    {
        $query = ApiLog::select('status_code', DB::raw('COUNT(*) as count'))
            ->where('status_code', '>=', 400)
            ->groupBy('status_code')
            ->orderBy('count', 'desc');
            
        $this->applyFilters($query, $filters);
        
        return $query->get()->toArray();
    }

    /**
     * 取得使用者活動日誌
     */
    public function getUserActivityLogs(int $userId, array $filters = []): array
    {
        $query = ApiLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc');
            
        $this->applyFilters($query, $filters);
        
        return $query->paginate($filters['per_page'] ?? 50)->toArray();
    }

    /**
     * 取得效能分析資料
     */
    public function getPerformanceAnalysis(array $filters = []): array
    {
        $query = ApiLog::query();
        $this->applyFilters($query, $filters);
        
        return [
            'response_time_percentiles' => [
                'p50' => $this->getPercentile($query, 'response_time', 50),
                'p90' => $this->getPercentile($query, 'response_time', 90),
                'p95' => $this->getPercentile($query, 'response_time', 95),
                'p99' => $this->getPercentile($query, 'response_time', 99),
            ],
            'slowest_actions' => $this->getSlowestActions($filters),
            'hourly_distribution' => $this->getHourlyDistribution($filters),
        ];
    }

    /**
     * 取得最慢的動作
     */
    public function getSlowestActions(array $filters = [], int $limit = 10): array
    {
        $query = ApiLog::select('action_type', DB::raw('AVG(response_time) as avg_response_time'))
            ->groupBy('action_type')
            ->orderBy('avg_response_time', 'desc')
            ->limit($limit);
            
        $this->applyFilters($query, $filters);
        
        return $query->get()->map(function ($item) {
            $item->avg_response_time = round($item->avg_response_time, 2);
            return $item;
        })->toArray();
    }

    /**
     * 取得每小時請求分佈
     */
    public function getHourlyDistribution(array $filters = []): array
    {
        $query = ApiLog::select(
            DB::raw('HOUR(created_at) as hour'),
            DB::raw('COUNT(*) as count')
        )
        ->groupBy(DB::raw('HOUR(created_at)'))
        ->orderBy('hour');
        
        $this->applyFilters($query, $filters);
        
        return $query->get()->toArray();
    }

    /**
     * 清理舊日誌
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);
        
        return ApiLog::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * 匯出日誌資料
     */
    public function exportLogs(array $filters = [], string $format = 'csv'): string
    {
        $query = ApiLog::with('user:id,name,email')
            ->orderBy('created_at', 'desc');
            
        $this->applyFilters($query, $filters);
        
        $logs = $query->get();
        
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($logs);
            case 'json':
                return $logs->toJson();
            default:
                throw new \InvalidArgumentException("不支援的匯出格式: {$format}");
        }
    }

    /**
     * 應用篩選條件
     */
    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (!empty($filters['action_type'])) {
            $query->where('action_type', $filters['action_type']);
        }
        
        if (!empty($filters['status_code'])) {
            $query->where('status_code', $filters['status_code']);
        }
        
        if (!empty($filters['ip_address'])) {
            $query->where('ip_address', $filters['ip_address']);
        }
        
        if (isset($filters['min_response_time'])) {
            $query->where('response_time', '>=', $filters['min_response_time']);
        }
        
        if (isset($filters['max_response_time'])) {
            $query->where('response_time', '<=', $filters['max_response_time']);
        }
    }

    /**
     * 計算百分位數
     */
    private function getPercentile($query, string $column, int $percentile): float
    {
        $count = $query->count();
        if ($count === 0) {
            return 0;
        }
        
        $position = ceil($count * $percentile / 100) - 1;
        
        $value = $query->orderBy($column)->skip($position)->first();
        
        return $value ? round($value->{$column}, 2) : 0;
    }

    /**
     * 匯出為 CSV 格式
     */
    private function exportToCsv($logs): string
    {
        $csv = "ID,使用者ID,使用者名稱,動作類型,狀態碼,回應時間,IP位址,建立時間\n";
        
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%d,%.3f,%s,%s\n",
                $log->id,
                $log->user_id ?? '',
                $log->user->name ?? '',
                $log->action_type,
                $log->status_code,
                $log->response_time,
                $log->ip_address,
                $log->created_at->format('Y-m-d H:i:s')
            );
        }
        
        return $csv;
    }
}
<?php

/**
 * API負載測試腳本
 * 
 * 用於測試API在高併發情況下的效能表現
 * 
 * 使用方法：
 * php scripts/load-test.php --url=http://localhost/api/ --token=your_token --concurrent=10 --requests=100
 */

require_once __DIR__ . '/../vendor/autoload.php';

class ApiLoadTester
{
    /**
     * API端點URL
     * 
     * @var string
     */
    private string $apiUrl;

    /**
     * Bearer Token
     * 
     * @var string
     */
    private string $token;

    /**
     * 併發使用者數量
     * 
     * @var int
     */
    private int $concurrentUsers;

    /**
     * 每個使用者的請求數量
     * 
     * @var int
     */
    private int $requestsPerUser;

    /**
     * 測試結果
     * 
     * @var array
     */
    private array $results = [];

    /**
     * 建構函式
     * 
     * @param string $apiUrl API端點URL
     * @param string $token Bearer Token
     * @param int $concurrentUsers 併發使用者數量
     * @param int $requestsPerUser 每個使用者的請求數量
     */
    public function __construct(string $apiUrl, string $token, int $concurrentUsers = 10, int $requestsPerUser = 100)
    {
        $this->apiUrl = $apiUrl;
        $this->token = $token;
        $this->concurrentUsers = $concurrentUsers;
        $this->requestsPerUser = $requestsPerUser;
    }

    /**
     * 執行負載測試
     */
    public function run(): void
    {
        echo "開始API負載測試...\n";
        echo "API端點: {$this->apiUrl}\n";
        echo "併發使用者: {$this->concurrentUsers}\n";
        echo "每使用者請求數: {$this->requestsPerUser}\n";
        echo "總請求數: " . ($this->concurrentUsers * $this->requestsPerUser) . "\n";
        echo str_repeat("-", 50) . "\n";

        $startTime = microtime(true);

        // 建立多個子程序來模擬併發使用者
        $processes = [];
        for ($i = 0; $i < $this->concurrentUsers; $i++) {
            $processes[] = $this->createUserProcess($i);
        }

        // 等待所有程序完成
        $this->waitForProcesses($processes);

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // 收集和分析結果
        $this->collectResults();
        $this->analyzeResults($totalTime);
    }

    /**
     * 建立使用者程序
     * 
     * @param int $userId 使用者ID
     * @return resource 程序資源
     */
    private function createUserProcess(int $userId)
    {
        $command = sprintf(
            'php -r "
                require_once \'%s/../vendor/autoload.php\';
                \$tester = new %s(\'%s\', \'%s\', 1, %d);
                \$tester->runSingleUser(%d);
            "',
            __DIR__,
            self::class,
            $this->apiUrl,
            $this->token,
            $this->requestsPerUser,
            $userId
        );

        return proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $pipes);
    }

    /**
     * 等待所有程序完成
     * 
     * @param array $processes 程序陣列
     */
    private function waitForProcesses(array $processes): void
    {
        foreach ($processes as $process) {
            proc_close($process);
        }
    }

    /**
     * 執行單一使用者的測試
     * 
     * @param int $userId 使用者ID
     */
    public function runSingleUser(int $userId): void
    {
        $userResults = [];
        $resultFile = sys_get_temp_dir() . "/load_test_user_{$userId}.json";

        for ($i = 0; $i < $this->requestsPerUser; $i++) {
            $result = $this->makeApiRequest();
            $userResults[] = $result;

            // 每10個請求輸出一次進度
            if (($i + 1) % 10 === 0) {
                echo "使用者 {$userId}: 完成 " . ($i + 1) . "/{$this->requestsPerUser} 請求\n";
            }
        }

        // 將結果寫入暫存檔案
        file_put_contents($resultFile, json_encode($userResults));
    }

    /**
     * 發送API請求
     * 
     * @return array 請求結果
     */
    private function makeApiRequest(): array
    {
        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['action_type' => 'user.info']),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // 轉換為毫秒

        return [
            'timestamp' => $startTime,
            'response_time' => $responseTime,
            'http_code' => $httpCode,
            'success' => $httpCode === 200,
            'error' => $error,
            'response_size' => strlen($response),
        ];
    }

    /**
     * 收集所有使用者的結果
     */
    private function collectResults(): void
    {
        $this->results = [];

        for ($i = 0; $i < $this->concurrentUsers; $i++) {
            $resultFile = sys_get_temp_dir() . "/load_test_user_{$i}.json";
            
            if (file_exists($resultFile)) {
                $userResults = json_decode(file_get_contents($resultFile), true);
                $this->results = array_merge($this->results, $userResults);
                unlink($resultFile); // 清理暫存檔案
            }
        }
    }

    /**
     * 分析測試結果
     * 
     * @param float $totalTime 總測試時間
     */
    private function analyzeResults(float $totalTime): void
    {
        if (empty($this->results)) {
            echo "沒有收集到測試結果\n";
            return;
        }

        $totalRequests = count($this->results);
        $successfulRequests = array_filter($this->results, fn($r) => $r['success']);
        $failedRequests = $totalRequests - count($successfulRequests);

        $responseTimes = array_column($this->results, 'response_time');
        $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
        $minResponseTime = min($responseTimes);
        $maxResponseTime = max($responseTimes);

        // 計算百分位數
        sort($responseTimes);
        $p50 = $this->getPercentile($responseTimes, 50);
        $p90 = $this->getPercentile($responseTimes, 90);
        $p95 = $this->getPercentile($responseTimes, 95);
        $p99 = $this->getPercentile($responseTimes, 99);

        $requestsPerSecond = $totalRequests / $totalTime;
        $successRate = (count($successfulRequests) / $totalRequests) * 100;

        // 輸出結果
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "負載測試結果\n";
        echo str_repeat("=", 50) . "\n";
        
        echo "總體統計:\n";
        echo "  總請求數: {$totalRequests}\n";
        echo "  成功請求: " . count($successfulRequests) . "\n";
        echo "  失敗請求: {$failedRequests}\n";
        echo "  成功率: " . number_format($successRate, 2) . "%\n";
        echo "  總測試時間: " . number_format($totalTime, 2) . " 秒\n";
        echo "  每秒請求數: " . number_format($requestsPerSecond, 2) . " RPS\n";
        
        echo "\n回應時間統計 (毫秒):\n";
        echo "  平均: " . number_format($avgResponseTime, 2) . " ms\n";
        echo "  最小: " . number_format($minResponseTime, 2) . " ms\n";
        echo "  最大: " . number_format($maxResponseTime, 2) . " ms\n";
        echo "  50%: " . number_format($p50, 2) . " ms\n";
        echo "  90%: " . number_format($p90, 2) . " ms\n";
        echo "  95%: " . number_format($p95, 2) . " ms\n";
        echo "  99%: " . number_format($p99, 2) . " ms\n";

        // 效能評估
        echo "\n效能評估:\n";
        if ($avgResponseTime < 100) {
            echo "  ✓ 平均回應時間優秀 (< 100ms)\n";
        } elseif ($avgResponseTime < 500) {
            echo "  ⚠ 平均回應時間良好 (< 500ms)\n";
        } else {
            echo "  ✗ 平均回應時間需要改善 (> 500ms)\n";
        }

        if ($p95 < 1000) {
            echo "  ✓ 95%請求回應時間優秀 (< 1000ms)\n";
        } else {
            echo "  ✗ 95%請求回應時間需要改善 (> 1000ms)\n";
        }

        if ($successRate >= 99.9) {
            echo "  ✓ 成功率優秀 (>= 99.9%)\n";
        } elseif ($successRate >= 99) {
            echo "  ⚠ 成功率良好 (>= 99%)\n";
        } else {
            echo "  ✗ 成功率需要改善 (< 99%)\n";
        }

        if ($requestsPerSecond >= 100) {
            echo "  ✓ 吞吐量優秀 (>= 100 RPS)\n";
        } elseif ($requestsPerSecond >= 50) {
            echo "  ⚠ 吞吐量良好 (>= 50 RPS)\n";
        } else {
            echo "  ✗ 吞吐量需要改善 (< 50 RPS)\n";
        }

        // 錯誤分析
        if ($failedRequests > 0) {
            echo "\n錯誤分析:\n";
            $errorCounts = [];
            foreach ($this->results as $result) {
                if (!$result['success']) {
                    $key = "HTTP {$result['http_code']}";
                    if (!empty($result['error'])) {
                        $key .= " - {$result['error']}";
                    }
                    $errorCounts[$key] = ($errorCounts[$key] ?? 0) + 1;
                }
            }

            foreach ($errorCounts as $error => $count) {
                echo "  {$error}: {$count} 次\n";
            }
        }

        echo "\n" . str_repeat("=", 50) . "\n";
    }

    /**
     * 計算百分位數
     * 
     * @param array $values 數值陣列（已排序）
     * @param int $percentile 百分位數
     * @return float 百分位數值
     */
    private function getPercentile(array $values, int $percentile): float
    {
        $count = count($values);
        $index = ($percentile / 100) * ($count - 1);
        
        if (floor($index) === $index) {
            return $values[$index];
        }
        
        $lower = $values[floor($index)];
        $upper = $values[ceil($index)];
        $fraction = $index - floor($index);
        
        return $lower + ($fraction * ($upper - $lower));
    }
}

// 命令列參數處理
function parseArguments(): array
{
    $options = getopt('', [
        'url:',
        'token:',
        'concurrent:',
        'requests:',
        'help'
    ]);

    if (isset($options['help'])) {
        echo "API負載測試腳本\n\n";
        echo "使用方法:\n";
        echo "  php scripts/load-test.php --url=<API_URL> --token=<BEARER_TOKEN> [選項]\n\n";
        echo "必要參數:\n";
        echo "  --url=<URL>        API端點URL\n";
        echo "  --token=<TOKEN>    Bearer Token\n\n";
        echo "可選參數:\n";
        echo "  --concurrent=<N>   併發使用者數量 (預設: 10)\n";
        echo "  --requests=<N>     每使用者請求數量 (預設: 100)\n";
        echo "  --help             顯示此說明\n\n";
        echo "範例:\n";
        echo "  php scripts/load-test.php --url=http://localhost/api/ --token=your_token_here --concurrent=20 --requests=50\n";
        exit(0);
    }

    if (!isset($options['url']) || !isset($options['token'])) {
        echo "錯誤: 必須提供 --url 和 --token 參數\n";
        echo "使用 --help 查看詳細說明\n";
        exit(1);
    }

    return [
        'url' => $options['url'],
        'token' => $options['token'],
        'concurrent' => (int)($options['concurrent'] ?? 10),
        'requests' => (int)($options['requests'] ?? 100),
    ];
}

// 主程式
if (php_sapi_name() === 'cli') {
    $args = parseArguments();
    
    $tester = new ApiLoadTester(
        $args['url'],
        $args['token'],
        $args['concurrent'],
        $args['requests']
    );
    
    $tester->run();
}
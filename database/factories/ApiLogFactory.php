<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiLog>
 */
class ApiLogFactory extends Factory
{
    /**
     * 定義模型的預設狀態
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actionTypes = [
            'get_user_info',
            'update_profile',
            'create_post',
            'delete_post',
            'upload_file',
            'send_message',
            'get_notifications',
            'mark_as_read',
        ];

        $statusCodes = [200, 201, 400, 401, 403, 404, 422, 500];
        $statusCode = $this->faker->randomElement($statusCodes);

        return [
            'user_id' => $this->faker->boolean(80) ? User::factory() : null,
            'action_type' => $this->faker->randomElement($actionTypes),
            'request_data' => $this->generateRequestData(),
            'response_data' => $this->generateResponseData($statusCode),
            'response_time' => $this->faker->randomFloat(3, 10, 5000), // 10ms 到 5s
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'status_code' => $statusCode,
            'request_id' => Str::uuid()->toString(),
        ];
    }

    /**
     * 生成請求資料
     */
    private function generateRequestData(): array
    {
        $data = [];
        
        // 隨機添加一些常見的請求參數
        if ($this->faker->boolean(70)) {
            $data['page'] = $this->faker->numberBetween(1, 10);
        }
        
        if ($this->faker->boolean(50)) {
            $data['limit'] = $this->faker->numberBetween(10, 100);
        }
        
        if ($this->faker->boolean(30)) {
            $data['search'] = $this->faker->words(2, true);
        }
        
        if ($this->faker->boolean(40)) {
            $data['filters'] = [
                'status' => $this->faker->randomElement(['active', 'inactive', 'pending']),
                'category' => $this->faker->randomElement(['news', 'blog', 'product']),
            ];
        }

        return $data;
    }

    /**
     * 根據狀態碼生成回應資料
     */
    private function generateResponseData(int $statusCode): array
    {
        if ($statusCode >= 400) {
            // 錯誤回應
            return [
                'status' => 'error',
                'message' => $this->getErrorMessage($statusCode),
                'error_code' => 'ERR_' . $statusCode,
            ];
        }

        // 成功回應
        return [
            'status' => 'success',
            'message' => '操作成功',
            'data' => $this->generateSuccessData(),
        ];
    }

    /**
     * 根據狀態碼取得錯誤訊息
     */
    private function getErrorMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => '請求參數錯誤',
            401 => '未授權存取',
            403 => '權限不足',
            404 => '資源不存在',
            422 => '資料驗證失敗',
            500 => '伺服器內部錯誤',
            default => '未知錯誤',
        };
    }

    /**
     * 生成成功回應的資料
     */
    private function generateSuccessData(): array
    {
        return [
            'id' => $this->faker->numberBetween(1, 1000),
            'name' => $this->faker->name(),
            'email' => $this->faker->email(),
            'created_at' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 建立錯誤日誌
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => $this->faker->randomElement([400, 401, 403, 404, 422, 500]),
        ]);
    }

    /**
     * 建立成功日誌
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => $this->faker->randomElement([200, 201, 204]),
        ]);
    }

    /**
     * 建立慢請求日誌
     */
    public function slow(): static
    {
        return $this->state(fn (array $attributes) => [
            'response_time' => $this->faker->randomFloat(3, 1000, 10000), // 1s 到 10s
        ]);
    }

    /**
     * 建立特定動作類型的日誌
     */
    public function actionType(string $actionType): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => $actionType,
        ]);
    }
}

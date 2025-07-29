<?php

namespace Database\Factories;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * API Token 模型工廠
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiToken>
 */
class ApiTokenFactory extends Factory
{
    /**
     * 對應的模型名稱
     *
     * @var string
     */
    protected $model = ApiToken::class;

    /**
     * 定義模型的預設狀態
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plainToken = Str::random(80);
        
        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', $plainToken),
            'name' => fake()->words(2, true) . ' Token',
            'permissions' => ['api:read', 'api:write'],
            'expires_at' => Carbon::now()->addDays(30),
            'last_used_at' => null,
            'is_active' => true,
        ];
    }

    /**
     * 建立已過期的 Token
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => Carbon::now()->subDays(1),
        ]);
    }

    /**
     * 建立已停用的 Token
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * 建立永不過期的 Token
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    /**
     * 建立具有特定權限的 Token
     */
    public function withPermissions(array $permissions): static
    {
        return $this->state(fn (array $attributes) => [
            'permissions' => $permissions,
        ]);
    }

    /**
     * 建立具有完整權限的 Token
     */
    public function withFullPermissions(): static
    {
        return $this->state(fn (array $attributes) => [
            'permissions' => ['*'],
        ]);
    }
}
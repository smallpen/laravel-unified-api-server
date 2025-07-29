<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 建立 API Token 資料表
 */
return new class extends Migration
{
    /**
     * 執行遷移
     */
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('使用者 ID');
            $table->string('token_hash', 64)->unique()->comment('Token 雜湊值');
            $table->string('name')->comment('Token 名稱');
            $table->json('permissions')->nullable()->comment('權限陣列');
            $table->timestamp('expires_at')->nullable()->comment('過期時間');
            $table->timestamp('last_used_at')->nullable()->comment('最後使用時間');
            $table->boolean('is_active')->default(true)->comment('是否啟用');
            $table->timestamps();
            
            // 索引
            $table->index('token_hash');
            $table->index('user_id');
            $table->index(['is_active', 'expires_at']);
            $table->index('last_used_at');
        });
    }

    /**
     * 回滾遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
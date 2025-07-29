<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 執行遷移
     */
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('使用者ID');
            $table->string('action_type')->comment('動作類型');
            $table->json('request_data')->nullable()->comment('請求資料');
            $table->json('response_data')->nullable()->comment('回應資料');
            $table->decimal('response_time', 8, 3)->comment('回應時間（毫秒）');
            $table->string('ip_address', 45)->comment('IP位址');
            $table->text('user_agent')->nullable()->comment('使用者代理');
            $table->integer('status_code')->comment('HTTP狀態碼');
            $table->string('request_id')->unique()->comment('請求ID');
            $table->timestamps();
            
            // 建立索引以提升查詢效能
            $table->index(['user_id', 'created_at']);
            $table->index(['action_type', 'created_at']);
            $table->index(['status_code', 'created_at']);
            $table->index('request_id');
            
            // 外鍵約束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * 回滾遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};

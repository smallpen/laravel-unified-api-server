<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 建立使用者資料表
 */
return new class extends Migration
{
    /**
     * 執行遷移
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('使用者姓名');
            $table->string('email')->unique()->comment('電子郵件地址');
            $table->timestamp('email_verified_at')->nullable()->comment('電子郵件驗證時間');
            $table->string('password')->comment('密碼雜湊值');
            $table->rememberToken()->comment('記住我權杖');
            $table->timestamps();
            
            // 索引
            $table->index('email');
        });
    }

    /**
     * 回滾遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
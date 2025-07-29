<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('action_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('action_type')->unique()->comment('Action類型識別碼');
            $table->json('required_permissions')->comment('所需權限清單');
            $table->boolean('is_active')->default(true)->comment('是否啟用');
            $table->text('description')->nullable()->comment('權限描述');
            $table->timestamps();

            $table->index('action_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_permissions');
    }
};

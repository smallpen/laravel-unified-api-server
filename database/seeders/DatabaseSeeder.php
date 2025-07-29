<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * 主要資料庫種子資料類別
 */
class DatabaseSeeder extends Seeder
{
    /**
     * 執行資料庫種子資料
     */
    public function run(): void
    {
        $this->call([
            ActionPermissionSeeder::class,
            UserPermissionSeeder::class,
        ]);
    }
}
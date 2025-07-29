<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| 這個檔案是定義所有基於閉包的控制台命令的地方。每個閉包都綁定到一個
| 命令實例，允許一個簡單的方法與每個命令的IO方法互動。
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
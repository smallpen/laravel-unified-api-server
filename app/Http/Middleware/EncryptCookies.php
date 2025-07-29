<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

/**
 * 加密Cookie中介軟體
 */
class EncryptCookies extends Middleware
{
    /**
     * 不應該被加密的cookie名稱
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
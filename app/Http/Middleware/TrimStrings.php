<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

/**
 * 修剪字串中介軟體
 */
class TrimStrings extends Middleware
{
    /**
     * 不應該被修剪的屬性名稱
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];
}
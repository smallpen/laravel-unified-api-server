<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

/**
 * CSRF Token驗證中介軟體
 */
class VerifyCsrfToken extends Middleware
{
    /**
     * 應該從CSRF驗證中排除的URI
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',
    ];
}
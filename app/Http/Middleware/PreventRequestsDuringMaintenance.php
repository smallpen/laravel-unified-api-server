<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

/**
 * 維護模式中介軟體
 */
class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * 在維護模式期間應該可以存取的URI
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
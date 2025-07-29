<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * 基礎控制器類別
 * 
 * 提供所有控制器的基本功能
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
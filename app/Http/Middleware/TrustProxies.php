<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * 信任代理中介軟體
 */
class TrustProxies extends Middleware
{
    /**
     * 此應用程式的受信任代理
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * 應該用來偵測代理的標頭
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
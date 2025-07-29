<?php

namespace Tests\Unit\Providers;

use Tests\TestCase;
use App\Providers\ResponseFormatterServiceProvider;
use App\Contracts\ResponseFormatterInterface;
use App\Services\ResponseFormatter;

/**
 * ResponseFormatterServiceProvider單元測試
 * 
 * 測試服務提供者是否正確註冊ResponseFormatter服務
 */
class ResponseFormatterServiceProviderTest extends TestCase
{
    /**
     * 測試服務提供者註冊了正確的服務
     * 
     * @return void
     */
    public function test_provides_correct_services(): void
    {
        $provider = new ResponseFormatterServiceProvider($this->app);
        
        $expectedServices = [
            ResponseFormatterInterface::class,
            ResponseFormatter::class,
            'response.formatter',
        ];
        
        $this->assertEquals($expectedServices, $provider->provides());
    }

    /**
     * 測試ResponseFormatterInterface綁定到ResponseFormatter
     * 
     * @return void
     */
    public function test_response_formatter_interface_binding(): void
    {
        $formatter = $this->app->make(ResponseFormatterInterface::class);
        
        $this->assertInstanceOf(ResponseFormatter::class, $formatter);
    }

    /**
     * 測試ResponseFormatter類別可以被解析
     * 
     * @return void
     */
    public function test_response_formatter_class_resolution(): void
    {
        $formatter = $this->app->make(ResponseFormatter::class);
        
        $this->assertInstanceOf(ResponseFormatter::class, $formatter);
    }

    /**
     * 測試response.formatter別名可以被解析
     * 
     * @return void
     */
    public function test_response_formatter_alias_resolution(): void
    {
        $formatter = $this->app->make('response.formatter');
        
        $this->assertInstanceOf(ResponseFormatter::class, $formatter);
    }

    /**
     * 測試ResponseFormatter是否為單例模式
     * 
     * @return void
     */
    public function test_response_formatter_is_singleton(): void
    {
        $formatter1 = $this->app->make('response.formatter');
        $formatter2 = $this->app->make('response.formatter');
        
        $this->assertSame($formatter1, $formatter2);
    }

    /**
     * 測試不同的綁定方式是否回傳相同的實例
     * 
     * @return void
     */
    public function test_different_bindings_return_same_instance(): void
    {
        $formatter1 = $this->app->make(ResponseFormatterInterface::class);
        $formatter2 = $this->app->make(ResponseFormatter::class);
        $formatter3 = $this->app->make('response.formatter');
        
        $this->assertSame($formatter1, $formatter2);
        $this->assertSame($formatter2, $formatter3);
    }
}
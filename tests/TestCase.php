<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * 測試基底類別
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
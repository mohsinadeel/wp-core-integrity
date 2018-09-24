<?php

namespace tests;

use Inceptionsol\Coreintegrity\core\WP_Core_Integrity;
use PHPUnit\Framework\TestCase;

class BasicTest extends TestCase
{

    public function test_plugin_initiated()
    {
        $plugin = new WP_Core_Integrity();
        $this->assertFalse($plugin->get_initiated());
    }
}
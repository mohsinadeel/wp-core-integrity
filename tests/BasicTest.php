<?php


namespace tests;

use PHPUnit\Framework\TestCase;

class BasicTest extends TestCase
{

    public function test_plugin_initiated()
    {
        $plugin = new \mohsinadeel\wpcoreintegrity\core\WP_Core_Integrity();
        $this->assertFalse($plugin->get_initiated());
    }
}
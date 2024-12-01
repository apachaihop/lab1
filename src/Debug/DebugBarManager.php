<?php

namespace Anko\Lab1\Debug;

use DebugBar\DebugBar;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\JavascriptRenderer;

class DebugBarManager
{
    private static $debugbar = null;
    private static $renderer = null;

    public static function initialize()
    {
        try {
            if (self::$debugbar === null) {
                self::$debugbar = new DebugBar();
                self::$debugbar->addCollector(new MessagesCollector());
                self::$debugbar->addCollector(new TimeDataCollector());
                self::$debugbar->addCollector(new MemoryCollector());

                self::$renderer = self::$debugbar->getJavascriptRenderer();
                self::$renderer->setBaseUrl('/lab1/vendor/maximebf/debugbar/src/DebugBar/Resources');
            }
        } catch (\Exception $e) {
            error_log("DebugBar initialization failed: " . $e->getMessage());
            self::$debugbar = null;
            self::$renderer = null;
        }
    }

    public static function getDebugBar()
    {
        if (!self::$debugbar) {
            self::initialize();
        }
        return self::$debugbar;
    }

    public static function getRenderer()
    {
        if (!self::$renderer) {
            self::initialize();
        }
        return self::$renderer;
    }

    public static function isEnabled()
    {
        return self::$debugbar !== null && self::$renderer !== null;
    }

    public static function getTimeCollector()
    {
        if (!self::$debugbar) {
            self::initialize();
        }
        return self::$debugbar['time'];
    }
}

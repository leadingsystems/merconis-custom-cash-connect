<?php

namespace Merconis\Custom;

class apiController_cleanup
{
    protected static $objInstance;

    /** @var \LeadingSystems\Api\ls_apiController $obj_apiReceiver */
    protected $obj_apiReceiver = null;

    protected function __construct()
    {
    }

    final private function __clone()
    {
    }

    public static function getInstance()
    {
        if (!is_object(self::$objInstance)) {
            self::$objInstance = new self();
        }

        return self::$objInstance;
    }

    public function processRequest($str_resourceName, $obj_apiReceiver)
    {
        if (!$str_resourceName || !$obj_apiReceiver) {
            return;
        }

        $this->obj_apiReceiver = $obj_apiReceiver;

        /*
         * If this class has a method that matches the resource name, we call it.
         * If not, we don't do anything because another class with a corresponding
         * method might have a hook registered.
         */
        if (method_exists($this, $str_resourceName)) {
            $this->{$str_resourceName}();
        }
    }

    /**
     * Automated product cleanup:
     *
     * Test
     *
     * Scope: FE
     *
     * Allowed user types: apiUser
     */
    protected function apiResource_performProductCleanup()
    {
        $this->obj_apiReceiver->requireScope(['FE']);
        $this->obj_apiReceiver->requireUser(['apiUser']);

        $obj_merconis_productCleanupAutomator = merconis_productCleanupAutomator::getInstance();
        $obj_merconis_productCleanupAutomator->run($this->obj_apiReceiver);
    }
}
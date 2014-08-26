<?php

namespace Bolt\Extension\Bolt\RateIt;

/**
 * RateIt extension for Bolt
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Extension extends \Bolt\BaseExtension
{
    const NAME = 'rateit';

    public function getName()
    {
        return Extension::NAME;
    }

    public function initialize()
    {
    }

    /**
     * Set up config and defaults
     */
    private function setConfig()
    {
    }

}
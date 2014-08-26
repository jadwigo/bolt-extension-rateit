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
        /*
         * Config
         */
        $this->setConfig();

        /*
         * Backend
         */
        if ($this->app['config']->getWhichEnd() == 'backend') {
            // Check & create database tables if required
            $records = new RateItRecords($this->app);
            $records->dbCheck();
        }

        /*
         * Frontend
         */
        if ($this->app['config']->getWhichEnd() == 'frontend') {
            //
        }
    }

    /**
     * Set up config and defaults
     */
    private function setConfig()
    {
        // Sane defaults
        if (empty($this->config['stars'])) {
            $this->config['stars'] = 5;
        }
        if (empty($this->config['increment'])) {
            $this->config['increment'] = 0.5;
        }
        if (!empty($this->config['size']) && $this->config['size'] == 'large') {
            $this->config['px'] = 32;
            $this->config['class'] = 'bigstars';
        }
        else {
            $this->config['px'] = 16;
            $this->config['class'] = '';
        }
        if (empty($this->config['reponse_class'])) {
            $this->config['reponse_class'] = '';
        }
        if (empty($this->config['response_msg'])) {
            $this->config['response_msg'] = '';
        }
        if (empty($this->config['logging'])) {
            $this->config['logging'] = 'off';
        }
    }

}
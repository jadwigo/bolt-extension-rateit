<?php

namespace Bolt\Extension\Bolt\RateIt;

use Bolt\Extensions\Snippets\Location as SnippetLocation;

/**
 * RateIt extension for Bolt
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Extension extends \Bolt\BaseExtension
{
    /**
     * @var string Extension name
     */
    const NAME = 'RateIt';

    /**
     * @var string Extension's container
     */
    const CONTAINER = 'extensions.RateIt';

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
            // Twig hook
            $this->addTwigFunction('rateit', 'twigRateIt');
        }

        /*
         * AJAX
         */
        if ($this->app['config']->getWhichEnd() == 'async') {
            $this->setController();
        }
    }

    public function twigRateIt($record = null)
    {
        // Add our twig path
        $this->app['twig.loader.filesystem']->addPath(__DIR__ . '/assets/');

        // Add needed JavaScript & CSS
        $this->addAssets();

        // Get the Twig record object
        $record = $this->getRecord($record);

        $max = $this->config['stars'];
        $inc = $this->config['increment'];

        $bolt_record_id = $record->id;
        $bolt_contenttype = strtolower($record->contenttype['name']);

        if (empty($bolt_record_id) || empty($bolt_contenttype)) {
            return new \Twig_Markup('<!-- Error: content not found -->', 'UTF-8');
        }

        // Get the current value of the rating
        try {
            $db = new RateItRecords($this->app);
            $lookup = $db->dbLookupRating(array(
                'contenttype' => $bolt_contenttype,
                'record_id' => $bolt_record_id)
            );

            if(!empty($lookup) && isset($lookup['vote_avg'])) {
                $current_val = $lookup['vote_avg'];
            }
            else {
                $current_val = 0;
            }
        } catch (\Exception $e) {
            $current_val = 0;
        }

        // Customisation goes here. See http://rateit.codeplex.com/documentation
        $html = $this->app['render']->render('rateit.twig', array(
            'config' => $this->config,
            'max' => $max,
            'inc' => $inc,
            'record' => $record,
            'bolt_record_id' => $bolt_record_id,
            'bolt_contenttype' => $bolt_contenttype,
            'current_val' => $current_val
        ));

        return new \Twig_Markup($html, 'UTF-8');
    }

    /**
     * Config defaults
     */
    protected function getDefaultConfig()
    {
        return array(
            'stylesheet' => 'rateit.css',
            'location' => 'head',
            'stars' => 5,
            'increment' => 0.5,
            'tooltips' => '',
            'reponse_class' => '',
            'response_msg' => '',
            'logging' => 'off',
        );
    }

    /**
     * Set up config
     */
    private function setConfig()
    {
        // Sane defaults
        if (!empty($this->config['size']) && $this->config['size'] == 'large') {
            $this->config['px'] = 32;
            $this->config['class'] = 'bigstars';
        }
        else {
            $this->config['px'] = 16;
            $this->config['class'] = '';
        }
        if (!empty($this->config['tooltips'])) {
            $this->config['tooltips'] = json_encode($this->config['tooltips']);
        }
    }

    /**
     * Create controller and define routes
     */
    private function setController()
    {
        $this->app->mount('/async/RateIt', new RateItController());
    }

    private function addAssets()
    {
        // Inject CSS
        if ($this->config['location'] == 'body') {
            $this->addCSS('css/' . $this->config['stylesheet'], true);

        } else {
            $this->addCSS('css/' . $this->config['stylesheet']);
        }

        // JavaScript
        $this->addJavascript('js/bolt.rateit.min.js', true);

        $js = $this->app['render']->render('_javascript.twig', array(
            'tooltips' => $this->config['tooltips']
        ));

        $this->addSnippet(SnippetLocation::END_OF_HTML, $js);
    }

    private function getRecord($record = null)
    {
        if ($record) {
            return $record;
        }

        if (isset($this->record)) {
            return $this->record;
        }

        $globalTwigVars = $this->app['twig']->getGlobals('record');

        if (isset($globalTwigVars['record'])) {
            return $globalTwigVars['record'];
        }
        else {
            return false;
        }

        return $record;
    }

    private function isCookieSet($record = null)
    {
        $bolt_record_id = $record->id;
        $bolt_contenttype = strtolower( $record->contenttype['name'] );

        if (isset($_COOKIE['rateit'][$bolt_contenttype][$bolt_record_id])) {
            return true;
        }
        return false;
    }

}
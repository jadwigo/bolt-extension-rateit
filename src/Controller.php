<?php

namespace Bolt\Extension\Bolt\RateIt;

use Symfony\Component\HttpFoundation\Request;

/**
 * RateIt Controller
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Controller
{
    /**
     * @var Silex\Application
     */
    private $app;

    /**
     * @var Extension config array
     */
    private $config;

    public function __construct(\Bolt\Application $app)
    {
        $this->app = $app;
        $this->config = $this->app['extensions.' . Extension::NAME]->config;
    }

    /**
     * AJAX POST controller
     *
     * @param array|string $vars Do something
     * @return NULL
     */
    function ajaxRateIt(Request $request, $errors = null)
    {
        // Initialise rating array
        $rating = array();

        // If we were passed an error, exit
        if (is_array($errors)) {
            return;
        }

        // Check that we're here for a POST instead of a programmer typo
        if ($request->getMethod() === 'POST') {
            // Database records object
            $db = new RateItRecords($this->app);

            if ($this->config['logging'] == 'on') {
                $this->dbLogVote($request);
            }

            $rating['contenttype'] = $request->get('contenttype');
            $rating['record_id'] = $request->get('record_id');
            $rating['vote'] = floatval($request->get('value'));

            $db_rating = $db->dbLookupRating($rating);

            if (empty($db_rating)) {
                $rating['create'] = true;
                $rating['vote_num'] = 1;
                $rating['vote_sum'] = $rating['vote'];
                $rating['vote_avg'] = $rating['vote_sum'];
            }
            else {
                $rating['create'] = false;
                $rating['vote_num'] = $db_rating['vote_num'] + 1;
                $rating['vote_sum'] = $db_rating['vote_sum'] + $rating['vote'];
                $rating['vote_avg'] = round($db_rating['vote_sum'] / $db_rating['vote_num'], 2);
            }

            // Write it back
            $response = $db->dbUpdateRating($rating);

            echo json_encode($response);
        }

        exit;
    }
}

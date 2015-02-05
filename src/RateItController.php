<?php

namespace Bolt\Extension\Bolt\RateIt;

use Silex;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RateIt Controller
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class RateItController implements ControllerProviderInterface
{
    /**
     * @var array
     */
    private $config;

    /**
     * @param  Silex\Application           $app
     * @return \Silex\ControllerCollection
     */
    public function connect(Silex\Application $app)
    {
        $this->config = $app[Extension::CONTAINER]->config;

        /**
         * @var $ctr \Silex\ControllerCollection
         */
        $ctr = $app['controllers_factory'];

        $ctr->match('/', array($this, 'ajaxRateIt'))
            ->bind('ajaxRateIt')
            ->method('POST');

        return $ctr;
    }

    /**
     * AJAX POST controller
     *
     * @param  Silex\Application                             $app
     * @param  Symfony\Component\HttpFoundation\Request      $request
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    public function ajaxRateIt(Silex\Application $app, Request $request, $errors = null)
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
            $db = new RateItRecords($app);

            if ($this->config['logging'] == 'on') {
                $db->dbLogVote($request);
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

            return new JsonResponse($response);
        }

        return new JsonResponse('Invalid request type!', JsonResponse::HTTP_FORBIDDEN);
    }
}

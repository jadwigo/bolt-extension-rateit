<?php

namespace Bolt\Extension\Bolt\RateIt;

use Silex;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Cookie;
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
     * @param \Silex\Application $app
     *
     * @return \Silex\ControllerCollection
     */
    public function connect(Silex\Application $app)
    {
        $this->config = $app[Extension::CONTAINER]->config;

        /** @var $ctr \Silex\ControllerCollection */
        $ctr = $app['controllers_factory'];

        $ctr->match('', array($this, 'ajaxRateIt'))
            ->bind('ajaxRateIt')
            ->method('POST');

        return $ctr;
    }

    /**
     * AJAX POST controller
     *
     * @param \Silex\Application                        $app
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function ajaxRateIt(Silex\Application $app, Request $request, $errors = null)
    {
        // Response array
        $data = array();

        // If we were passed an error, exit
        if (is_array($errors)) {
            return;
        }

        // Check that we're here for a POST instead of a programmer typo
        if ($request->getMethod() !== 'POST') {
            return new Response('', Response::HTTP_MOVED_PERMANENTLY);
        }

        // Database records object
        $db = new RateItRecords($app);

        $response    = new Response(null, Response::HTTP_OK);
        $contenttype = $request->get('contenttype');
        $record_id   = $request->get('record_id');
        $cookie      = $this->getVoteCookie($contenttype, $record_id, floatval($request->get('value')));
        $votedata    = $this->getVote($request, $db, $cookie);

        // Prevent vote stuffing
        if ($votedata['vote'] > 0 && $this->hasVoteCookie($request, $contenttype, $record_id)) {
            $response->setStatusCode(Response::HTTP_I_AM_A_TEAPOT);
            $this->makeTea($app, $response, $votedata['vote']);
        } else {
            // Log it, if desired
            if ($this->config['logging'] === 'on') {
                $db->dbLogVote($votedata);
            }

            // Write it back
            try {
                $db->dbUpdateRating($votedata);

                if ($votedata['vote'] == 0) {
                    $this->clearVoteCookie($response, $cookie);
                } else {
                    $this->setVoteCookie($response, $cookie);
                    $this->makeTea($app, $response, $votedata['vote']);
                }
            } catch (\Exception $e) {
                $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
                $this->makeTea($app, $response, $e->getMessage());
            }
        }

        return $response;
    }

    /**
     * Get the vote data
     *
     * @param Request $request
     * @param Cookie  $cookie
     *
     * @return array
     */
    private function getVote(Request $request, RateItRecords $db, Cookie $cookie)
    {
        $contenttype = $request->get('contenttype');
        $record_id   = $request->get('record_id');
        $db_rating   = $db->dbLookupRating($contenttype, $record_id);
        $vote        = floatval($request->get('value'));

        $req_cookie = $request->cookies->get('rateit', 0);
        $last_vote  = isset($req_cookie[$contenttype][$record_id]) ? $req_cookie[$contenttype][$record_id] : 0;

        if (empty($db_rating)) {
            $create   = true;
            $vote_num = $vote == 0 ? 0 : 1;
            $vote_sum = $vote;
            $vote_avg = $vote;
        } else {
            $create   = false;

            if($vote_num == 0 && $vote == 0) {
                // first vote has been reset
                $vote_num = 0;
                $vote_sum = 0;
                $vote_avg = 0;
            } elseif ($vote == 0) {
                // Vote's been reset
                $vote_num = $last_vote == 0 ? $db_rating['vote_num'] : $db_rating['vote_num'] - 1;
                $vote_sum = $db_rating['vote_sum'] - $last_vote;
                $vote_avg = round($vote_sum / $vote_num, 2);
            } else {
                $vote_num = $db_rating['vote_num'] + 1;
                $vote_sum = $db_rating['vote_sum'] + $vote;
                $vote_avg = round($vote_sum / $vote_num, 2);
            }
        }

        return array(
            'datetime'    => date('Y-m-d H:i:s', time()),
            'ip'          => $request->getClientIp(),
            'cookie'      => $cookie->getName(),
            'content_id'  => $record_id,
            'contenttype' => $contenttype,
            'vote'        => $vote,
            'vote_num'    => $vote_num,
            'vote_sum'    => $vote_sum,
            'vote_avg'    => $vote_avg,
            'create'      => $create
        );
    }

    /**
     * @param Request $request
     * @param string  $contenttype
     * @param integer $record_id
     *
     * @return string
     */
    private function hasVoteCookie(Request $request, $contenttype, $record_id)
    {
        $cookie = $request->cookies->get('rateit');
        if (isset($cookie[$contenttype][$record_id])) {
            return true;
        }

        return false;
    }

    /**
     * Get a cookie string
     *
     * @param string  $contenttype
     * @param integer $record_id
     * @param integer $value
     *
     * @return string
     */
    private function getVoteCookie($contenttype, $record_id, $value)
    {
        $expire = strtotime('+1 year');
        $name   = "rateit[$contenttype][$record_id]";

        return new Cookie($name, $value, $expire);
    }

    /**
     * Set the cookie set for a record's vote
     *
     * @param Response $response
     * @param Cookie   $cookie
     *
     * @return Response
     */
    private function setVoteCookie(Response $response, Cookie $cookie)
    {
        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * Clear the cookie set for a record's vote
     *
     * @param Response $response
     * @param Cookie   $cookie
     *
     * @return Response
     */
    private function clearVoteCookie(Response &$response, Cookie $cookie)
    {
        return $response->headers->clearCookie($cookie->getName());
    }

    /**
     * @param \Silex\Application                         $app
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @param integer|string                             $value
     */
    private function makeTea(Silex\Application $app, &$response, $value)
    {
        if ($response->getStatusCode() === Response::HTTP_OK) {
            $context = array(
                'message' => str_replace('%RATING%', $value, $this->config['response_msg']),
                'class'   => $this->config['response_msg_class']
            );
        } elseif ($response->getStatusCode() === Response::HTTP_I_AM_A_TEAPOT) {
            $context = array(
                'message' => str_replace('%RATING%', $value, $this->config['already_msg']),
                'class'   => $this->config['already_msg_class']
            );
        } else {
            $context = array(
                'message' => str_replace('%ERROR%', $value, $this->config['error_msg']),
                'class'   => $this->config['error_msg_class']
            );
        }

        $app['twig.loader.filesystem']->addPath(dirname(__DIR__) . '/assets');
        $html = $app['render']->render('_teapot.twig', $context);
        $html = new \Twig_Markup($html, 'UTF-8');

        $response->setContent($html);
    }
}

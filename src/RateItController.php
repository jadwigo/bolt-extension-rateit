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

        $response    = new JsonResponse(null, Response::HTTP_OK);
        $contenttype = $request->get('contenttype');
        $record_id   = $request->get('record_id');
        $cookie      = $this->getVoteCookie($contenttype, $record_id, floatval($request->get('value')));
        $votedata    = $this->getVote($request, $db, $cookie);

        // Log it, if desired
        if ($this->config['logging'] === 'on') {
            $db->dbLogVote($votedata);
        }

        // Write it back
        try {
            $db->dbUpdateRating($votedata);

            $data = array(
                'retval' => 0,
                'msg'    => $votedata['vote'] == 0 ? '' : str_replace('%RATING%', $votedata['vote'], $this->config['response_msg'])
            );

            if ($votedata['vote'] == 0) {
                $this->clearVoteCookie($response, $cookie);
            } else {
                $this->setVoteCookie($response, $cookie);
            }
        } catch (\Exception $e) {
            $data = array(
                'retval' => 1,
                'msg'    => 'Sorry, something went wrong: ' .  $e->getMessage()
            );

            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response->setData($data);
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

        // $cookie->getValue()
        $req_cookie = $request->cookies->get('rateit', 0);
        $last_vote  = isset($req_cookie[$contenttype][$record_id]) ? $req_cookie[$contenttype][$record_id] : 0;

        if (empty($db_rating)) {
            $create   = true;
            $vote_num = $vote === 0 ? 0 : 1;
            $vote_sum = $vote;
            $vote_avg = $vote;
        } else {
            $create   = false;

            if ($vote == 0) {
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
}

<?php

namespace Bolt\Extension\Bolt\RateIt;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\DBAL\Schema\Schema;

/**
 *
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class RateItRecords
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

        $prefix = $this->app['config']->get('general/database/prefix', "bolt_");
        $this->table_name = $prefix . 'rateit';
        $this->log_table_name = $prefix . 'rateit_log';
    }

    /**
     * Log the readers rating vote
     *
     * @since Bolt 1.5.1
     *
     * @param array|string $vars Do something
     * @return NULL
     */
    public function dbLogVote(Request $request)
    {
        $map = array(
            'datetime' => date("Y-m-d H:i:s", time()),
            'ip' => $request->getClientIp(),
            'cookie' => $request->cookies->get('bolt_session'),
            'content_id' => $request->get('record_id'),
            'contenttype' => $request->get('contenttype'),
            'vote' => floatval($request->get('value'))
        );

        $this->app['db']->insert($this->log_table_name, $map);
    }



    /**
     * Lookup extension database to see if a rating exists for an existing
     * record and return it.
     *
     * @since Bolt 1.5.1
     *
     * @param string $contenttype The Bolt contenttype being rated
     * @param string $record_id The record ID being rated
     * @return array
     */
    public function dbLookupRating(Array $rating) {

        $query = "SELECT vote_num, vote_sum, vote_avg " .
                 "FROM {$this->table_name} " .
                 "WHERE (contenttype = :contenttype AND content_id = :content_id)";

        $map = array(
            ':contenttype' => $rating['contenttype'],
            ':content_id' => $rating['record_id']
        );

        return $this->app['db']->fetchAssoc($query, $map);
    }

    /**
     * Update extension database rating for an existing record with results of
     * incomming vote
     *
     * @since Bolt 1.5.1
     *
     * @param array $rating Array of details about the vote that was made
     * @return array        Array to be returned to AJAX client
     */
    public function dbUpdateRating(Array $rating) {
        $response = array();

        $map = array(
            'content_id'  => $rating['record_id'],
            'contenttype' => $rating['contenttype'],
            'vote_num'    => $rating['vote_num'],
            'vote_sum'    => $rating['vote_sum'],
            'vote_avg'    => $rating['vote_avg']
        );

        if ($rating['create'] === true) {
            $result = $this->app['db']->insert($this->table_name, $map);
        }
        else {
            $where = array(
                'contenttype' => $rating['contenttype'],
                'content_id' => $rating['record_id']
            );

            $result = $this->app['db']->update($this->table_name, $map, $where);
        }

        if ($result === 1) {
            $response['retval'] = 0;
            $response['msg'] = str_replace( '%RATING%', $rating['vote'], $this->config['response_msg']);
            setcookie("rateit[{$rating['contenttype']}][{$rating['record_id']}]", true, time()+31536000, '/');
        }
        else {
            $response['retval'] = 1;
            $response['msg'] = 'Sorry, something went wrong';
        }
        return $response;
    }

    /**
     * Create/update database tables
     */
    public function dbCheck()
    {
        $me = $this;

        // Rating table
        $this->app['integritychecker']->registerExtensionTable(
            function(Schema $schema) use ($me) {
                // Define table
                $table = $schema->createTable($me->table_name);

                // Add primary column
                $table->addColumn("id", "integer", array('autoincrement' => true));
                $table->setPrimaryKey(array("id"));

                // Add working columns
                $table->addColumn("content_id", "integer", array("length" => 11));
                $table->addColumn("contenttype", "string", array("length" => 32));
                $table->addColumn("vote_num", "integer");
                $table->addColumn("vote_sum", "decimal", array("scale" => '2'));
                $table->addColumn("vote_avg", "decimal", array("scale" => '2'));

                // Index column(s)
                $table->addIndex(array('content_id'));
                $table->addIndex(array('contenttype'));
                return $table;
            });

        // Log table
        $this->app['integritychecker']->registerExtensionTable(
            function(Schema $schema) use ($me) {
                // Define table
                $table = $schema->createTable($me->log_table_name);

                // Add primary column
                $table->addColumn("id", "integer", array('autoincrement' => true));
                $table->setPrimaryKey(array("id"));

                // Add working columns
                $table->addColumn("datetime", "datetime");
                $table->addColumn("ip", "string", array("length" => 39));
                $table->addColumn("cookie", "string");
                $table->addColumn("content_id", "integer", array("length" => 11));
                $table->addColumn("contenttype", "string", array("length" => 32));
                $table->addColumn("vote", "decimal", array("scale" => '2'));

                return $table;
            });
    }
}
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

    public function __construct(Silex\Application $app)
    {
        $this->app = $app;
        $this->config = $this->app['extensions.' . Extension::NAME]->config;

        $prefix = $this->app['config']->get('general/database/prefix', "bolt_");
        $this->table_name = $prefix . 'rateit';
        $this->log_table_name = $prefix . 'rateit_log';
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
<?php

namespace Bolt\Extension\Bolt\RateIt;

use Bolt\Application;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Schema;

/**
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class RateItRecords
{
    /** @var Silex\Application */
    private $app;

    /** @var array Extension config  */
    private $config;

    /**
     * @param \Bolt\Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $this->app['extensions.' . Extension::NAME]->config;

        $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');

        $this->table_name     = $prefix . 'rateit';
        $this->log_table_name = $prefix . 'rateit_log';
    }

    /**
     * Log the readers rating vote
     *
     * @param array $vote
     */
    public function dbLogVote(array $vote)
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $query */
        $this->app['db']
            ->createQueryBuilder()
            ->insert($this->log_table_name)
            ->values(array(
                'datetime'    => ':datetime',
                'ip'          => ':ip',
                'cookie'      => ':cookie',
                'content_id'  => ':content_id',
                'contenttype' => ':contenttype',
                'vote'        => ':vote'
            ))
            ->setParameters(array(
                'datetime'    => date('Y-m-d H:i:s', time()),
                'ip'          => $vote['ip'],
                'cookie'      => $vote['cookie'],
                'content_id'  => $vote['content_id'],
                'contenttype' => $vote['contenttype'],
                'vote'        => $vote['vote']
            ))
            ->execute();
    }

    /**
     * Lookup extension database to see if a rating exists for an existing
     * record and return it.
     *
     * @param string  $contenttype
     * @param integer $record_id
     *
     * @return array
     */
    public function dbLookupRating($contenttype, $record_id)
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $query */
        $query = $this->app['db']
            ->createQueryBuilder()
            ->select('vote_num, vote_sum, vote_avg')
            ->from($this->table_name)
            ->where('contenttype = :contenttype', 'content_id = :content_id')
            ->setParameters(array(
                'content_id'  => $record_id,
                'contenttype' => $contenttype
            ))
        ;

        return $query->execute()->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Update extension database rating for an existing record with results of
     * incomming vote
     *
     * @param array $rating Array of details about the vote that was made
     *
     * @return array Array to be returned to AJAX client
     */
    public function dbUpdateRating(array $rating)
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $query */
        $query = $this->app['db']->createQueryBuilder();

        if ($rating['create'] === true) {
            $query->insert($this->table_name)
                ->values(array(
                    'content_id'  => ':content_id',
                    'contenttype' => ':contenttype',
                    'vote_num'    => ':vote_num',
                    'vote_sum'    => ':vote_sum',
                    'vote_avg'    => ':vote_avg'
            ));
        } else {
            $query->update($this->table_name)
                ->set('vote_num',    ':vote_num')
                ->set('vote_sum',    ':vote_sum')
                ->set('vote_avg',    ':vote_avg')
                ->where('contenttype   = :contenttype', 'content_id = :content_id')
            ;
        }

        $result = $query
            ->setParameters(array(
                'content_id'  => $rating['content_id'],
                'contenttype' => $rating['contenttype'],
                'vote_num'    => $rating['vote_num'],
                'vote_sum'    => $rating['vote_sum'],
                'vote_avg'    => $rating['vote_avg']
            ))
            ->execute();

        return $result;
    }

    /**
     * Create/update database tables
     */
    public function dbCheck()
    {
        $me = $this;

        // Rating table
        $this->app['integritychecker']->registerExtensionTable(
            function (Schema $schema) use ($me) {
                // Define table
                $table = $schema->createTable($me->table_name);

                $table->addColumn('id',          'integer', array('autoincrement' => true));
                $table->addColumn('content_id',  'integer', array('length' => 11));
                $table->addColumn('contenttype', 'string',  array('length' => 32));
                $table->addColumn('vote_num',    'integer');
                $table->addColumn('vote_sum',    'decimal', array('scale'  => 2));
                $table->addColumn('vote_avg',    'decimal', array('scale'  => 2));

                // Index column(s)
                $table->setPrimaryKey(array('id'));
                $table->addIndex(array('content_id'));
                $table->addIndex(array('contenttype'));

                return $table;
            });

        // Log table
        $this->app['integritychecker']->registerExtensionTable(
            function (Schema $schema) use ($me) {
                // Define table
                $table = $schema->createTable($me->log_table_name);

                $table->addColumn('id',          'integer', array('autoincrement' => true));
                $table->addColumn('datetime',    'datetime');
                $table->addColumn('ip',          'string',  array('length' => 39));
                $table->addColumn('cookie',      'string');
                $table->addColumn('content_id',  'integer', array('length' => 11));
                $table->addColumn('contenttype', 'string',  array('length' => 32));
                $table->addColumn('vote',        'decimal', array('scale'  => 2));

                $table->setPrimaryKey(array('id'));

                return $table;
            });
    }
}

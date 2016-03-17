<?php

class TransactionManager
{
    public $isTransactionStarted = false;
    static private $instance;

    public function startTransaction()
    {
        global $wpdb;

        $wpdb->query('BEGIN', $wpdb->dbh);
        $this->isTransactionStarted = true;

    }

    public function commit(QueryManager $queryManager)
    {
        global $wpdb;

        if ($this->isTransactionStarted) {
            foreach ($queryManager->getQueries() as $query) {
                $wpdb->query($query);
            }

            $wpdb->query('COMMIT', $wpdb->db);
            $this->isTransactionStarted = false;
        }
    }

    public function rollback()
    {
        global $wpdb;

        $wpdb->query('ROLLBACK', $wpdb->db);
    }

    static public function getInstance()
    {
        if (empty(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }
}
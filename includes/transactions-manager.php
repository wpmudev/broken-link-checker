<?php

class TransactionManager
{
    private $isTransactionStarted = false;
    static private $instance;

    public function start()
    {
        global $wpdb;

        $wpdb->query('BEGIN');
        $this->isTransactionStarted = true;

    }

    public function commit()
    {
        global $wpdb;

        if ($this->isTransactionStarted) {
            $wpdb->query('COMMIT');
            $this->isTransactionStarted = false;
        }
    }

    public function rollback()
    {
        global $wpdb;

        $wpdb->query('ROLLBACK');
    }

    static public function getInstance()
    {
        if (empty(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }
}
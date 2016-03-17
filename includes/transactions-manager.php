<?php

class TransactionManager
{
    private $isTransactionStarted = false;
    static private $instance;

    public function start()
    {
        global $wpdb;

        $wpdb->query('BEGIN');

    }

    public function commit()
    {
        global $wpdb;

        if (!$this->isTransactionStarted) {
            $wpdb->query('BEGIN');
        }

        try {
            $wpdb->query('COMMIT');
            $this->isTransactionStarted = false;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
        }
    }

    static public function getInstance()
    {
        if (empty(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }
}
<?php

class TransactionManager{

    public function startTransaction(){
        global $wpdb;
        mysqli_query('BEGIN', $wpdb->dbh);

    }
    public function commit(){
        global $wpdb;

        mysqli_query('COMMIT', $wpdb->db);
    }
    public function rollback(){
        global $wpdb;

        mysqli_query('ROLLBACK', $wpdb->db);
    }
}
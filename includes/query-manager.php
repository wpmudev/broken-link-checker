<?php

class QueryManager{

    var $queries = array();

    public function instanceManager(){

    }

    public function getQueries(){
        return $this->queries;
    }
    public function addInstance($instance){
        $this->queries[] = $instance->getQuery();
    }

    public function clearQueries(){
        $this->queries = array();
    }
}

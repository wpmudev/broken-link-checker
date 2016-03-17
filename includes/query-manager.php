<?php

class QueryManager{

    var $queries = array();

    public function getQueries(){
        return $this->queries;
    }
//    public function getQueries2(){
//        return $this->queries;
//    }
    public function addInstance($instance){
        $this->queries[] = $instance->getQuery();
    }
    public function addLink($link){
        $this->queries[] = $link->save();
    }

    public function clearQueries(){
        $this->queries = array();
    }
//    public function clearQueries2(){
//        $this->queries = array();
//    }
}

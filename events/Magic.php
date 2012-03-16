<?php

namespace ext\activedocument\events;

/**
 * Magic event
 * Used when handling non-existent variable|method events
 */
class Magic extends \CEvent {

    const GET='__get';
    const SET='__set';
    const SETIS='__isset';
    const SETUN='__unset';
    const CALL='__call';

    /**
     * @var string One of the above constants
     */
    public $method;

    /**
     * @var string Request variable|method name
     */
    public $name;

    /**
     * @var mixed Result to return, if any
     */
    public $result;

    public function __construct($sender, $method, $name, $params=null) {
        parent::__construct($sender,$params);
        $this->method = $method;
        $this->name = $name;
    }

}
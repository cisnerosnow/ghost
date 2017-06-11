<?php
require 'ghost.php';
$ghost = new Ghost();
$ghost->connect('localhost', 'root', '', 'ghost_db');
$ghost->service('post', 'employee', array('name' => 'text', 'lastname' => 'text'));
$ghost->service('get', 'employee', array('name' => 'text')); //do not need the key cause can search using the other params
$ghost->service('put', 'employee', array('name' => 'text', 'lastname' => 'text', 'id' => 'key')); //put needs key
$ghost->service('delete', 'employee', array('id' => 'key')); //delete needs key
$ghost->run();

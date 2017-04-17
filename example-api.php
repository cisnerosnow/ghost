<?php
require 'ghost.php'; //we are invoking a ghost D:

$ghost = new Ghost();

//for now ghost connect to db using mysqli (only for MySQL db)
//NOTE: If you arent using MySQL check the example at the bottom of this doc
$ghost->connect('localhost', 'root', '', 'ghost_db'); //connect to ghost_db

//An array with field => 'type' this way ghost can validate inputs
//til now is just int and text validators (im working on validators)
$arr = array('name' => 'text', 'lastname' => 'text');

//This is an easy way to declare your post, get, put and delete
//first param is the method
//second param is the option (in this case a table name in ghost_db,
//but could be just a name so you can define 'custom options')
//$ghost->service('post', 'employee', $arr, function($ghost) {/*some code*/});
//$ghost->service('get', 'employee', $arr, function($ghost) {/*some code*/});
//$ghost->service('put', 'employee', $arr, function($ghost) {/*some code*/});
//$ghost->service('delete', 'employee', $arr, function($ghost) {/*some code*/});

//But wait... there's more...
//post,get,put and delete? that sounds like a crud
$ghost->service('crud', 'employee', $arr); //ok, that's better :)

//So ok, ghost is waiting for post,get,put and deletes over the option
//'employee', but as this is direcly a 'crud' then employee GOTTA
//be a tableName in the ghost_db that way the
//insert,select,update and delete sql statements
//are made automatically :)
$ghost->run(); //run ghost!, run!

//"But hey! hey!! i just want to receive a post in an option called
//'myOwnOption', validate some params and execute some code...
//AND i'm not using MySQL !! so ghost is not for me :("

//... easy as this:
/*$paramsToValidate = array('flavor' => 'text', 'color' => 'text');
$ghost->service('post', 'myOwnOption', $paramsToValidate, function($ghost) {
    //Then you can play with your received params
    //$ghost->params['flavor'];
    //$ghost->params['color'];
    //And return a response:
    return $ghost->response('this is cool :)', 200); //statusCode 200
});
$ghost->run();*/

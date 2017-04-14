<?php
require 'ghost.php';

$ghost = new Ghost();
$ghost->post('casper', function($params) { echo 'this is casper'; var_dump($params);});
$ghost->get('hamlet', function() { echo 'this is hamlet'; });
$ghost->get('witch', function() { echo 'this is witch'; });
$ghost->put('bloody', function($params) { echo 'hi, this is bloody... yup'; var_dump($params); });
$ghost->delete('slimer', function() { echo 'hi im the real slim shady, slimer than slim'; });
$ghost->run();

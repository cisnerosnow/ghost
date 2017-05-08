<?php
class Ghost
{
    protected $conf = array('post' => array(), 'get' => array(), 'put' => array(), 'delete' => array());
    protected $debug = FALSE;
    public $params = NULL;
    public $method = NULL;
    public $option = NULL;
    public $host = NULL;
    public $user = NULL;
    public $pass = NULL;
    public $db_name = NULL;

    public function connect($host, $user, $pass, $db_name) { //set mysql connection
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->db_name = $db_name;
    }

    public function get_connect() {
        return mysqli_connect($this->host, $this->user, $this->pass, $this->db_name);
    }

    public function sql($method, $option, $params) {
        //return call_user_func("sql_$method");
        $sql = '';
        switch($method) {
            case 'post':
                $sql = $this->sql_post($option, $params);
                break;
            case 'get':
                $sql = $this->sql_get($option, $params);
                break;
            case 'put':
                $sql = $this->sql_put($option, $params);
                break;
            case 'delete':
                $sql = $this->sql_delete($option, $params);
                break;
        }
        return $sql;
    }

    public function sql_post($option, $params) {
        $fields = '';
        $values = '';
        foreach ($params as $field => $value) {
            $fields .= "$field,";
            $values .= "'$value',";
        }
        $fields = trim($fields, ',');
        $values = trim($values, ',');
        return "INSERT INTO $option ($fields) VALUES ($values)";
    }

    public function sql_get($option, $params) {
        $fields = '';
        $values = '';
        foreach ($params as $field => $value) {
            $fields .= "$field,";
            $values .= "'$value',";
        }
        $fields = trim($fields, ',');
        $values = trim($values, ',');
        if (isset($params['id']) && is_numeric($params['id'])) {
            $sql = "SELECT $fields FROM $option WHERE id='$params[id]' LIMIT 1";
        } else {
            $sql = "SELECT $fields FROM $option";
        }
        return $sql;
    }

    public function sql_put($option, $params) {
        $set = '';
        foreach ($params as $field => $value) {
            if ($field != 'id') {
                $set .= "$field='$value',";
            }
        }
        $set = trim($set, ',');
        return "UPDATE $option SET $set WHERE id='$params[id]' LIMIT 1";
    }

    public function sql_delete($option, $params) {
        return "DELETE FROM $option WHERE id='$params[id]' LIMIT 1";
    }

    private function generate_callback_function($method) {

        switch($method) {
            case 'post':
            case 'delete':
                $function = function($gastly) {
                    $code = 'success';
                    $msg = '';
                    $sql = $gastly->sql($gastly->method, $gastly->option, $gastly->params);
                    $con = $gastly->get_connect();
                    if (!mysqli_query($con, $sql)) {
                        $code = 'error';
                        $msg = $sql;
                    }
                    return $gastly->response($msg, $code);
                };
                break;
            case 'get':
                $function = function($gastly) {
                    $code = 'success';
                    $sql = $gastly->sql($gastly->method, $gastly->option, $gastly->params);
                    $con = $gastly->get_connect();
                    $res = mysqli_query($con, $sql);
                    if ($res == TRUE && mysqli_num_rows($res) > 0) {
                        $code = 'success';
                        $msg = array();
                        while ($row = mysqli_fetch_assoc($res)) {
                            $msg[] = $row;
                        }
                    } else {
                        $code = 'error';
                        $msg = $sql;
                    }
                    return $gastly->response($msg, $code);
                };
                break;
            case 'put':
                $function = function($gastly) {
                    $code = 'success';
                    $sql = $gastly->sql('get', $gastly->option, $gastly->params);
                    $con = $gastly->get_connect();
                    $res = mysqli_query($con, $sql);
                    if ($res == TRUE && mysqli_num_rows($res) > 0) {
                        $sql = $gastly->sql($gastly->method, $gastly->option, $gastly->params);
                        if (mysqli_query($con, $sql)) {
                            $code = 'success';
                            $msg = '';
                        } else {
                            $code = 'error';
                            $msg = $sql;
                        }
                    } else {
                        $code = 'error';
                        $msg = 'The id does not exist';
                    }
                    return $gastly->response($msg, $code);
                };
                break;
        }

        return $function;
    }

    public function service($method = NULL, $option = NULL, $rules = NULL, $function = NULL) {
        if (isset($method, $option, $rules)) { //, $function)) {
            $methods = array('post', 'get', 'put', 'delete');
            if (in_array($method, $methods)) {
                $this->conf[$method][] = array('option' => $option, 'rules' => $rules, 'function' => $function);
            } else if ($method == 'crud') {
                $w_function = $function;
                foreach ($methods as $method) {
                    if ($w_function == NULL) {
                        $this->method = $method;
                        $function = $this->generate_callback_function($method);
                    }
                    $this->conf[$method][] = array('option' => $option, 'rules' => $rules, 'function' => $function);
                }
            }
        }
    }

    public function response($msg = '', $code = 0) {
        if (!is_numeric($code)) {
            if ($code == 'success') {
                $code = 200;
            } else {
                $code = 500;
            }
        }
        header("HTTP/1.1 $code");
        header('Content-Type: application/json; charset=UTF-8');
        die(json_encode(array('message' => $msg, 'code' => $code)));
        exit;
    }

    protected function process($method, $option, $params = NULL) {

        $found = FALSE;

        foreach ($this->conf[$method] as $key) {
            if ($option == $key['option']) {

                if ($params != NULL) {
                    $rules = $key['rules'];

                    if (in_array($method, array('put', 'delete'))) {
                        if (!isset($params['id'])) {
                            $this->response('Falta el id', 400);
                        }
                        if (!isset($rules['id'])) {
                            $rules['id'] = 'int';
                        }
                    }

                    /*if (count($rules) != count($params)) {
                        $this->response('El número de parámetros no coincide con los esperados', 401);
                    }*/

                    //Validations must stop at the first error, that way we
                    //mantain the server cool and stop the
                    //client from being a lazy validator
                    foreach ($rules as $field => $type) {
                        $wparam = $params[$field];
                        switch($type) {
                            case 'int':
                                if (!is_numeric($wparam)) {
                                    $this->response(array($field => 'Gotta be numeric'), 402);
                                }
                                break;
                            case 'email':
                                if (filter_var($wparam, FILTER_VALIDATE_EMAIL) === false) {
                                    $this->response(array($field => 'Gotta be a valid email'), 402);
                                }
                                break;
                        }
                    }
                }

                $this->option = $option;
                $this->params = $params;

                if ($key['function'] == NULL) {
                    $key['function'] = $this->generate_callback_function($method);
                    $key['function']($this);
                } else {
                    if (is_string($key['function'])) {
                        call_user_func($key['function'], array($this));
                    } else {
                        $key['function']($this);
                    }
                }
                $found = TRUE;
                break;
            }
        }

        exit;
    }

    function run() {

        if (in_array($_SERVER['REQUEST_METHOD'], array('POST', 'GET', 'PUT', 'DELETE'))) {

            $method = strtolower($_SERVER['REQUEST_METHOD']);
            $_METHOD = ($method == 'post') ? $_POST : $_GET;

            if (in_array($method, array('put', 'delete'))) {
                parse_str(file_get_contents('php://input'), $_METHOD);
            }

            $_METHOD['params'] = (isset($_METHOD['params'])) ? $_METHOD['params'] : NULL;
            if (isset($_METHOD['option'])) {
                $this->method = $method;
                $this->process($method, $_METHOD['option'], $_METHOD['params']);
            }
            exit;
        }
    }
}

<?php
class Ghost
{
    protected $conf = array('post' => array(), 'get' => array(), 'put' => array(), 'delete' => array());
    protected $debug = FALSE;
    public $params = NULL;
    public $param = NULL;
    public $method = NULL;
    public $option = NULL;
	public $files = array();
    public $con = NULL;

    public function connect($host, $user, $pass, $db_name) { //set mysql connection
        $this->con = mysqli_connect($host, $user, $pass, $db_name);
    }

    public function get_connect() {
        return $this->con;
    }

    public function getConnect() {
        return $this->con;
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

    //https://stackoverflow.com/questions/18910814/best-practice-to-generate-random-token-for-forgot-password
    public function createToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    public function post($table, $params) {
        $con = $this->con;
        $sql = $this->sql_post($option, $params);
        if (mysqli_query($con, $sql)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function get($table, $fields, $where, $limit = 1) {
        if (is_array($fields)) {
            $fields_str = '';
            foreach ($fields as $field) {
                $fields_str .= "$field,";
            }
            $fields_str  = trim($fields_str, ',');

            $wheres = '';
            foreach ($where as $key => $value) {
                $wheres .= "$key='$value' AND ";
            }
            $wheres = trim($wheres, ' AND ');

            $con = $this->con;
            $limit = ($limit == FALSE) ? '' : "LIMIT $limit";
            $sql = utf8_decode("SELECT $fields_str FROM $table WHERE $wheres $limit");
            $res = mysqli_query($con, $sql);
            if ($res == TRUE && mysqli_num_rows($res) > 0) {
                $results = array();
                while ($row = mysqli_fetch_assoc($res)) {
                    $results[] = $row;
                    //echo json_encode($myArray);
                }
                return $results;
            } else {
                return FALSE;
            }
        }
    }

    public function put($table, $params, $where, $limit = 1) {
        if (is_array($params)) {
            $sets = '';
            foreach ($params as $key => $value) {
                $sets .= "$key='$value',";
            }
            $sets = trim($sets, ',');

            $wheres = '';
            foreach ($where as $key => $value) {
                $wheres .= "$key='$value' AND ";
            }
            $wheres = trim($wheres, ' AND ');

            $con = $this->con;
            $sql = utf8_decode("UPDATE $table SET $sets WHERE $wheres LIMIT $limit");
            if (mysqli_query($con, $sql)) {
                return TRUE;
            } else {
                return FALSE;
            }
        }
    }

    public function delete($table, $where, $limit = 1) {
        if (is_array($params)) {
            $wheres = '';
            foreach ($where as $key => $value) {
                $wheres .= "$key='$value' AND ";
            }
            $wheres = trim($wheres, ' AND ');

            $con = $this->con;
            $limit = ($limit == FALSE) ? '' : "LIMIT $limit";
            $sql = utf8_decode("DELETE $table WHERE $wheres $limit");
            if (mysqli_query($con, $sql)) {
                return TRUE;
            } else {
                return FALSE;
            }
        }
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
        return utf8_decode("INSERT INTO $option ($fields) VALUES ($values)");
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
        return utf8_decode("UPDATE $option SET $set WHERE id='$params[id]' LIMIT 1");
    }

    public function sql_delete($option, $params) {
        return "DELETE FROM $option WHERE id='$params[id]' LIMIT 1";
    }

    //http://stackoverflow.com/questions/19083175/generate-random-string-in-php-for-file-name
    public function random_string($length) {
        $key = '';
        $keys = array_merge(range(0, 9), range('a', 'z'));

        for ($i = 0; $i < $length; $i++) {
            $key .= $keys[array_rand($keys)];
        }

        return $key;
    }

    public function save_file($param, $path = NULL) {
        $name = (isset($this->params[$param]['name'])) ? $this->params[$param]['name'] : NULL;
        if ($name == NULL) {
            $data = $this->params[$param];
            if (file_put_contents($path, $data)) {
                return $path;
            } else {
                return FALSE;
            }
        } else {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $name = $this->random_string(48) . ".$ext";
            $path = ($path == NULL) ? "uploads/$name" : $path;

            if (move_uploaded_file($this->params[$param]['tmp_name'], $path)) {
                return $path;
            } else {
                return FALSE;
            }
        }
    }

    private function generate_callback_function($method) {

        switch($method) {
            case 'post':
                $function = function($gastly, $autoResponse = FALSE) {
                    $params = $gastly->params;
                    foreach ($params as $key => $value) {
                        if (in_array($key, $gastly->files)) {
                            $path = $gastly->save_file($key);
                            if ($path != FALSE) {
                                $params[$key] = $path;
                            }
                        }
                    }
                    $code = 'success';
                    $msg = '';
                    $sql = $gastly->sql($gastly->method, $gastly->option, $params);
                    $con = $gastly->get_connect();
                    if (mysqli_query($con, $sql)) {
                        $sql = $gastly->sql('get', $gastly->option, $params);
                        $con = $gastly->get_connect();
                        $res = mysqli_query($con, $sql);
                        if ($res == TRUE && mysqli_num_rows($res) > 0) {
                            $code = 'success';
                            /*$msg = array();
                            while ($row = mysqli_fetch_assoc($res)) {
                                $msg[] = $row;
                            }*/
                        } else {
                            $code = 'error';
                            $msg = 'The id does not exist';
                        }
                    } else {
                        $code = 'error';
                        $msg = $sql;
                    }

                    if ($autoResponse == TRUE) {
                        return $gastly->response($msg, $code);
                    } else {
                        return ($code == 'success') ? TRUE : FALSE;
                    }
                };
                break;
            case 'get':
                $function = function($gastly, $autoResponse = FALSE) {
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

                    if ($autoResponse == TRUE) {
                        return $gastly->response($msg, $code);
                    } else {
                        return ($code == 'success') ? TRUE : FALSE;
                    }
                };
                break;
            case 'put':
                $function = function($gastly, $autoResponse = FALSE) {
                    $params = $gastly->params;
                    foreach ($params as $key => $value) {
                        if (in_array($key, $gastly->files)) {
                            $path = $gastly->save_file($key);
                            if ($path != FALSE) {
                                $params[$key] = $path;
                            }
                        }
                    }
                    $code = 'success';
                    $sql = $gastly->sql('get', $gastly->option, $params);
                    $con = $gastly->get_connect();
                    $res = mysqli_query($con, $sql);
                    if ($res == TRUE && mysqli_num_rows($res) > 0) {
                        $sql = $gastly->sql($gastly->method, $gastly->option, $params);
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

                    if ($autoResponse == TRUE) {
                        return $gastly->response($msg, $code);
                    } else {
                        return ($code == 'success') ? TRUE : FALSE;
                    }
                };
                break;
            case 'delete':
                $function = function($gastly, $autoResponse = FALSE) {
                    $params = $gastly->params;
                    $code = 'success';
                    $msg = '';
                    $sql = $gastly->sql($gastly->method, $gastly->option, $params);
                    $con = $gastly->get_connect();
                    if (!mysqli_query($con, $sql)) {
                        $code = 'error';
                        $msg = $sql;
                    }

                    if ($autoResponse == TRUE) {
                        return $gastly->response($msg, $code);
                    } else {
                        return ($code == 'success') ? TRUE : FALSE;
                    }
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

    public function response($msg = '', $code = 0, $typeText = FALSE) {
        if (!is_numeric($code)) {
            if ($code == 'success') {
                $code = 200;
            } else {
                $code = 500;
            }
        }
        header("HTTP/1.1 $code");
        if ($typeText === FALSE) {
            header('Content-Type: application/json; charset=UTF-8');
            die(json_encode(array('message' => $msg, 'code' => $code)));
        } else {
            header('charset=UTF-8');
            die($msg);
        }
        exit;
    }

    public function responseText($msg = '', $code = 0) {
        $this->response($msg, $code, TRUE);
    }

    function boolResponse($bool) {
        if (is_bool($bool)) {
            $code = ($bool == TRUE) ? 200 : 500;
            $this->response('', $code);
        }
    }

    protected function process($method, $option, $params = NULL) {
        $found = FALSE;

        foreach ($this->conf[$method] as $key) {
            if ($option == $key['option']) {

                if ($params != NULL) {
                    if (is_string($params)) {
                        $params = json_decode($params, TRUE);
                    }
                    $rules = $key['rules'];

                    if (in_array($method, array('put', 'delete'))) {

                        //Lo deje comentado porque no siempre  lleva un parametro id, hay veces que es tipo token o algo asi, puedo dejarlo para despues para declararlo como key
                        /*if (!isset($params['id'])) {
                            $this->response('Falta el id', 400);
                        }
                        if (!isset($rules['id'])) {
                            $rules['id'] = 'int';
                        }*/
                    }

                    /*if (count($rules) != count($params)) {
                        $this->response('El número de parámetros no coincide con los esperados', 401);
                    }*/

                    //Validations must stop at the first error, that way we
                    //mantain the server cool and stop the
                    //client from being a lazy validator
                    foreach ($rules as $field => $type) {
                        $wparam = (isset($params[$field])) ? $params[$field] : NULL;
                        if ($wparam == NULL) {
                            $this->response(array($field => 'Is required'), 402);
                            break;
                        }

                        if (is_array($type) || is_object($type)) {
                            $paramValidators = $type;
                            $validator = $this->validator($field, $wparam, $paramValidators);
                            if ($validator !== TRUE) {
                                $this->response($validator, 500);
                            }
                            break;
                        } else if (is_callable($type)) {
                            $gastly = (object) array($field => $wparam, 'con' => $this->con);
                            if (call_user_func($type, $gastly) === FALSE) {
                                $this->response(array($field => 'Is not valid'), 402);
                            }
                            break;
                        } else {
                            switch($type) {
                                case 'text':
                                    if (!is_string($wparam)) {
                                        $this->response(array($field => 'Gotta be text'), 402);
                                    }
                                    break;
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
                                case 'file':
                                    if (!isset($_FILES)) {
                                        $this->response(array($field => 'Gotta select a file to update it'), 402);
                                    }
                                    break;
                                case 'json':
                                    if (!is_string($wparam) || json_decode($wparam) === NULL) {
                                        $this->response(array($field => 'Gotta be a JSON'), 402);
                                    }
                                    break;
                            }
                        }
                    }
                }

                $this->option = $option;
                $this->params = $params;
                $this->param = (object) $params;

                if ($key['function'] == NULL) {
                    $key['function'] = $this->generate_callback_function($method);
                    $key['function']($this, TRUE);
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

    function validator($field, $value, $rules) {
        $valid = array();
        foreach ($rules as $ruleKey => $ruleValue) {
            switch($ruleKey) {
                case 'type':
                    $type = $ruleValue;
                    if ($this->validateType($type, $value) === FALSE) {
                        return "$field must be a $type";
                    } else {
                        $valid[] = TRUE;
                    }
                    break;
                case 'length':
                    if (strlen($value) !== (int)$ruleValue) {
                        return "$field length is wrong";
                    } else {
                        $valid[] = TRUE;
                    }
                    break;
                case 'function':
                    $function = $ruleValue;
                    if (is_callable($function)) {
                        $gastly = (object) array($field => $value, 'con' => $this->con);
                        if (call_user_func($function, $gastly) === FALSE) {
                            return "$field is not valid";
                        } else {
                            $valid[] = TRUE;
                        }
                    } else {
                        return "$field is not valid";
                    }
                    break;
                default:
                    return FALSE;
            }
        }

        if (count($valid) == 3) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function validateType($type, $value) {
        switch($type) {
            case 'text':
                return is_string($value);
                break;
            case 'int':
                return is_numeric($value);
                break;
            case 'email':
                return (filter_var($value, FILTER_VALIDATE_EMAIL) === FALSE) ? FALSE : TRUE;
                break;
            case 'file':
                return isset($_FILES);
                break;
            case 'json':
                return (!is_string($value) || json_decode($value) === NULL) ? FALSE : TRUE;
                break;
        }

        return FALSE;
    }

    public function runDefault($autoResponse = FALSE) {
        $method = $this->method;
        $defaultFunction = $this->generate_callback_function($method);
        return $defaultFunction($this, $autoResponse);
    }

    public function run() {
        header('Access-Control-Allow-Origin: *');
        if (in_array($_SERVER['REQUEST_METHOD'], array('POST', 'GET', 'PUT', 'DELETE'))) {

            $method = strtolower($_SERVER['REQUEST_METHOD']);
            $_METHOD = ($method == 'post') ? $_POST : $_GET;

            if (in_array($method, array('put', 'delete'))) {
                parse_str(file_get_contents('php://input'), $_METHOD);
            }

            $_METHOD['params'] = (isset($_METHOD['params'])) ? $_METHOD['params'] : NULL;
            if (is_string($_METHOD['params']) && json_decode($_METHOD['params']) !== NULL) {
                $_METHOD['params'] = json_decode($_METHOD['params'], TRUE);
            }
            if (isset($_FILES)) {
                foreach ($_FILES as $key => $val) { //without using $val it returns an array ($key) instead of keyname ($key) :|
                    if (isset($_METHOD['params'][$key]) == NULL) {
                        $_METHOD['params'][$key] = $_FILES[$key];
                        $this->files[] = $key;
                    }
                }
            }

            if (isset($_METHOD['option'])) {
                $this->method = $method;
                $this->process($method, $_METHOD['option'], $_METHOD['params']);
            } else if (isset($_GET['doc'])) {
                $conf = $this->conf;
                $html = '<h1>Options</h1>';
                $html .= '<ul>';
                $options = array();
                foreach ($conf as $method => $method_conf) {
                    foreach ($method_conf as $key => $value) {
                        $option = $value['option'];
                        if (!in_array($option, $options)) {
                            $options[] = $value['option'];
                            $html .= "<li>$option</li>";
                        }
                    }
                }
                $html .= '</ul>';
                $html .= '<h3>'.$options[0].'</h3>';
                $html .= '<table><tr><td>POST</td><td>GET</td><td>PUT</td><td>DELETE</td></tr>';
                //foreach()$conf['post']
                //echo $html .= "<div id='{$options[0]}Post'></div>";
            }
            exit;
        }
    }
}

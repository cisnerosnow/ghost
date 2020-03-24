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
    public $host = NULL;
    public $user = NULL;
    public $pass = NULL;
    public $db_name = NULL;
    public $key = NULL;
    protected $db_type = 'mysql';

    public function connect($host, $user, $pass, $db_name) {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->db_name = $db_name;        
        $this->con = $this->m_connect($host, $user, $pass, $db_name);        
    }

    public function set_db_type($db_type) {
        $this->db_type = $db_type;
    }

    private function m_connect($host, $user, $pass, $db_name) {
        $dbType = $this->db_type;
        if ($dbType == 'mysql') {            
            return mysqli_connect($host, $user, $pass, $db_name);
        } else if ($dbType == 'mssql') {
            // Crear una conexión a MSSQL
            $link = mssql_connect($host, $user, $pass);

            // Seleccionar la base de datos 'php'
            mssql_select_db($db_name, $link);

            return $link;
        } else if ($dbType == 'oracle') {
            //db_name is equal to user
            return oci_connect($db_name, $pass, $host, 'AL32UTF8');            
        }
    }

    public function m_query($query) {
        $con = $this->con;
        $dbType = $this->db_type;
        if ($dbType == 'mysql') {
            return mysqli_query($con, $query);
        } else if ($dbType == 'mssql') {
            return mssql_query($query, $con);
        } else if ($dbType == 'oracle') {            
            $stid = oci_parse($con, $query);
            if ($stid === FALSE) {
                return FALSE;
            }
            if (oci_execute($stid)) {
                return $stid;
            } else {
                                                
                //$e = oci_error($stid);  // For oci_execute errors pass the statement handle
                //var_dump($e);
                //exit;
                return FALSE;
            }
        }
    }

    private function m_num_rows($res) {
        $dbType = $this->db_type;
        if ($dbType == 'mysql') {
            return mysqli_num_rows($res);
        } else if ($dbType == 'mssql') {
            return mssql_num_rows($res);
        } else if ($dbType == 'oracle') {
            if ($res === FALSE) {
                return 0;
            } else {
                return 1;
            }
        }
    }

    private function m_fetch_assoc($res) {
        $dbType = $this->db_type;
        if ($dbType == 'mysql') {
            return mysqli_fetch_assoc($res);
        } else if ($dbType == 'mssql') {
            return mssql_fetch_assoc($res);
        } else if ($dbType == 'oracle') {
            return oci_fetch_assoc($res);
        }
    }
    
    public function m_fetch_array($res) {
        $dbType = $this->db_type;
        if ($dbType == 'mysql') {
            return mysqli_fetch_array($res);
        } else if ($dbType == 'mssql') {
            return mssql_fetch_array($res);
        } else if ($dbType == 'oracle') {
            return oci_fetch_array($res, OCI_ASSOC+OCI_RETURN_NULLS);
        }
    }

    public function get_connect() {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->db_name = $db_name;    
        return $this->m_connect($host, $user, $pass, $db_name);        
    }

    public function getConnect() {
        //return $this->con;
        return $this->get_connect();
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

    public function query($sql = '') {
        if ($sql != '') {
            return $this->m_query($sql);
        } else {
            return FALSE;
        }
    }

    //https://stackoverflow.com/questions/18910814/best-practice-to-generate-random-token-for-forgot-password
    public function createToken($length = 32) {
        if (function_exists('bin2hex') && function_exists('random_bytes')) {
            return substr(bin2hex(random_bytes($length)), 0, $length);
        } else {
            return $this->random_string($length);
        }
    }

    public function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
        return $d && $d->format($format) === $date;
    }

    public function sql_post($option, $params) {
        $fields = '';
        $values = '';
        $dbType = $this->db_type;
        foreach ($params as $field => $value) {
            $fields .= "$field,";
            if ($dbType == 'oracle') {
                if (is_numeric($value)) {
                    $values .= "$value,";
                } else if (strlen($value) == 10 && $this->validateDate($value) === TRUE) {
                    $values .= "TO_DATE('$value','yyyy-mm-dd'),";                    
                } else {
                    $values .= "'$value',";
                }
            } else {
                $values .= "'$value',";
            }
        }
        $fields = trim($fields, ',');
        $values = trim($values, ',');
        $sql = "INSERT INTO $option($fields) VALUES($values)";
        $sql = utf8_decode($sql);
        return $sql;
    }

    public function post($table, $params) {        
        $sql = $this->sql_post($table, $params);
        if ($this->m_query($sql)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function sql_get($table, $fields = NULL, $where = NULL, $limit = 1, $orderBy = NULL) {

        $fields_str = '*';
        if (is_array($fields) && count($fields) > 0) {
            $fields_str = '';
            foreach ($fields as $field) {
                $fields_str .= "$field,";
            }
            $fields_str  = trim($fields_str, ',');
        }

        $wheres = '';
        if (is_array($where) && count($where) > 0) {
            foreach ($where as $key => $value) {
                $wheres .= "$key='$value' AND ";
            }
            $wheres = trim($wheres, ' AND ');
            $wheres = "WHERE $wheres";
        }

        $dbType = $this->db_type;
        if ($dbType == 'mysql') {
            $limit = ($limit == FALSE) ? '' : "LIMIT $limit";
        } else if ($dbType == 'mssql') {
            $limit = ($limit == FALSE) ? '' : "TOP $limit";
        } else if ($dbType == 'oracle') {
            $limit = ($limit == FALSE) ? '' : ''; //i'll apply something for oracle later
        }
        if (is_array($orderBy)) {
            $sql = 'ORDER BY ';
            foreach ($orderBy as $key => $value) {
                $sql .= "$key $value, ";
            }
            $sql = trim($sql, ',');
            $orderBy = $sql;
        } else {
            $orderBy = ($orderBy == NULL) ? '' : "ORDER BY $orderBy";
        }
        if ($dbType == 'mysql') {            
            return "SELECT $fields_str FROM $table $wheres $orderBy $limit"; //now without utf8_decode...i need to implement a way to detect if it's utf8 already to prevent errors
        } else {
            return utf8_decode("SELECT $limit $fields_str FROM $table $wheres $orderBy");
        }
    }

    public function get($table, $fields, $where = NULL, $limit = 1, $orderBy = NULL) {
        $sql = $this->sql_get($table, $fields, $where, $limit, $orderBy);
        if ($sql !== FALSE) {
            $dbType = $this->db_type;

            //Microsoft SQL no tiene 'SET Names utf8' por eso solo checa si es mysql
            if ($dbType == 'mysql') {
                $this->m_query("SET NAMES 'utf8'");
                $res = $this->m_query($sql);
                if ($res !== FALSE) {                    
                    if (mysqli_num_rows($res) > 0) {
                        $arr = $this->queryToArray($res);
                        return $arr;
                    } else {
                        return FALSE;
                    }
                } else {
                    return FALSE;
                }
            } else {                
                $res = $this->m_query($sql);
                if ($res === FALSE) {
                    return FALSE;
                } else {
                    $arr = $this->queryToArray($res);
                    return $arr;                
                }
            }
        } else {
            return FALSE;
        }
    }

    public function queryToArray($res) {
        $results = array();
        $dbType = $this->db_type;
        if ($dbType == 'oracle') {
            if ($this->m_num_rows($res) > 0) {
                while ($row = $this->m_fetch_assoc($res)) {
                    $results[] = $row;
                }
            }
        } else if ($dbType == 'mysql') { 
            //if ($res == TRUE && $this->m_num_rows($res) > 0) { //not really needed
                while ($row = $this->m_fetch_assoc($res)) {

                    //Must find a way to get all data already utf8 encoded
                    foreach ($row as $key => $value) {
                        $row[$key] = is_numeric($value) ? (is_float($value) ? floatval($value) : intval($value)) : utf8_encode($value);
                    }

                    $results[] = $row;
                }
            //}
        } else if ($dbType == 'mssql') {                 
            while ($row = $this->m_fetch_assoc($res)) {
                $results[] = $row;
            }                
        }  

        return $results;
    }

    public function sql_put($table, $params, $where, $limit = 1) {
        if (is_array($params)) {
            $dbType = $this->db_type;
            $sets = '';
            foreach ($params as $key => $value) {
                if ($dbType == 'oracle') {
                    if (is_numeric($value)) {
                        $sets .= "$key=$value,";
                    } else {
                        $sets .= "$key='$value',";
                    }
                } else {
                    $sets .= "$key='$value',";
                }
            }
            $sets = trim($sets, ',');

            $wheres = '';
            foreach ($where as $key => $value) {
                if ($dbType == 'oracle') {
                    if (is_numeric($value)) {
                        $wheres .= "$key=$value AND ";
                    } else { 
                        $wheres .= "$key='$value' AND ";
                    }
                } else { 
                    $wheres .= "$key='$value' AND ";
                }                
            }
            $wheres = trim($wheres, ' AND ');            
            if ($dbType == 'mysql') {
                return utf8_decode("UPDATE $table SET $sets WHERE $wheres LIMIT $limit");
            } else if ($dbType == 'mssql') {
                return utf8_decode("UPDATE TOP($limit) $table SET $sets WHERE $wheres");
            } else if ($dbType == 'oracle') {
                return utf8_decode("UPDATE $table SET $sets WHERE $wheres");
            }
        } else {
            return FALSE;
        }
    }

    public function put($table, $params, $where, $limit = 1) {

        if (! is_array($params)) {
            return FALSE;
        } else {
            $paramsArr = array();
            foreach ($params as $key => $value) {
                $paramsArr[] = $key;
            }
            $res = $this->get($table, $paramsArr, $where, $limit);
            if ($res === FALSE) {                
                return FALSE;
            } else {                
                $sql = $this->sql_put($table, $params, $where, $limit);                
                if ($sql !== FALSE) {                    
                    if ($this->m_query($sql)) {
                        return TRUE;
                    } else {
                        return FALSE;
                    }
                } else {
                    return FALSE;
                }
            }
        }
    }

    public function delete($table, $where, $limit = 1) {
        $sql = $this->sql_delete($table, $where, $limit);
        if ($sql !== FALSE) {            
            if ($this->m_query($sql)) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    public function sql_delete($table, $where, $limit = 1) {
        if (is_array($where)) {
            $dbType = $this->db_type;
            $wheres = '';
            foreach ($where as $key => $value) {
                if ($dbType == 'oracle') {
                    if (is_numeric($value)) {
                        $wheres .= "$key=$value AND ";
                    } else {
                        $wheres .= "$key='$value' AND ";
                    }
                } else {
                    $wheres .= "$key='$value' AND ";
                }
            }
            $wheres = trim($wheres, ' AND ');

            if ($dbType == 'mysql') {
                $limit = ($limit == FALSE) ? '' : "LIMIT $limit";
                return utf8_decode("DELETE FROM $table WHERE $wheres $limit");
            } else if ($dbType == 'mssql') {
                $limit = ($limit == FALSE) ? '' : "TOP($limit)";                
                return utf8_decode("DELETE $limit FROM $table WHERE $wheres");
            } else if ($dbType == 'oracle') {                
                return utf8_decode("DELETE FROM $table WHERE $wheres");
            }
        } else {
            return FALSE;
        }
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

        if (isset($param)) {
            $name = $param['name'];
        } else {
            $name = (isset($this->params[$param]['name'])) ? $this->params[$param]['name'] : NULL;
        }

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

            if (isset($param)) {             
                if (move_uploaded_file($param['tmp_name'], $path)) {
                    return $path;
                } else {
                    return FALSE;
                }
            } else {
                if (move_uploaded_file($this->params[$param]['tmp_name'], $path)) {
                    return $path;
                } else {
                    return FALSE;
                }
            }            
        }
    }

    public function generateCallbackFunction($method) {
        switch($method) {
            case 'post':
                $function = function() {
                    $table = $this->option;
                    $params = $this->params;
                    $res = $this->post($table, $params);
                    if ($res === FALSE) {
                        $this->response('', 500);
                    } else {
                        $this->response('', 200);
                    }
                };
                break;
            case 'get':
                $function = function() {
                    $table = $this->option;
                    $params = $this->params;
                    $res = $this->get($table, NULL, $params);
                    if ($res === FALSE) {
                        $this->response($res, 500);
                    } else {
                        $this->response($res, 200);
                    }
                };
                break;
            case 'put':
                $function = function() {
                    $table = $this->option;
                    $params = $this->params;
                    $key = $this->key;
                    $where = array($key => $params[$key]);
                    unset($params[$key]);
                    $res = $this->put($table, $params, $where);
                    if ($res === FALSE) {
                        $this->response('', 500);
                    } else {
                        $this->response('', 200);
                    }
                };
                break;
            case 'delete':
                $function = function() {
                    $table = $this->option;
                    $params = $this->params;
                    $key = $this->key;
                    $where = array($key => $params[$key]);
                    $res = $this->delete($table, $where);
                    if ($res === FALSE) {
                        $this->response('', 500);
                    } else {
                        $this->response('', 200);
                    }
                };
                break;
        }

        return $function;
    }

    //$rules are not required
    //$function is not required
    public function service($method = NULL, $option = NULL, $rules = NULL, $function = NULL) {
        if (isset($method, $option)) {
            $methods = array('post', 'get', 'put', 'delete');
            if (in_array($method, $methods)) {
                $this->conf[$method][] = array('option' => $option, 'rules' => $rules, 'function' => $function);
            } else if ($method == 'crud') {
                $w_function = $function;
                foreach ($methods as $method) {
                    if ($w_function == NULL) {
                        $this->method = $method;
                        $function = $this->generateCallbackFunction($method);
                    }
                    $this->conf[$method][] = array('option' => $option, 'rules' => $rules, 'function' => $function);
                }
            }
        }
    }

    public function response($msg = '', $code = 200, $typeText = FALSE) {
        if (!is_numeric($code)) {
            if ($code == 'success') {
                $code = 200;
            } else {
                $code = 500;
            }
        }
        if ($msg === FALSE) {
            $code = 500;
            $msg = '';
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

    public function jsonEncode($arr) {
        header('Content-Type: application/json; charset=UTF-8');
        die(json_encode($arr));
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
        $optionFound = FALSE;
        foreach ($this->conf[$method] as $key) {
            if ($option == $key['option']) {
                $optionFound = TRUE;
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

                    if ($rules !== NULL) {
                        //Validations must stop at the first error, that way we
                        //mantain the server cool and stop the
                        //client from being a lazy validator
                        foreach ($rules as $field => $type) {
                            $wparam = (isset($params[$field])) ? $params[$field] : NULL;

                            if ($wparam === NULL) {                         
                                $wparam = (isset($params['params'][$field])) ? $params['params'][$field] : NULL;
                                $params[$field] = $wparam;
                            }

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
                            } else if ($type != 'key' && $type != 'file' && is_callable($type)) { //check if type != 'file' because i got is_callable('file') == TRUE :|
                                $gastly = (object) array($field => $wparam, 'con' => $this->con); //$this->getConnect());
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
                                    case 'array':
                                        if (is_array($wparam) === FALSE) {
                                            $this->response(array($field => 'Gotta be an array'), 402);
                                        }
                                        break;
                                    case 'key':
                                        if (is_numeric($wparam)) {
                                            $this->key = $field;
                                        } else {
                                            $this->response(array($field => 'Gotta be numeric'), 402);
                                        }
                                        break;
                                }
                            }
                        }
                    }
                }

                $this->option = $option;
                $this->params = $params;
                $this->param = (object) $params;

                if ($key['function'] == NULL) {
                    $key['function'] = $this->generateCallbackFunction($method); //$this->generate_callback_function($method);
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

        if ($optionFound == FALSE) {
            $this->response('', 404); //There is no option that match
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
                        $gastly = (object) array($field => $value, 'con' => $this->con); //$this->getConnect());
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
        $defaultFunction = $this->generateCallbackFunction($method);
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

            if (!isset($_METHOD['option']) && isset($_METHOD['params']['option'])) {
                $_METHOD['option'] = $_METHOD['params']['option'];             
            }
            
            if (isset($_METHOD['option'])) {
                $this->method = $method;
                $this->process($method, $_METHOD['option'], $_METHOD['params']);
            } else {
                $this->response('', 500);
            }
            exit;
        }
    }
}

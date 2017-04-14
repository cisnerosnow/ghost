<?php
class Ghost
{
    protected $conf = array('post' => array(), 'get' => array(), 'put' => array(), 'delete' => array());
    protected $debug = FALSE;

    protected function add_conf($type = NULL, $option = NULL, $function = NULL) {
        if (isset($type, $option, $function) && in_array($type, array('post', 'get', 'put', 'delete'))) {
            $this->conf[$type][] = array('option' => $option, 'function' => $function);
        }
    }

    function post($option = NULL, $function = NULL) {
        if (isset($option, $function)) {
            $this->add_conf('post', $option, $function);
        }
    }

    function get($option = NULL, $function = NULL) {
        if (isset($option, $function)) {
            $this->add_conf('get', $option, $function);
        }
    }

    function put($option = NULL, $function = NULL) {
        if (isset($option, $function)) {
            $this->add_conf('put', $option, $function);
        }
    }

    function delete($option = NULL, $function = NULL) {
        if (isset($option, $function)) {
            $this->add_conf('delete', $option, $function);
        }
    }

    /*protected function error($type, $str) {

    }*/

    function run() {

        if (isset($_POST, $_POST['option'], $_POST['params'])) {
            $found = FALSE;
            foreach ($this->conf['post'] as $key) {
                if ($_POST['option'] == $key['option']) {
                    if (is_string($key['function'])) {
                        call_user_func($key['function'], $_POST['params']);
                    } else {
                        $key['function']($_POST['params']);
                    }
                    $found = TRUE;
                    break;
                }
            }

            /*if ($found == FALSE) {
                $this->error('option', $_POST['option']);
            }*/
            exit;
        }

        if (isset($_GET, $_GET['option'], $_GET['params'])) {
            foreach ($this->conf['get'] as $key) {
                if ($_GET['option'] == $key['option']) {
                    if (is_string($key['function'])) {
                        call_user_func($key['function'], $_GET['params']);
                    } else {
                        $key['function']($_GET['params']);
                    }
                    break;
                }
            }
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'PUT') {

            parse_str(file_get_contents('php://input'), $_PUT);

            if (isset($_PUT['option'], $_PUT['params'])) {
                foreach ($this->conf['put'] as $key) {
                    if ($_PUT['option'] == $key['option']) {
                        if (is_string($key['function'])) {
                            call_user_func($key['function'], $_PUT['params']);
                        } else {
                            $key['function']($_PUT['params']);
                        }
                        break;
                    }
                }
            }
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {

            parse_str(file_get_contents('php://input'), $_DELETE);

            if (isset($_DELETE['option'], $_DELETE['params'])) {
                foreach ($this->conf['delete'] as $key) {
                    if ($_DELETE['option'] == $key['option']) {
                        if (is_string($key['function'])) {
                            call_user_func($key['function'], $_DELETE['params']);
                        } else {
                            $key['function']($_DELETE['params']);
                        }
                        break;
                    }
                }
            }
            exit;
        }

        //Actually im working on showing errors:
        /*header('HTTP/1.1 500 Internal Server Booboo');
        header('Content-Type: application/json; charset=UTF-8');
        die(json_encode(array('message' => 'ERROR', 'code' => 1337)));*/
    }
}
?>

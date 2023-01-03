<?php

class CounterHandler {
    var $value = 0;
    var $fileName;

    function __construct() {
    }

    function serve() {
        if (preg_match('@^/([a-z]+)/([0-9]+)/?$@', $_SERVER['PATH_INFO'], $matches)) {
            $method = $matches[1];
            $id = $matches[2];
            $this->fileName = "counter/$id";
            if ($method == 'get') {
                $this->get();
                $this->send(true);
                return;
            } else if ($method == 'pull') {
                $this->get();
                $this->send(false);
                return;
            } else if ($method == 'set') {
                if (preg_match('@^value=([0-9]+)$@', $_SERVER['QUERY_STRING'], $matches)) {
                    $this->set(intval($matches[1]));
                    $this->send(false);
                    return;
                }
            } else if ($method == 'add') {
                if ($this->get() == 0) {
                    $this->send(false);
                } else {
                    $this->add();
                    $this->send(true);
                }
                return;
            }
        }
        http_response_code(400);
    }

    function get() {
        $value = file_get_contents($this->fileName);
        if ($value !== false) {
            $this->value = $value;
        }
        return $this->value;
    }

    function set($value) {
        $this->value = $value;
        if (!is_dir(dirname($this->fileName))) {
            mkdir(dirname($this->fileName));
        }
        file_put_contents($this->fileName, $value);
    }

    function add() {
        $this->set($this->get() + 1);
    }

    function send($cache) {
        http_response_code(200);
        header('Content-Type: application/json');
        if ($cache) {
            header('Cache-Control: private, max-age=86400'); // 1 day
        } else {
            header('Cache-Control: no-store, no-cache');
        }
        echo '{"value":' . $this->value . '}';
    }
}

$counter = new CounterHandler();
$counter->serve();

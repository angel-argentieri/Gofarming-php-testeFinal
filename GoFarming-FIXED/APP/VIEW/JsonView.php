<?php

class JsonView {
    public function send($dados, $status = 200) {
        http_response_code($status);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

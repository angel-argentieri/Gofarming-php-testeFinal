<?php

class JsonView {
    public function send($dados, $status = 200) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
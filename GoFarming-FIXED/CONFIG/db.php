<?php

function config_env($nome, $padrao = '') {
    $v = getenv($nome);
    if ($v === false || $v === '') {
        $v = $_SERVER[$nome] ?? $_ENV[$nome] ?? '';
    }
    return trim($v !== '' ? $v : $padrao);
}

define('PLANT_ID_KEY', config_env('PLANT_ID_KEY', '3qPbDUmVBuoXULHJyHHIX1jrN4TnxPciutckYp8oLuR4TTmhVG'));
define('GEMINI_KEY',   config_env('GEMINI_KEY',   'AQ.Ab8RN6LDIaefqJPutSYEztEbOchVeLVwjIrunLwS1eGn01Jxiw'));
define('GEMINI_MODEL', config_env('GEMINI_MODEL', 'gemini-2.0-flash'));

class Database {
    private $host     = 'localhost';
    private $dbname   = 'GoFarmingBD';
    private $username = 'root';
    private $password = '';
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            $this->pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            error_log('[GoFarming DB] ' . $e->getMessage());
            echo json_encode(['error' => 'Falha na conexão com o banco.']);
            exit;
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
<?php

require_once dirname(__DIR__) . '/MODEL/UsuarioModel.php';
require_once dirname(__DIR__) . '/VIEW/JsonView.php';

class AuthController {
    private $model;
    private $view;

    public function __construct($db) {
        $this->model = new UsuarioModel($db);
        $this->view  = new JsonView();
    }

    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email']) || empty($data['password'])) {
            $this->view->send(['error' => 'Preencha email e senha.'], 400);
            return;
        }

        $usuario = $this->model->buscarPorEmail($data['email']);

        if (!$usuario || !password_verify($data['password'], $usuario['senha'])) {
            $this->view->send(['error' => 'Email ou senha incorretos.'], 401);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['id_usuario'] = (int) $usuario['id'];
        $_SESSION['nome']       = $usuario['nome'];

        $this->view->send(['message' => 'Login realizado.', 'nome' => $usuario['nome']]);
    }

    public function cadastro() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['nome']) || empty($data['email']) || empty($data['password'])) {
            $this->view->send(['error' => 'Preencha todos os campos.'], 400);
            return;
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->view->send(['error' => 'Email inválido.'], 400);
            return;
        }

        if (strlen($data['password']) < 6) {
            $this->view->send(['error' => 'A senha precisa ter ao menos 6 caracteres.'], 400);
            return;
        }

        if ($this->model->buscarPorEmail($data['email'])) {
            $this->view->send(['error' => 'Email já cadastrado.'], 409);
            return;
        }

        $this->model->criar($data['nome'], $data['email'], $data['password']);
        $this->view->send(['message' => 'Cadastro realizado.'], 201);
    }

    public function sessao() {
        if (empty($_SESSION['id_usuario'])) {
            $this->view->send(['autenticado' => false], 401);
            return;
        }
        $this->view->send(['autenticado' => true, 'nome' => $_SESSION['nome'] ?? '']);
    }

    public function logout() {
        $_SESSION = [];
        session_destroy();
        $this->view->send(['message' => 'Logout realizado.']);
    }
}

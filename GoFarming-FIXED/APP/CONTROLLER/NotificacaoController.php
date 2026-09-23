<?php

require_once dirname(__DIR__) . '/MODEL/NotificacaoModel.php';
require_once dirname(__DIR__) . '/MODEL/RegaModel.php';
require_once dirname(__DIR__) . '/VIEW/JsonView.php';

class NotificacaoController {
    private $model;
    private $modelRega;
    private $view;

    public function __construct($db) {
        $this->model     = new NotificacaoModel($db);
        $this->modelRega = new RegaModel($db);
        $this->view      = new JsonView();
    }

    private function sincronizarPendentes($id_usuario) {
        $pendentes = $this->modelRega->buscarPendentesDoUsuario($id_usuario);
        foreach ($pendentes as $rega) {
            if (!$this->model->jaExisteHoje($id_usuario, $rega['id_planta'], 'rega')) {
                $this->model->criar(
                    $id_usuario,
                    'Hora de regar 💧',
                    "{$rega['nome_planta']} está esperando por água hoje.",
                    'rega',
                    $rega['id_planta']
                );
            }
        }
    }

    public function listar() {
        if (empty($_SESSION['id_usuario'])) {
            $this->view->send(['error' => 'Não autenticado.'], 401);
            return;
        }

        $id_usuario = $_SESSION['id_usuario'];
        $this->sincronizarPendentes($id_usuario);

        $this->view->send([
            'notificacoes' => $this->model->listarPorUsuario($id_usuario),
            'nao_lidas'    => $this->model->contarNaoLidas($id_usuario),
        ]);
    }

    public function marcarLida() {
        if (empty($_SESSION['id_usuario'])) {
            $this->view->send(['error' => 'Não autenticado.'], 401);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id'])) {
            $this->view->send(['error' => 'ID da notificação não informado.'], 400);
            return;
        }

        $this->model->marcarComoLida($data['id'], $_SESSION['id_usuario']);
        $this->view->send(['message' => 'Notificação marcada como lida.']);
    }

    public function marcarTodasLidas() {
        if (empty($_SESSION['id_usuario'])) {
            $this->view->send(['error' => 'Não autenticado.'], 401);
            return;
        }

        $this->model->marcarTodasComoLidas($_SESSION['id_usuario']);
        $this->view->send(['message' => 'Todas as notificações foram lidas.']);
    }
}

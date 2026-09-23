<?php

require_once dirname(__DIR__) . '/MODEL/RegaModel.php';
require_once dirname(__DIR__) . '/MODEL/PlantaModel.php';
require_once dirname(__DIR__) . '/VIEW/JsonView.php';

class RegaController {
    private $model;
    private $modelPlanta;
    private $view;

    public function __construct($db) {
        $this->model       = new RegaModel($db);
        $this->modelPlanta = new PlantaModel($db);
        $this->view        = new JsonView();
    }

    private function exigirLogin() {
        if (empty($_SESSION['id_usuario'])) {
            $this->view->send(['error' => 'Não autenticado.'], 401);
            exit;
        }
        return (int) $_SESSION['id_usuario'];
    }

    private function exigirPlantaDoUsuario($id_planta, $id_usuario) {
        $planta = $this->modelPlanta->buscarPorId($id_planta);
        if (!$planta || (int) $planta['id_usuario'] !== $id_usuario) {
            $this->view->send(['error' => 'Planta não encontrada.'], 404);
            exit;
        }
        return $planta;
    }

    public function regar() {
        $id_usuario = $this->exigirLogin();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id_rega'])) {
            $this->view->send(['error' => 'ID da rega não informado.'], 400);
            return;
        }

        if (!$this->model->marcarComoRegada($data['id_rega'], $id_usuario)) {
            $this->view->send(['error' => 'Rega não encontrada ou já registrada.'], 404);
            return;
        }

        $this->view->send(['message' => 'Rega registrada!']);
    }

    public function agenda() {
        $id_usuario = $this->exigirLogin();
        $id_planta  = $_GET['id_planta'] ?? null;

        if (!$id_planta) {
            $this->view->send(['error' => 'id_planta não informado.'], 400);
            return;
        }

        $planta = $this->exigirPlantaDoUsuario($id_planta, $id_usuario);

        $this->view->send([
            'id_planta'        => (int) $planta['id'],
            'vezes_por_semana' => (int) ($planta['vezes_por_semana'] ?? 2),
            'dias_semana'      => RegaModel::normalizarDias($planta['dias_semana'] ?? '1,4'),
            'horario_rega'     => $planta['horario_rega'] ?? '08:00',
            'frequencia_rega'  => $planta['frequencia_rega'],
            'proximas'         => $this->model->proximas($planta['id']),
        ]);
    }

    public function salvarAgenda() {
        $id_usuario = $this->exigirLogin();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id_planta'])) {
            $this->view->send(['error' => 'id_planta não informado.'], 400);
            return;
        }

        $planta = $this->exigirPlantaDoUsuario($data['id_planta'], $id_usuario);

        if (isset($data['dias_semana'])) {
            $dias = RegaModel::normalizarDias($data['dias_semana']);
        } elseif (isset($data['vezes_por_semana'])) {
            $dias = RegaModel::distribuirDias($data['vezes_por_semana']);
        } else {
            $this->view->send(['error' => 'Informe dias_semana ou vezes_por_semana.'], 400);
            return;
        }

        $horario = $data['horario_rega'] ?? null;
        $this->modelPlanta->atualizarAgenda($planta['id'], count($dias), implode(',', $dias), $horario);
        $this->model->gerarAgenda($planta['id'], $dias);

        $this->view->send([
            'message'          => 'Agenda de rega atualizada!',
            'dias_semana'      => $dias,
            'horario_rega'     => $horario,
            'vezes_por_semana' => count($dias),
            'proximas'         => $this->model->proximas($planta['id']),
        ]);
    }
}

<?php

require_once dirname(__DIR__) . '/MODEL/PlantaModel.php';
require_once dirname(__DIR__) . '/MODEL/RegaModel.php';
require_once dirname(__DIR__) . '/SERVICE/GeminiService.php';
require_once dirname(__DIR__) . '/VIEW/JsonView.php';

class IAController {
    private $modelPlanta;
    private $gemini;
    private $view;

    public function __construct($db) {
        $this->modelPlanta = new PlantaModel($db);
        $this->gemini      = new GeminiService();
        $this->view        = new JsonView();
    }

    public function chat() {
        if (empty($_SESSION['id_usuario'])) {
            $this->view->send(['error' => 'Não autenticado.'], 401);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id_planta']) || empty(trim($data['pergunta'] ?? ''))) {
            $this->view->send(['error' => 'Dados incompletos.'], 400);
            return;
        }

        $planta = $this->modelPlanta->buscarPorId($data['id_planta']);

        if (!$planta || (int) $planta['id_usuario'] !== (int) $_SESSION['id_usuario']) {
            $this->view->send(['error' => 'Planta não encontrada.'], 404);
            return;
        }

        $nome    = $planta['nome'] ?? 'Planta';
        $especie = $planta['especie'] ?? '';
        $dias    = $this->nomesDias(RegaModel::normalizarDias($planta['dias_semana'] ?? '1,4'));

        $historico = '';
        foreach (array_slice($data['historico'] ?? [], -6) as $msg) {
            $papel = ($msg['papel'] ?? '') === 'ia' ? 'Você' : 'Usuário';
            $historico .= "{$papel}: " . trim($msg['texto'] ?? '') . "\n";
        }

        $prompt = "Você é um especialista em jardinagem no app GoFarming.\n"
                . "Planta: {$nome} ({$especie}). Dias de rega: {$dias}.\n"
                . ($historico ? "Conversa:\n{$historico}\n" : '')
                . "Responda em português, de forma direta, em no máximo 4 frases.\n"
                . "Pergunta: " . trim($data['pergunta']);

        $r = $this->gemini->gerarTexto($prompt, 600);

        if (isset($r['error'])) {
            error_log('[GoFarming chat] ' . $r['error']);
            $this->view->send(['error' => $r['error']], 502);
            return;
        }

        $this->view->send(['resposta' => $r['texto']]);
    }

    public function obterCuidados() {
        if (empty($_SESSION['id_usuario'])) {
            $this->view->send(['error' => 'Não autenticado.'], 401);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['nome_planta'])) {
            $this->view->send(['error' => 'Nome da planta não informado.'], 400);
            return;
        }

        $prompt = "Planta: {$data['nome_planta']}. Responda APENAS com JSON: "
                . '{"vezes_por_semana": <inteiro 1 a 7>, "resumo": "<frase curta em português>"}';

        $r = $this->gemini->gerarJson($prompt, [
            'type' => 'OBJECT',
            'properties' => [
                'vezes_por_semana' => ['type' => 'INTEGER'],
                'resumo'           => ['type' => 'STRING'],
            ],
            'required' => ['vezes_por_semana', 'resumo'],
        ], 200);

        if (isset($r['error']) || empty($r['dados']['vezes_por_semana'])) {
            $this->view->send([
                'vezes_por_semana' => 2,
                'resumo'           => 'Regar cerca de 2 vezes por semana.',
                'dias_semana'      => RegaModel::distribuirDias(2),
            ]);
            return;
        }

        $vezes = max(1, min(7, (int) $r['dados']['vezes_por_semana']));

        $this->view->send([
            'vezes_por_semana' => $vezes,
            'resumo'           => $r['dados']['resumo'] ?? "Regar {$vezes}x por semana.",
            'dias_semana'      => RegaModel::distribuirDias($vezes),
        ]);
    }

    private function nomesDias(array $dias) {
        $mapa = [1=>'segunda',2=>'terça',3=>'quarta',4=>'quinta',5=>'sexta',6=>'sábado',7=>'domingo'];
        return implode(', ', array_map(fn($d) => $mapa[$d], $dias));
    }
}

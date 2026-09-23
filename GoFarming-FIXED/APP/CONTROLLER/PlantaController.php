<?php

require_once dirname(__DIR__) . '/MODEL/PlantaModel.php';
require_once dirname(__DIR__) . '/MODEL/RegaModel.php';
require_once dirname(__DIR__) . '/SERVICE/GeminiService.php';
require_once dirname(__DIR__) . '/VIEW/JsonView.php';

class PlantaController {
    private $modelPlanta;
    private $modelRega;
    private $gemini;
    private $view;

    public function __construct($db) {
        $this->modelPlanta = new PlantaModel($db);
        $this->modelRega   = new RegaModel($db);
        $this->gemini      = new GeminiService();
        $this->view        = new JsonView();
    }

    private function exigirLogin() {
        if (empty($_SESSION['id_usuario'])) {
            $this->view->send(['error' => 'Não autenticado.'], 401);
            exit;
        }
        return (int) $_SESSION['id_usuario'];
    }

    public function listar() {
        $id_usuario = $this->exigirLogin();
        $plantas = $this->modelPlanta->buscarPorUsuario($id_usuario);

        foreach ($plantas as &$p) {
            $p['dias_semana']      = RegaModel::normalizarDias($p['dias_semana'] ?? '1,4');
            $p['vezes_por_semana'] = (int) ($p['vezes_por_semana'] ?? 2);
        }

        $this->view->send($plantas);
    }

    public function identificar() {
        $this->exigirLogin();
        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data) || empty($data['imagem']) || !is_string($data['imagem'])) {
            $this->view->send(['error' => 'Imagem não recebida.'], 400);
            return;
        }

        $imagem = preg_replace('#^data:image/[a-zA-Z0-9.+-]+;base64,#', '', $data['imagem']);
        $binario = base64_decode($imagem, true);
        if ($binario === false || strlen($binario) < 100) {
            $this->view->send(['error' => 'A imagem está inválida. Tire outra foto ou escolha outro arquivo.'], 400);
            return;
        }
        if (strlen($binario) > 8 * 1024 * 1024) {
            $this->view->send(['error' => 'A imagem é muito grande. Selecione uma foto menor.'], 413);
            return;
        }

        $resposta = $this->chamarPlantId($data['imagem']);

        if (isset($resposta['error'])) {
            $this->view->send(['error' => $resposta['error']], 400);
            return;
        }

        $sugestoes = $resposta['result']['classification']['suggestions'] ?? [];

        if (empty($sugestoes)) {
            $this->view->send(['error' => 'Nenhuma planta identificada na imagem.'], 400);
            return;
        }

        $melhor  = $sugestoes[0];
        $especie = $melhor['name'];
        $nome    = $melhor['details']['common_names'][0]
                ?? $melhor['common_names'][0]
                ?? $especie;

        $cuidados = $this->consultarFrequencia($especie, $nome);

        $this->view->send([
            'nome'             => $nome,
            'especie'          => $especie,
            'confianca'        => round(($melhor['probability'] ?? 0) * 100),
            'frequencia_rega'  => $cuidados['resumo'],
            'vezes_por_semana' => $cuidados['vezes_por_semana'],
            'dias_semana'      => $cuidados['dias_semana'],
            'access_token'     => $resposta['access_token'] ?? null,
        ]);
    }

    public function salvar() {
        $id_usuario = $this->exigirLogin();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['nome']) || empty($data['especie'])) {
            $this->view->send(['error' => 'Dados incompletos.'], 400);
            return;
        }

        if (empty($data['vezes_por_semana'])) {
            $cuidados = $this->consultarFrequencia($data['especie'], $data['nome']);
            $data['frequencia_rega']  = $data['frequencia_rega'] ?? $cuidados['resumo'];
            $data['vezes_por_semana'] = $cuidados['vezes_por_semana'];
            $data['dias_semana']      = $cuidados['dias_semana'];
        }

        $dias = RegaModel::normalizarDias(
            $data['dias_semana'] ?? RegaModel::distribuirDias($data['vezes_por_semana'])
        );

        $horario   = $data['horario_rega'] ?? '08:00';

        $id_planta = $this->modelPlanta->criar(
            $id_usuario,
            $data['nome'],
            $data['especie'],
            $data['foto_url'] ?? null,
            $data['frequencia_rega'] ?? null,
            count($dias),
            implode(',', $dias),
            $data['access_token'] ?? null,
            $horario
        );

        $this->modelRega->gerarAgenda($id_planta, $dias);

        $this->view->send([
            'message'     => 'Planta salva no jardim!',
            'id'          => (int) $id_planta,
            'dias_semana' => $dias,
        ], 201);
    }

    public function deletar() {
        $id_usuario = $this->exigirLogin();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id'])) {
            $this->view->send(['error' => 'ID não informado.'], 400);
            return;
        }

        $planta = $this->modelPlanta->buscarPorId($data['id']);
        if (!$planta || (int) $planta['id_usuario'] !== $id_usuario) {
            $this->view->send(['error' => 'Planta não encontrada.'], 404);
            return;
        }

        $this->modelPlanta->deletar($data['id']);
        $this->view->send(['message' => 'Planta removida.']);
    }

    private function consultarFrequencia($especie, $nome = '') {
        $padrao = [
            'vezes_por_semana' => 2,
            'resumo'           => 'Regar cerca de 2 vezes por semana.',
            'dias_semana'      => RegaModel::distribuirDias(2),
        ];

        if (!$this->gemini->configurada()) return $padrao;

        $prompt = "Você é um especialista em jardinagem no Brasil.\n"
                . "Planta: {$nome} (espécie: {$especie}).\n"
                . 'Responda APENAS com JSON: {"vezes_por_semana": <inteiro de 1 a 7>, "resumo": "<frase curta em português>"}';

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'vezes_por_semana' => ['type' => 'INTEGER'],
                'resumo'           => ['type' => 'STRING'],
            ],
            'required' => ['vezes_por_semana', 'resumo'],
        ];

        $r = $this->gemini->gerarJson($prompt, $schema, 200);

        if (isset($r['error']) || empty($r['dados']['vezes_por_semana'])) {
            error_log('[GoFarming] frequência IA falhou: ' . ($r['error'] ?? 'sem dados'));
            return $padrao;
        }

        $vezes = max(1, min(7, (int) $r['dados']['vezes_por_semana']));

        return [
            'vezes_por_semana' => $vezes,
            'resumo'           => trim($r['dados']['resumo'] ?? '') ?: "Regar {$vezes}x por semana.",
            'dias_semana'      => RegaModel::distribuirDias($vezes),
        ];
    }

    private function chamarPlantId($base64) {
        $apiKey = defined('PLANT_ID_KEY') ? PLANT_ID_KEY : '';
        if ($apiKey === '') {
            return ['error' => 'PLANT_ID_KEY não configurada em CONFIG/db.php.'];
        }

        $url = 'https://api.plant.id/v3/identification?details=common_names&language=pt';

        if (!function_exists('curl_init')) {
            return ['error' => 'A extensão cURL do PHP não está habilitada no XAMPP. Habilite extension=curl no php.ini e reinicie o Apache.'];
        }

        $payload = json_encode(['images' => ['data:image/jpeg;base64,' . $base64]], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return ['error' => 'Não foi possível preparar a imagem para análise.'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Api-Key: ' . $apiKey, 'Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 45,
        ]);

        $raw  = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($erro) return ['error' => 'Falha cURL: ' . $erro];

        $json = json_decode($raw, true);

        if ($http >= 400 || !is_array($json)) {
            $mensagem = $json['message'] ?? $json['error']['message'] ?? 'resposta inválida';
            error_log("[GoFarming Plant.id] HTTP {$http}: {$mensagem}");
            if ($http === 401 || $http === 403) {
                return ['error' => 'A chave da API Plant.id foi recusada. Configure uma chave válida em CONFIG/db.php.'];
            }
            if ($http === 429) {
                return ['error' => 'Limite de consultas da API Plant.id atingido. Tente novamente mais tarde.'];
            }
            if ($http >= 500) {
                return ['error' => 'O serviço de identificação está temporariamente indisponível. Tente novamente.'];
            }
            return ['error' => "Erro Plant.id (HTTP {$http}): {$mensagem}"];
        }

        return $json;
    }
}

<?php

class GeminiService {

    private $apiKey;
    private $model;

    public function __construct() {
        $this->apiKey = defined('GEMINI_KEY') ? trim(GEMINI_KEY) : '';
        $modelConfigurado = defined('GEMINI_MODEL') ? trim(GEMINI_MODEL) : '';
        
        $this->model = ($modelConfigurado === '' || $modelConfigurado === 'gemini-2.0-flash')
            ? 'gemini-3.6-flash'
            : $modelConfigurado;
    }

    public function configurada() {
        return $this->apiKey !== '';
    }

    public function gerarTexto($prompt, $maxTokens = 600) {
        return $this->chamar([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'     => 0.7,
                'maxOutputTokens' => $maxTokens,
            ],
        ]);
    }

    public function gerarJson($prompt, $schema = null, $maxTokens = 600) {
        $cfg = [
            'temperature'      => 0.2,
            'maxOutputTokens'  => $maxTokens,
            'responseMimeType' => 'application/json',
        ];
        if ($schema) $cfg['responseSchema'] = $schema;

        $r = $this->chamar([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => $cfg,
        ]);

        if (isset($r['error'])) return $r;

        $limpo = trim(preg_replace('/^```(?:json)?|```$/m', '', $r['texto']));
        $dados = json_decode($limpo, true);

        if (!is_array($dados)) {
            return ['error' => 'O modelo não devolveu JSON válido.'];
        }
        return ['dados' => $dados];
    }

    private function chamar(array $body) {
        if (!$this->configurada()) {
            return ['error' => 'GEMINI_KEY não configurada em CONFIG/db.php.'];
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . rawurlencode($this->model) . ':generateContent';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $resposta = curl_exec($ch);
        $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro     = curl_error($ch);
        curl_close($ch);

        if ($erro) {
            return ['error' => 'Erro cURL: ' . $erro];
        }

        $json = json_decode($resposta, true);

        if (!is_array($json)) {
            return ['error' => "Resposta inválida da API (HTTP {$http})."];
        }
        if (isset($json['error']['message'])) {
            return ['error' => 'Erro Google API: ' . $json['error']['message']];
        }
        if (!empty($json['promptFeedback']['blockReason'])) {
            return ['error' => 'Resposta bloqueada por segurança.'];
        }
        if (empty($json['candidates'])) {
            return ['error' => 'Sem resposta do modelo.'];
        }

        $texto = '';
        foreach (($json['candidates'][0]['content']['parts'] ?? []) as $p) {
            if (isset($p['text'])) $texto .= $p['text'];
        }

        if (trim($texto) === '') {
            $motivo = $json['candidates'][0]['finishReason'] ?? '';
            return ['error' => 'Sem resposta de texto do modelo' . ($motivo ? " ({$motivo})" : '') . '.'];
        }

        return ['texto' => trim($texto)];
    }
}
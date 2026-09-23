<?php

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=UTF-8');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$base = dirname(__DIR__);

require_once $base . '/CONFIG/db.php';
require_once $base . '/APP/VIEW/JsonView.php';
require_once $base . '/APP/CONTROLLER/AuthController.php';
require_once $base . '/APP/CONTROLLER/PlantaController.php';
require_once $base . '/APP/CONTROLLER/RegaController.php';
require_once $base . '/APP/CONTROLLER/IAController.php';
require_once $base . '/APP/CONTROLLER/NotificacaoController.php';

$view = new JsonView();

$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

if ($script !== '/' && strpos($uri, $script) === 0) {
    $uri = substr($uri, strlen($script));
}
$rota   = trim($uri, '/');
$rota   = preg_replace('#^index\.php/?#', '', $rota);
$rota   = $_GET['rota'] ?? $rota;
$metodo = $_SERVER['REQUEST_METHOD'];

try {
    $db = (new Database())->getConnection();

    $auth        = new AuthController($db);
    $planta      = new PlantaController($db);
    $rega        = new RegaController($db);
    $ia          = new IAController($db);
    $notificacao = new NotificacaoController($db);

    switch ("$metodo $rota") {
        case 'POST login':      $auth->login();     break;
        case 'POST cadastro':   $auth->cadastro();  break;
        case 'POST logout':     $auth->logout();    break;
        case 'GET sessao':      $auth->sessao();    break;

        case 'GET plantas':     $planta->listar();      break;
        case 'POST plantas':    $planta->salvar();      break;
        case 'DELETE plantas':  $planta->deletar();     break;
        case 'POST identificar': $planta->identificar(); break;

        case 'POST regar':      $rega->regar();         break;
        case 'GET agenda':      $rega->agenda();        break;
        case 'POST agenda':     $rega->salvarAgenda();  break;

        case 'POST chat':       $ia->chat();            break;
        case 'POST cuidados':   $ia->obterCuidados();   break;

        case 'GET notificacoes':        $notificacao->listar();          break;
        case 'POST notificacoes/lida':  $notificacao->marcarLida();      break;
        case 'POST notificacoes/lidas': $notificacao->marcarTodasLidas(); break;

        default:
            $view->send(['error' => "Rota não encontrada: {$metodo} /{$rota}"], 404);
    }
} catch (Throwable $e) {
    error_log('[GoFarming] ' . $e->getMessage());
    $view->send(['error' => 'Erro interno no servidor.'], 500);
}

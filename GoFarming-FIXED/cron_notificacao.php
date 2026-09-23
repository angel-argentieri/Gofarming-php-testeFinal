<?php
/**
 * Tarefa diária do GoFarming.
 *
 * Linux/macOS (todo dia às 8h):
 *   0 8 * * * /usr/bin/php /caminho/para/gofarming/cron_notificacao.php >> /var/log/gofarming.log 2>&1
 *
 * Windows / XAMPP — Agendador de Tarefas:
 *   Programa:    C:\xampp\php\php.exe
 *   Argumentos:  C:\xampp\htdocs\gofarming\cron_notificacao.php
 */

require_once __DIR__ . '/CONFIG/db.php';
require_once __DIR__ . '/APP/MODEL/RegaModel.php';
require_once __DIR__ . '/APP/MODEL/PlantaModel.php';
require_once __DIR__ . '/APP/MODEL/NotificacaoModel.php';

$db = (new Database())->getConnection();

$modelRega        = new RegaModel($db);
$modelPlanta      = new PlantaModel($db);
$modelNotificacao = new NotificacaoModel($db);

// Renova agendas com horizonte curto
$stmt = $db->query("
    SELECT p.id, p.dias_semana
    FROM Plantas p
    LEFT JOIN (
        SELECT id_planta, MAX(data_prevista) AS ultima
        FROM Regas GROUP BY id_planta
    ) r ON r.id_planta = p.id
    WHERE r.ultima IS NULL OR r.ultima < DATE_ADD(CURDATE(), INTERVAL 14 DAY)
");

$renovadas = 0;
foreach ($stmt->fetchAll() as $planta) {
    $modelRega->gerarAgenda($planta['id'], $planta['dias_semana']);
    $renovadas++;
}
echo "Agendas renovadas: {$renovadas}\n";

// Cria notificações e envia e-mails
$pendentes = $modelRega->buscarPendentesHoje();

if (empty($pendentes)) {
    echo "Nenhuma rega pendente hoje.\n";
    exit;
}

foreach ($pendentes as $rega) {
    if (!$modelNotificacao->jaExisteHoje($rega['id_usuario'], $rega['id_planta'], 'rega')) {
        $modelNotificacao->criar(
            $rega['id_usuario'],
            'Hora de regar 💧',
            "{$rega['nome_planta']} está esperando por água hoje.",
            'rega',
            $rega['id_planta']
        );
    }
}

$porEmail = [];
foreach ($pendentes as $rega) {
    $porEmail[$rega['email']][] = $rega;
}

foreach ($porEmail as $email => $regas) {
    $nome_usuario  = htmlspecialchars($regas[0]['nome_usuario'], ENT_QUOTES, 'UTF-8');
    $lista_plantas = htmlspecialchars(implode(', ', array_column($regas, 'nome_planta')), ENT_QUOTES, 'UTF-8');

    $assunto  = '=?UTF-8?B?' . base64_encode('GoFarming — suas plantas precisam de água!') . '?=';
    $mensagem = "<html><body style='font-family:sans-serif;background:#0d0d10;color:#fff;padding:30px;'>"
              . "<h2 style='color:#B8A8FF;'>🌱 Hora de regar!</h2>"
              . "<p>Olá, {$nome_usuario}!</p>"
              . "<p>As seguintes plantas ainda não foram regadas hoje:</p>"
              . "<p style='color:#B8A8FF;font-weight:bold;'>{$lista_plantas}</p>"
              . "<p>Acesse o GoFarming e registre a rega.</p>"
              . "</body></html>";

    $cabecalhos = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: GoFarming <noreply@gofarming.com>\r\n";

    $ok = @mail($email, $assunto, $mensagem, $cabecalhos);
    echo ($ok ? 'Email enviado para ' : 'FALHA ao enviar para ') . $email . "\n";
}

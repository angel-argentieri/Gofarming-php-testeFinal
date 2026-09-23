# GoFarming V3 — o que estava quebrado e o que foi corrigido

## 1. A causa-raiz: não existia roteador

`PUBLIC/index.php` apenas dava `require_once` nos controllers e terminava.
Nenhuma classe era instanciada, nenhuma rota era despachada, `$db` nunca era criado.
**Qualquer** chamada do front (`login`, `plantas`, `chat`, `regar`) devolvia uma
resposta vazia. Por isso "nada funcionava" — não era a IA, não era o banco.

Foi escrito um roteador completo mapeando método + rota para o controller certo,
com `try/catch` global e 404 explicando a rota não encontrada.

## 2. O chat da IA estava morto por um `die()` de debug

Primeira linha de `IAController::chat()`:

    die(json_encode(['error' => 'CHEGOU NO IACONTROLLER CERTO']));

Isso matava a função antes de qualquer coisa. Removido.
Além disso o chat agora exige login, valida que a planta é do usuário,
manda histórico das últimas mensagens (contexto) e tem campo de texto livre —
antes só existiam 4 botões fixos.

## 3. A frequência de rega era adivinhada por regex

O código pedia uma frase ao Gemini e depois fazia `preg_match('/\d+/')` para
pegar **o primeiro número da frase**. Ou seja:

| Resposta da IA | Número extraído | Interpretado como |
|---|---|---|
| "Regue a cada **3** dias" | 3 | 3x por semana ❌ (quase o inverso) |
| "**1** a 2 vezes por semana" | 1 | 1x por semana ❌ |
| "Esta planta tropical, nativa do Brasil..." | — | fallback 2 |

Agora o Gemini responde com `responseMimeType: application/json` e um
`responseSchema`, devolvendo `{"vezes_por_semana": 3, "resumo": "..."}`.
Sem adivinhação.

## 4. Não existia agendamento por dia da semana

O schema não tinha onde guardar isso, e `criarProximasRegas` fazia
`round(7 / frequencia)` — o que gera intervalos errados (4x/semana virava
"a cada 2 dias" = 3,5x; 5x, 6x e 7x viravam todos "todo dia").

Adicionado:
- colunas `vezes_por_semana` (TINYINT) e `dias_semana` (VARCHAR, ex: `"1,4"`, ISO 1=Seg…7=Dom)
- `RegaModel::gerarAgenda()` — cria uma rega para cada dia escolhido, 60 dias à frente
- rotas `GET agenda?id_planta=X` e `POST agenda`
- seletor Seg–Dom na tela da planta, com a lista das próximas regas

## 5. Bugs que quebravam o JSON silenciosamente

- `ini_set('display_errors', 1)` + `Content-Type: application/json`: qualquer
  warning do PHP virava HTML no meio do JSON e o `res.json()` do front estourava
  com "Unexpected token <". Agora erros vão para o log.
- `session_start()` era chamado no `index.php` **e** dentro de cada controller →
  `Notice: session already active` impresso antes do JSON. Removido dos controllers.
- `api.js` agora lê como texto e tenta o `JSON.parse`, mostrando o corpo real no
  console quando não é JSON, em vez de morrer sem explicação.

## 6. Segurança

- **A chave do Gemini foi exposta publicamente. Revogue-a** em
  https://aistudio.google.com/apikey e gere outra.
  As chaves agora são lidas de variável de ambiente (`GEMINI_KEY`, `PLANT_ID_KEY`)
  com fallback para o valor em `CONFIG/db.php`.
- `CONFIG/.htaccess` redirecionava para o index em vez de **bloquear** — a pasta
  guarda a senha do banco. Trocado por `Require all denied`.
- `marcarComoRegada` não checava dono: qualquer usuário logado marcava a rega de
  outro chutando o ID. Agora faz `JOIN Plantas` com `id_usuario`.
- `identificar` e `chat` eram públicos (queimavam sua cota de API sem login).
- `CURLOPT_SSL_VERIFYPEER => false` removido. Se der erro de certificado no XAMPP,
  baixe https://curl.se/ca/cacert.pem e aponte `curl.cainfo` no `php.ini`.
- `session_regenerate_id(true)` no login (session fixation).
- Senha mínima de 6 caracteres e validação de e-mail no cadastro.

## 7. Outros

- `PlantaModel::buscarPorUsuario` usava LEFT JOIN que duplicava a planta se
  houvesse mais de uma rega na mesma data. Trocado por subqueries + índice único.
- `dashboard.js` apontava para `css/placeholder.png`, que não existe no projeto.
- `cron_notificacao.php` usava caminhos relativos nos `require` — falhava no cron.
- `teste_gemini.php` removido (arquivo de debug que expunha a chave no navegador).
- `db.php` sincroniza o time zone do MySQL com o do PHP, senão `CURDATE()` e
  `date()` podiam discordar e a rega "de hoje" sumia.

---

## Como aplicar

## Correção do scanner de plantas

O scanner agora reduz fotos da galeria para JPEG antes do envio, valida imagens
inválidas ou grandes demais e apresenta erros claros para chave recusada,
limite de consultas e indisponibilidade do Plant.id. Se o Apache ainda mostrar
HTTP 500, consulte o log de erros do PHP/Apache no XAMPP: erros de conexão com
o banco e falhas fatais ficam registrados lá.

As chaves que estavam gravadas neste arquivo foram removidas. Configure chaves
novas como variáveis de ambiente `PLANT_ID_KEY` e `GEMINI_KEY` ou defina os
valores locais em `CONFIG/db.php`. Como as chaves anteriores estavam dentro do
ZIP, revogue-as no painel dos respectivos serviços e gere novas.

1. **Banco** — rode no MySQL:

       mysql -u root -p < BD/migracao_v3.sql

   (Se for criar do zero, use `BD/script.sql`, que já vem atualizado.)

2. **Chave** — troque `GEMINI_KEY` em `CONFIG/db.php` pela chave NOVA,
   ou defina no Apache: `SetEnv GEMINI_KEY "sua_chave"`.

3. **Teste rápido** das rotas, já logado no navegador:

       GET  /gofarming/PUBLIC/sessao
       GET  /gofarming/PUBLIC/plantas
       GET  /gofarming/PUBLIC/agenda?id_planta=1

4. Se o `mod_rewrite` não estiver ligado, as rotas também funcionam como
   `/gofarming/PUBLIC/index.php?rota=plantas`.

5. Limpe o cache do Service Worker (DevTools → Application → Unregister) depois
   de atualizar os arquivos, senão o navegador serve os JS antigos.

---

# Parte 2 — Notificações aos usuários

O backend de notificações já existia (`NotificacaoController`, `NotificacaoModel`,
tabela `Notificacoes`), mas **nenhuma tela chamava essas rotas**. Não havia sino,
nem badge, nem notificação no celular. Era código morto.

## O que foi implementado

**1. Sino com badge, no dashboard e na tela da planta**
`FRONT/js/notificacoes.js` + estilos no `style.css`. Busca `GET notificacoes`
a cada 60 segundos, mostra o contador de não lidas e abre um painel com a lista.
Clicar numa notificação marca como lida e leva direto para a planta.

**2. Notificação nativa do sistema (app aberto ou em segundo plano na aba)**
Pede permissão na primeira vez que o painel é aberto e dispara
`registration.showNotification()`. Usar o Service Worker em vez de
`new Notification()` é obrigatório: no Chrome Android, com o PWA instalado,
o construtor direto é bloqueado.

Os IDs já avisados ficam em `localStorage`, então o mesmo lembrete não vira
alerta de novo a cada ciclo de 60s.

**3. Clique na notificação** (`notificationclick` no Service Worker)
Foca uma aba já aberta em vez de abrir uma nova a cada toque, e navega para
`planta.html?id=X`.

**4. Badge no ícone do app instalado** via `navigator.setAppBadge()`.

**5. Renovação automática da agenda** (no cron)
A agenda é gerada 60 dias à frente. Sem renovar, dois meses depois de cadastrar
a planta as regas acabariam e o app ficaria mudo — sem erro nenhum, só silêncio.
O cron agora detecta plantas com menos de 14 dias de agenda restante e regenera.

**6. Consulta por usuário**
`sincronizarPendentes()` chamava `buscarPendentesHoje()`, que carregava as regas
de **todos** os usuários do banco e filtrava em PHP. Criei
`buscarPendentesDoUsuario()`. Funcionava com 3 plantas de teste; não funcionaria
com 3 mil.

## Limite importante: app totalmente fechado

O que está implementado cobre: app aberto, aba em segundo plano, e e-mail diário.

Para notificar com o **app completamente fechado** é preciso Web Push com VAPID,
que exige (a) gerar um par de chaves VAPID, (b) guardar o `PushSubscription` de
cada usuário no banco, (c) assinar um JWT e criptografar o payload em `aes128gcm`
no servidor. O handler `push` já está pronto no Service Worker — falta só o
servidor. O caminho mais curto em PHP é a biblioteca `minishlink/web-push`:

    composer require minishlink/web-push

Não implementei isso porque a criptografia do payload não tem como ser validada
sem um ambiente real, e um push mal assinado falha silencioso — seria pior que
não ter. O `iOS` só aceita push em PWA instalado na tela de início (16.4+).

## E-mail no XAMPP

`mail()` não funciona no XAMPP padrão: não há SMTP local, e a função retorna
`false` sem avisar (o código original nem checava o retorno). Opções:

- configurar `[mail function]` no `php.ini` apontando para um SMTP real, ou
- usar PHPMailer com SMTP do Gmail/Brevo (`composer require phpmailer/phpmailer`)

O cron agora imprime `FALHA ao enviar para ...` quando `mail()` retorna false,
em vez de mentir dizendo que enviou.

## Testando sem esperar o dia certo

    -- força uma rega pendente pra hoje
    UPDATE Regas SET data_prevista = CURDATE(), status = 'pendente' WHERE id = 1;

Recarregue o dashboard: em até 60s o sino deve acender. Para testar o cron:

    php cron_notificacao.php

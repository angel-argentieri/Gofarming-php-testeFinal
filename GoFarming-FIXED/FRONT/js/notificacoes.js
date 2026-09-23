const CHAVE_AVISADAS = 'gofarming:notificacoes-avisadas';
const INTERVALO_MS   = 60000;

let notificacoes = [];
let painelAberto = false;

function idsAvisados() {
    try { return new Set(JSON.parse(localStorage.getItem(CHAVE_AVISADAS) || '[]')); }
    catch (e) { return new Set(); }
}

function marcarAvisado(ids) {
    const todos = Array.from(new Set([...idsAvisados(), ...ids])).slice(-100);
    localStorage.setItem(CHAVE_AVISADAS, JSON.stringify(todos));
}

async function pedirPermissaoNotificacao() {
    if (!('Notification' in window)) return false;
    if (Notification.permission === 'granted') return true;
    if (Notification.permission === 'denied')  return false;
    return (await Notification.requestPermission()) === 'granted';
}

async function dispararNotificacaoNativa(n) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;

    const opcoes = {
        body: n.mensagem,
        icon: 'css/Logo.png',
        badge: 'css/Logo.png',
        tag: 'gofarming-' + n.id,
        data: { id_planta: n.id_planta },
        requireInteraction: false,
    };

    const reg = await navigator.serviceWorker?.getRegistration();
    if (reg && reg.showNotification) {
        await reg.showNotification(n.titulo, opcoes);
    } else {
        new Notification(n.titulo, opcoes);
    }
}

async function carregarNotificacoes({ alertar = true } = {}) {
    const res = await get('notificacoes');
    if (res.error) return;

    notificacoes = res.notificacoes || [];
    atualizarBadge(res.nao_lidas || 0);

    if (painelAberto) renderizarPainel();
    if (!alertar) return;

    const jaAvisadas = idsAvisados();
    const novas = notificacoes.filter(n => !+n.lida && !jaAvisadas.has(n.id));
    if (!novas.length) return;

    for (const n of novas) await dispararNotificacaoNativa(n);
    marcarAvisado(novas.map(n => n.id));
}

function atualizarBadge(quantidade) {
    const badge = document.getElementById('sino-badge');
    if (!badge) return;
    badge.textContent = quantidade > 9 ? '9+' : quantidade;
    badge.style.display = quantidade > 0 ? 'flex' : 'none';
    if (navigator.setAppBadge) {
        quantidade > 0 ? navigator.setAppBadge(quantidade) : navigator.clearAppBadge();
    }
}

function tempoRelativo(iso) {
    const diff = (Date.now() - new Date(iso.replace(' ', 'T')).getTime()) / 1000;
    if (diff < 60)    return 'agora';
    if (diff < 3600)  return Math.floor(diff / 60) + ' min';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    return Math.floor(diff / 86400) + 'd';
}

function renderizarPainel() {
    const corpo = document.getElementById('painel-corpo');
    if (!corpo) return;

    if (!notificacoes.length) {
        corpo.innerHTML = '<div class="notif-vazio">Nenhuma notificação por aqui.</div>';
        return;
    }

    corpo.innerHTML = notificacoes.map(n => `
        <div class="notif-item ${+n.lida ? '' : 'nao-lida'}"
             onclick="abrirNotificacao(${n.id}, ${n.id_planta || 'null'})">
            <div class="notif-topo">
                <strong>${n.titulo}</strong>
                <span class="notif-tempo">${tempoRelativo(n.criada_em)}</span>
            </div>
            <div class="notif-msg">${n.mensagem}</div>
        </div>
    `).join('');
}

function alternarPainel() {
    const painel = document.getElementById('painel-notificacoes');
    if (!painel) return;
    painelAberto = !painelAberto;
    painel.style.display = painelAberto ? 'flex' : 'none';
    if (painelAberto) {
        renderizarPainel();
        pedirPermissaoNotificacao();
    }
}

async function abrirNotificacao(id, id_planta) {
    await post('notificacoes/lida', { id });
    if (id_planta) {
        window.location.href = 'planta.html?id=' + id_planta;
        return;
    }
    carregarNotificacoes({ alertar: false });
}

async function marcarTodasLidas() {
    await post('notificacoes/lidas', {});
    carregarNotificacoes({ alertar: false });
}

document.addEventListener('click', e => {
    const painel = document.getElementById('painel-notificacoes');
    const sino   = document.getElementById('sino');
    if (painelAberto && painel && !painel.contains(e.target) && !sino.contains(e.target)) {
        alternarPainel();
    }
});

document.addEventListener('visibilitychange', () => {
    if (!document.hidden) carregarNotificacoes();
});

carregarNotificacoes();
setInterval(carregarNotificacoes, INTERVALO_MS);

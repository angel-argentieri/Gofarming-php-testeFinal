const urlParams = new URLSearchParams(window.location.search);
const plantaId = urlParams.get('id');

if (!plantaId) {
    window.location.href = 'dashboard.html';
}

let diasSelecionados = [];

async function carregarDetalhes() {
    const plantas = await get('plantas');
    if (!plantas || plantas.error) return;

    const planta = plantas.find(p => p.id == plantaId);
    if (!planta) {
        alert('Planta não encontrada');
        window.location.href = 'dashboard.html';
        return;
    }

    document.getElementById('nome').textContent = planta.nome || 'Sem nome';
    document.getElementById('especie').textContent = planta.especie || '';
    document.getElementById('frequencia').textContent = planta.frequencia_rega || 'Regar conforme necessário';
    
    const fotoElem = document.getElementById('foto');
    if (planta.foto_url) {
        fotoElem.src = planta.foto_url.startsWith('data:') || planta.foto_url.startsWith('http') 
            ? planta.foto_url 
            : 'data:image/jpeg;base64,' + planta.foto_url;
    } else {
        fotoElem.src = 'css/Logo.png';
    }

    carregarAgenda();
}

async function carregarAgenda() {
    const res = await get('agenda?planta_id=' + plantaId);
    if (!res || res.error) return;

    diasSelecionados = (res.dias || []).map(Number);
    if (res.horario) {
        document.getElementById('horario-rega').value = res.horario;
    }

    atualizarBotoesDias();
    renderizarProximasRegas(res.proximas || []);
}

function atualizarBotoesDias() {
    document.querySelectorAll('.dia-btn').forEach(btn => {
        const dia = Number(btn.getAttribute('data-dia'));
        if (diasSelecionados.includes(dia)) {
            btn.classList.add('ativo');
        } else {
            btn.classList.remove('ativo');
        }
    });
}

function alternarDia(dia) {
    const diaNum = Number(dia);
    const pos = diasSelecionados.indexOf(diaNum);
    if (pos > -1) {
        diasSelecionados.splice(pos, 1);
    } else {
        diasSelecionados.push(diaNum);
    }
    atualizarBotoesDias();
}

async function salvarAgenda() {
    const horario = document.getElementById('horario-rega').value;
    const res = await post('agenda', {
        planta_id: plantaId,
        dias: diasSelecionados,
        horario: horario
    });

    if (res && res.success) {
        alert('Agenda salva com sucesso!');
        carregarAgenda();
    } else {
        alert(res.error || 'Erro ao salvar agenda');
    }
}

function renderizarProximasRegas(proximas) {
    const container = document.getElementById('proximas-regas');
    if (!proximas || !proximas.length) {
        container.innerHTML = '<div class="muted" style="font-size:12px;padding:8px 0;">Nenhuma rega agendada.</div>';
        return;
    }

    container.innerHTML = proximas.map(item => `
        <div class="linha-rega">
            <span>${item.data_formatada} (${item.dia_semana})</span>
            <span class="${item.status === 'realizada' ? 'ok' : 'pend'}">
                ${item.status === 'realizada' ? '✓ Regado' : '💧 Pendente'}
            </span>
        </div>
    `).join('');
}

async function regar() {
    const res = await post('regar', { planta_id: plantaId });
    if (res && res.success) {
        alert('Rega registrada com sucesso!');
        location.reload();
    } else {
        alert(res.error || 'Erro ao registrar rega');
    }
}

async function removerPlanta() {
    if (!confirm('Deseja realmente remover esta planta do seu jardim?')) return;
    const res = await del('plantas', { id: plantaId });
    if (res && res.success) {
        window.location.href = 'dashboard.html';
    } else {
        alert(res.error || 'Erro ao remover planta');
    }
}

async function perguntarIA(pergunta) {
    const chatDiv = document.getElementById('chat-mensagens');
    chatDiv.style.display = 'flex';

    const userMsg = document.createElement('div');
    userMsg.className = 'msg-usuario';
    userMsg.textContent = pergunta;
    chatDiv.appendChild(userMsg);

    const iaMsg = document.createElement('div');
    iaMsg.className = 'msg-ia';
    iaMsg.textContent = 'Pensando...';
    chatDiv.appendChild(iaMsg);
    chatDiv.scrollTop = chatDiv.scrollHeight;

    const res = await post('chat', { planta_id: plantaId, mensagem: pergunta });

    if (res && res.resposta) {
        iaMsg.textContent = res.resposta;
    } else {
        iaMsg.className = 'msg-ia msg-erro';
        iaMsg.textContent = res.error || 'Não foi possível obter resposta no momento.';
    }
    chatDiv.scrollTop = chatDiv.scrollHeight;
}

function enviarPergunta(e) {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const txt = input.value.trim();
    if (!txt) return;
    input.value = '';
    perguntarIA(txt);
}

carregarDetalhes();
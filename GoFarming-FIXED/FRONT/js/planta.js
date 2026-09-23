const params     = new URLSearchParams(window.location.search);
const id_planta  = params.get('id');

let rega_id         = null;
let diasSelecionados = [];
let historicoChat   = [];

const NOMES_DIAS = { 1:'Seg', 2:'Ter', 3:'Qua', 4:'Qui', 5:'Sex', 6:'Sáb', 7:'Dom' };

function formatarFoto(foto) {
    if (!foto) return 'css/Logo.png';
    if (foto.startsWith('data:image') || foto.startsWith('http')) return foto;
    return 'data:image/jpeg;base64,' + foto;
}

function formatarData(iso) {
    const [a, m, d] = iso.split('-');
    const data   = new Date(+a, +m - 1, +d);
    const semana = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][data.getDay()];
    return `${semana}, ${d}/${m}`;
}

async function carregar() {
    const plantas = await get('plantas');

    if (plantas.error || !Array.isArray(plantas)) {
        toast(plantas.error || 'Não foi possível carregar.');
        return;
    }

    const planta = plantas.find(p => p.id == id_planta);

    if (!planta) {
        window.location.href = 'dashboard.html';
        return;
    }

    document.title = planta.nome + ' — GoFarming';
    document.getElementById('nome').textContent      = planta.nome;
    document.getElementById('especie').textContent   = planta.especie || '';
    document.getElementById('frequencia').textContent = planta.frequencia_rega || 'Não definida';
    document.getElementById('foto').src              = formatarFoto(planta.foto_url);

    if (planta.rega_hoje === 'pendente') {
        rega_id = planta.rega_id;
        document.getElementById('rega-hoje').style.display = 'block';
    }

    await carregarAgenda();
}

async function carregarAgenda() {
    const ag = await get('agenda?id_planta=' + encodeURIComponent(id_planta));

    if (ag.error) {
        toast(ag.error);
        return;
    }

    diasSelecionados = ag.dias_semana || [];

    if (ag.horario_rega) {
        document.getElementById('horario-rega').value = ag.horario_rega;
    }

    renderizarDias();

    const lista = document.getElementById('proximas-regas');
    lista.innerHTML = (ag.proximas || []).length
        ? ag.proximas.map(r => `
            <div class="linha-rega">
                <span>${formatarData(r.data_prevista)}</span>
                <span class="${r.status === 'concluida' ? 'ok' : 'pend'}">
                    ${r.status === 'concluida' ? '✓ regada' : '💧 pendente'}
                </span>
            </div>`).join('')
        : '<div class="muted" style="font-size:13px;">Nenhuma rega agendada.</div>';
}

function renderizarDias() {
    document.querySelectorAll('.dia-btn').forEach(btn => {
        btn.classList.toggle('ativo', diasSelecionados.includes(+btn.dataset.dia));
    });
}

function alternarDia(dia) {
    dia = +dia;
    diasSelecionados = diasSelecionados.includes(dia)
        ? diasSelecionados.filter(d => d !== dia)
        : [...diasSelecionados, dia].sort((a, b) => a - b);
    renderizarDias();
}

async function salvarAgenda() {
    if (!diasSelecionados.length) {
        toast('Escolha pelo menos um dia.');
        return;
    }

    const horario = document.getElementById('horario-rega').value || '08:00';

    const res = await post('agenda', {
        id_planta,
        dias_semana:  diasSelecionados,
        horario_rega: horario,
    });

    if (res.error) {
        toast(res.error);
        return;
    }

    toast('Agenda salva! 🗓️');
    await carregarAgenda();
}

async function regar() {
    const res = await post('regar', { id_rega: rega_id });

    if (res.error) {
        toast(res.error);
        return;
    }

    document.getElementById('rega-hoje').style.display = 'none';
    toast('Rega registrada! 💧');
    carregarAgenda();
}

function adicionarMensagem(papel, texto) {
    const box = document.getElementById('chat-mensagens');
    const div = document.createElement('div');
    div.className = papel === 'ia' ? 'msg-ia' : 'msg-usuario';
    div.textContent = texto;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
    return div;
}

async function perguntarIA(pergunta) {
    pergunta = (pergunta || '').trim();
    if (!pergunta) return;

    const input = document.getElementById('chat-input');
    if (input) input.value = '';

    document.getElementById('chat-mensagens').style.display = 'flex';
    adicionarMensagem('usuario', pergunta);

    const carregando = adicionarMensagem('ia', 'Pensando...');

    const res = await post('chat', {
        id_planta,
        pergunta,
        historico: historicoChat.slice(-6),
    });

    if (res.error) {
        carregando.textContent = res.error;
        carregando.classList.add('msg-erro');
        return;
    }

    carregando.textContent = res.resposta;
    historicoChat.push({ papel: 'usuario', texto: pergunta });
    historicoChat.push({ papel: 'ia',      texto: res.resposta });
}

function enviarPergunta(e) {
    if (e) e.preventDefault();
    perguntarIA(document.getElementById('chat-input').value);
}

async function removerPlanta() {
    if (!confirm('Remover esta planta do jardim?')) return;

    const res = await del('plantas', { id: id_planta });

    if (res.error) {
        toast(res.error);
        return;
    }

    window.location.href = 'dashboard.html';
}

carregar();

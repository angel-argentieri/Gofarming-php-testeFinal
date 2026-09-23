let dadosPlanta = null;
let fotoBase64  = null;
let diasScan    = [];

navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    .then(stream => {
        document.getElementById('video').srcObject = stream;
    })
    .catch(() => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.capture = 'environment';
        input.style = 'display:none';
        input.onchange = e => processarArquivo(e.target.files[0]);
        document.body.appendChild(input);
        document.getElementById('btn-captura').onclick = () => input.click();
    });

function capturar() {
    const video  = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    if (!video.videoWidth || !video.videoHeight) {
        toast('A câmera ainda não está pronta. Tente novamente em alguns segundos.');
        return;
    }
    const escala = Math.min(1, 1600 / Math.max(video.videoWidth, video.videoHeight));
    canvas.width  = Math.round(video.videoWidth * escala);
    canvas.height = Math.round(video.videoHeight * escala);
    canvas.getContext('2d').drawImage(video, 0, 0);
    fotoBase64 = canvas.toDataURL('image/jpeg', 0.75).split(',')[1];
    mostrarPreview(canvas.toDataURL('image/jpeg', 0.75));
    analisar();
}

function selecionarArquivo(e) {
    if (e.target.files && e.target.files[0]) processarArquivo(e.target.files[0]);
}

function processarArquivo(file) {
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        toast('Selecione um arquivo de imagem.');
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        const imagem = new Image();
        imagem.onload = () => {
            const limite = 1600;
            const escala = Math.min(1, limite / Math.max(imagem.width, imagem.height));
            const canvas = document.getElementById('canvas');
            canvas.width = Math.round(imagem.width * escala);
            canvas.height = Math.round(imagem.height * escala);
            canvas.getContext('2d').drawImage(imagem, 0, 0, canvas.width, canvas.height);
            const jpeg = canvas.toDataURL('image/jpeg', 0.82);
            fotoBase64 = jpeg.split(',')[1];
            mostrarPreview(jpeg);
            analisar();
        };
        imagem.onerror = () => toast('Não foi possível abrir essa imagem.');
        imagem.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function mostrarPreview(src) {
    ['laser','btn-captura','btn-galeria'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    document.getElementById('video').style.display = 'none';
    const preview = document.getElementById('preview');
    preview.src = src;
    preview.style.display = 'block';
}

async function analisar() {
    document.getElementById('analisando').style.display = 'block';
    let res;
    try {
        res = await post('identificar', { imagem: fotoBase64 });
    } catch (e) {
        res = { error: 'Falha ao enviar a imagem. Verifique sua conexão e tente novamente.' };
    } finally {
        document.getElementById('analisando').style.display = 'none';
    }

    if (res.error) {
        toast(res.error);
        reiniciar();
        return;
    }

    dadosPlanta = res;
    dadosPlanta.foto_base64 = fotoBase64;
    diasScan = Array.isArray(res.dias_semana) ? [...res.dias_semana] : [1, 4];

    document.getElementById('foto-resultado').src      = 'data:image/jpeg;base64,' + fotoBase64;
    document.getElementById('nome-planta').textContent      = res.nome;
    document.getElementById('especie-planta').textContent   = res.especie;
    document.getElementById('confianca-planta').textContent = res.confianca + '% de certeza';
    document.getElementById('frequencia-planta').textContent = res.frequencia_rega;

    renderizarDiasScan();
    document.getElementById('resultado').style.display = 'block';
}

function renderizarDiasScan() {
    document.querySelectorAll('#dias-scan .dia-btn').forEach(btn => {
        btn.classList.toggle('ativo', diasScan.includes(+btn.dataset.dia));
    });
}

function alternarDiaScan(dia) {
    dia = +dia;
    diasScan = diasScan.includes(dia)
        ? diasScan.filter(d => d !== dia)
        : [...diasScan, dia].sort((a, b) => a - b);
    renderizarDiasScan();
}

async function salvarNoJardim() {
    if (!diasScan.length) {
        toast('Escolha pelo menos um dia de rega.');
        return;
    }

    const horario = document.getElementById('horario-scan').value || '08:00';

    const res = await post('plantas', {
        nome:             dadosPlanta.nome,
        especie:          dadosPlanta.especie,
        foto_url:         'data:image/jpeg;base64,' + dadosPlanta.foto_base64,
        frequencia_rega:  dadosPlanta.frequencia_rega,
        vezes_por_semana: diasScan.length,
        dias_semana:      diasScan,
        horario_rega:     horario,
        access_token:     dadosPlanta.access_token,
    });

    if (res.error) {
        toast(res.error);
        return;
    }

    toast('Planta adicionada ao jardim!');
    setTimeout(() => window.location.href = 'dashboard.html', 1500);
}

function reiniciar() {
    dadosPlanta = null;
    fotoBase64  = null;
    diasScan    = [];
    document.getElementById('resultado').style.display = 'none';
    document.getElementById('preview').style.display = 'none';
    document.getElementById('video').style.display = 'block';
    document.getElementById('laser').style.display = 'block';
    document.getElementById('btn-captura').style.display = 'flex';
    const btnGaleria = document.getElementById('btn-galeria');
    if (btnGaleria) btnGaleria.style.display = 'flex';
}

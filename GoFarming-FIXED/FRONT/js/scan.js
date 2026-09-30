let stream = null;
let imagemCapturadaBase64 = null;
let diasSelecionados = [1, 3, 5]; // Padrão: Seg, Qua, Sex

document.addEventListener('DOMContentLoaded', () => {
    iniciarCamera();
});

// Inicializa a câmera do dispositivo
async function iniciarCamera() {
    const video = document.getElementById('video');
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });
        video.srcObject = stream;
    } catch (erro) {
        console.warn('Câmera indisponível ou permissão negada:', erro);
    }
}

// Interrompe a transmissão da câmera
function pararCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
}

// Tira foto via canvas
function capturar() {
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const preview = document.getElementById('preview');

    if (!video.srcObject) {
        alert('A câmera não está ativa. Tente enviar uma imagem da galeria.');
        return;
    }

    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 640;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    imagemCapturadaBase64 = canvas.toDataURL('image/jpeg');
    
    // Exibe preview
    preview.src = imagemCapturadaBase64;
    video.style.display = 'none';
    preview.style.display = 'block';

    processarImagem(imagemCapturadaBase64);
}

// Carrega foto da galeria
function selecionarArquivo(event) {
    const arquivo = event.target.files[0];
    if (!arquivo) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        imagemCapturadaBase64 = e.target.result;
        
        const video = document.getElementById('video');
        const preview = document.getElementById('preview');

        pararCamera();
        video.style.display = 'none';
        preview.src = imagemCapturadaBase64;
        preview.style.display = 'block';

        processarImagem(imagemCapturadaBase64);
    };
    reader.readAsDataURL(arquivo);
}

// Processa e simula/envia a imagem para análise de IA
function processarImagem(base64Data) {
    document.querySelector('.capture-wrapper').style.display = 'none';
    document.getElementById('analisando').style.display = 'block';
    document.getElementById('resultado').style.display = 'none';

    // Simulação de resposta da IA (Substitua por chamada API real se houver)
    setTimeout(() => {
        exibirResultado({
            nome: 'Costela-de-Adão',
            especie: 'Monstera deliciosa',
            confianca: '98% de precisão',
            frequencia: 'Regar 2 a 3 vezes por semana',
            diasSugeridos: [1, 3, 5]
        });
    }, 1800);
}

// Exibe o resultado na tela
function exibirResultado(dados) {
    document.getElementById('analisando').style.display = 'none';
    document.getElementById('resultado').style.display = 'block';

    document.getElementById('foto-resultado').src = imagemCapturadaBase64;
    document.getElementById('nome-planta').textContent = dados.nome;
    document.getElementById('especie-planta').textContent = dados.especie;
    document.getElementById('confianca-planta').textContent = dados.confianca;
    document.getElementById('frequencia-planta').textContent = dados.frequencia;

    diasSelecionados = dados.diasSugeridos;
    atualizarBotoesDias();
}

// Alterna seleção dos dias da semana
function alternarDiaScan(dia) {
    const index = diasSelecionados.indexOf(dia);
    if (index > -1) {
        diasSelecionados.splice(index, 1);
    } else {
        diasSelecionados.push(dia);
    }
    atualizarBotoesDias();
}

function atualizarBotoesDias() {
    const botoes = document.querySelectorAll('#dias-scan .dia-btn');
    botoes.forEach(btn => {
        const diaNum = parseInt(btn.getAttribute('data-dia'), 10);
        if (diasSelecionados.includes(diaNum)) {
            btn.classList.add('ativo');
        } else {
            btn.classList.remove('ativo');
        }
    });
}

// Salva e redireciona para o dashboard
function salvarNoJardim() {
    const horario = document.getElementById('horario-scan').value;
    const nome = document.getElementById('nome-planta').textContent;

    const novaPlanta = {
        nome: nome,
        especie: document.getElementById('especie-planta').textContent,
        imagem: imagemCapturadaBase64,
        dias: diasSelecionados,
        horario: horario
    };

    // Salva no localStorage para persistência local
    let jardim = JSON.parse(localStorage.getItem('jardim_gofarming') || '[]');
    jardim.push(novaPlanta);
    localStorage.setItem('jardim_gofarming', JSON.stringify(jardim));

    alert(`${nome} adicionada ao seu jardim com sucesso!`);
    window.location.href = 'dashboard.html';
}

// Reinicia o scanner
function reiniciar() {
    pararCamera();
    imagemCapturadaBase64 = null;

    document.getElementById('preview').style.display = 'none';
    document.getElementById('video').style.display = 'block';
    document.querySelector('.capture-wrapper').style.display = 'flex';
    document.getElementById('resultado').style.display = 'none';
    document.getElementById('analisando').style.display = 'none';

    iniciarCamera();
}
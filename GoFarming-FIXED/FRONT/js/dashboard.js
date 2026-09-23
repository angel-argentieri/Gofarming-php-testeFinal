document.getElementById('saudacao').textContent = 'Olá, ' + (localStorage.getItem('nome') || 'Jardineiro');

// Função para formatar a imagem corretamente (Base64, URL ou Placeholder)
function formatarFoto(foto) {
    if (!foto) return 'css/Logo.png';
    
    // Se já começa com "data:image" ou "http", usa a foto direto
    if (foto.startsWith('data:image') || foto.startsWith('http')) {
        return foto;
    }
    
    // Se for Base64 puro vindo do banco, adiciona o cabeçalho necessário
    return 'data:image/jpeg;base64,' + foto;
}

function rotuloProxima(iso) {
    if (!iso) return '';
    const hoje = new Date(); hoje.setHours(0,0,0,0);
    const [a,m,d] = iso.split('-');
    const alvo = new Date(+a, +m-1, +d);
    const dias = Math.round((alvo - hoje) / 86400000);
    if (dias <= 0) return 'hoje';
    if (dias === 1) return 'amanhã';
    return `em ${dias} dias`;
}

async function carregarPlantas() {
    const plantas = await get('plantas');
    const lista = document.getElementById('lista');

    if (plantas && plantas.error) {
        lista.innerHTML = `<p style="text-align:center;color:#ff9d9d;padding:40px 0;">${plantas.error}</p>`;
        return;
    }

    if (!plantas || !plantas.length) {
        lista.innerHTML = `
            <div style="text-align:center; padding: 50px 0; color: #8b8b9e;">
                <div style="font-size: 48px; margin-bottom: 12px;">🌿</div>
                <p style="font-weight: 600; font-size: 16px; color: #ffffff;">Seu jardim está vazio.</p>
                <p style="margin-top: 6px; font-size: 13px;">Toque em 📷 para escanear uma planta.</p>
            </div>
        `;
        return;
    }

    lista.innerHTML = plantas.map(p => {
        const pendente = p.rega_hoje === 'pendente';
        const fotoSrc = formatarFoto(p.foto_url);

        return `
            <div class="card" onclick="window.location.href='planta.html?id=${p.id}'" style="cursor:pointer;">
                <div class="row">
                    <img src="${fotoSrc}" 
                         onerror="this.onerror=null; this.src='css/Logo.png';"
                         class="plant-img">
                    
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:700; font-size:16px; color:#ffffff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            ${p.nome}
                        </div>
                        <div class="muted" style="margin-top: 2px;">${p.especie || ''}</div>
                        
                        <span class="badge ${pendente ? 'badge-pendente' : 'badge-ok'}">
                            ${pendente ? '💧 Regar hoje' : '✓ Em dia'}
                        </span>
                        <div class="muted" style="margin-top:4px;font-size:11px;">
                            ${p.proxima_rega && !pendente ? 'Próxima rega ' + rotuloProxima(p.proxima_rega) : ''}
                        </div>
                    </div>

                    <svg width="20" height="20" fill="none" stroke="#8b8b9e" stroke-width="1.8" viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </div>
            </div>
        `;
    }).join('');
}

carregarPlantas();
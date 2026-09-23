// Calcula a raiz pelo local de FRONT para funcionar em qualquer pasta do XAMPP.
const APP_ROOT = location.pathname.replace(/\/FRONT\/.*$/, '');
const BASE = APP_ROOT + '/PUBLIC';

async function requisitar(rota, metodo, dados) {
    const opcoes = { method: metodo, credentials: 'include' };

    if (dados !== undefined) {
        // Se os dados forem FormData (envio de imagens/arquivos), não converta em JSON 
        // e deixe o navegador configurar o Content-Type automaticamente
        if (dados instanceof FormData) {
            opcoes.body = dados;
        } else {
            opcoes.headers = { 'Content-Type': 'application/json' };
            opcoes.body = JSON.stringify(dados);
        }
    }

    let res;
    try {
        res = await fetch(BASE + '/' + rota, opcoes);
    } catch (e) {
        return { error: 'Sem conexão com o servidor.' };
    }

    const texto = await res.text();

    let json;
    try {
        json = JSON.parse(texto);
    } catch (e) {
        console.error('Resposta não-JSON de /' + rota + ':', texto);
        return { error: 'Resposta inválida do servidor (veja o console).' };
    }

    if (res.status === 401 && !location.pathname.endsWith('login.html')) {
        location.href = 'login.html';
    }

    return json;
}

const post = (rota, dados) => requisitar(rota, 'POST', dados);
const get  = (rota)        => requisitar(rota, 'GET');
const del  = (rota, dados) => requisitar(rota, 'DELETE', dados);

function toast(msg) {
    let t = document.getElementById('toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'toast';
        t.className = 'toast';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add('visivel');
    setTimeout(() => t.classList.remove('visivel'), 2500);
}

if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        const regs = await navigator.serviceWorker.getRegistrations();
        for (const reg of regs) {
            if (reg.scope !== new URL(APP_ROOT + '/', location.origin).href) {
                await reg.unregister();
            }
        }

        // O service worker está na raiz do app; registrá-lo dali permite o escopo /gofarming/.
        const swPath = APP_ROOT + '/service-worker.js';

        navigator.serviceWorker.register(swPath, { scope: APP_ROOT + '/' })
            .then(reg => console.log('SW ativo:', reg.scope))
            .catch(err => console.error('Erro SW:', err));
    });
}

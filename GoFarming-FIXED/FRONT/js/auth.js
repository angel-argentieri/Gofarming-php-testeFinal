async function logar() {
    const email = document.getElementById('email').value;
    const senha = document.getElementById('senha').value;

    const res = await post('login', { email, password: senha });

    if (res.error) {
        toast(res.error);
        return;
    }

    localStorage.setItem('nome', res.nome);
    window.location.href = 'dashboard.html';
}

async function cadastrar() {
    const nome = document.getElementById('nome').value;
    const email = document.getElementById('email').value;
    const senha = document.getElementById('senha').value;

    const res = await post('cadastro', { nome, email, password: senha });

    if (res.error) {
        toast(res.error);
        return;
    }

    toast('Conta criada! Faça login.');
    setTimeout(() => window.location.href = 'login.html', 1500);
}

async function sair() {
    await post('logout', {});
    localStorage.clear();
    window.location.href = 'login.html';
}

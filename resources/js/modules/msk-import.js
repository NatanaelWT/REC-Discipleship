const wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

const token = () => {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
};

const run = async (root) => {
    const statusUrl = root.dataset.statusUrl;
    const batchUrl = root.dataset.batchUrl;
    const csrf = root.dataset.csrfToken;
    const message = root.querySelector('[data-msk-import-message]');
    const progress = root.querySelector('[data-msk-import-progress]');
    if (!statusUrl || !batchUrl || !csrf || !message || !progress) return;

    let batchToken = token();
    const render = (state) => {
        const percent = Math.max(0, Math.min(100, Number(state.progress || 0)));
        progress.value = percent;
        if (state.status === 'completed') {
            message.textContent = `Selesai: ${state.inserted || 0} ditambah, ${state.updated || 0} diperbarui. Muat ulang untuk melihat hasil.`;
            root.classList.remove('info', 'danger');
            root.classList.add('success');
        } else if (state.status === 'failed') {
            const first = Array.isArray(state.errors) ? state.errors[0] : null;
            message.textContent = first?.message || 'Import gagal. Periksa file lalu coba kembali.';
            root.classList.remove('info', 'success');
            root.classList.add('danger');
        } else if (state.busy) {
            message.textContent = 'Batch lain masih berjalan; status akan diperiksa kembali.';
        } else {
            message.textContent = `${state.processed || 0} dari ${state.total || 0} baris diproses (${percent}%).`;
        }
    };

    let state = await fetch(statusUrl, {
        credentials: 'same-origin', headers: { Accept: 'application/json' },
    }).then((response) => {
        if (!response.ok) throw new Error('status');
        return response.json();
    });
    render(state);

    while (!state.terminal) {
        if (state.busy) await wait(1000);
        try {
            const response = await fetch(batchUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ action: 'import_pemuridan_excel', batch_token: batchToken }),
            });
            if (!response.ok) throw new Error('batch');
            state = await response.json();
            batchToken = token();
            render(state);
        } catch (error) {
            message.textContent = 'Koneksi terputus; batch yang sama akan dicoba lagi.';
            await wait(1500);
        }
    }
};

document.querySelectorAll('[data-msk-import-job]').forEach((root) => {
    run(root).catch(() => {
        const message = root.querySelector('[data-msk-import-message]');
        if (message) message.textContent = 'Status import tidak dapat dimuat. Muat ulang halaman untuk melanjutkan.';
    });
});

let deferredPrompt;

function getInstallButton() {
    return document.getElementById('pwa-install-btn');
}

function getInstallWrap() {
    return document.querySelector('[data-pwa-install-wrap]');
}

function getInstallTip() {
    return document.getElementById('pwa-install-tip');
}

function isEmbeddedInstallMenu() {
    return Boolean(getInstallWrap());
}

function isStandaloneMode() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function showInstallButton() {
    const installBtn = getInstallButton();
    const installWrap = getInstallWrap();
    const installTip = getInstallTip();
    if (!installBtn || isStandaloneMode()) {
        return;
    }

    if (installWrap) {
        installWrap.style.display = 'grid';
        if (installTip) {
            installTip.textContent = 'Siap dipasang ke layar utama.';
        }
    }
    installBtn.style.display = 'inline-flex';
    installBtn.disabled = false;
    installBtn.textContent = installWrap ? 'Install App' : installBtn.textContent;
}

function hideInstallButton() {
    const installBtn = getInstallButton();
    const installWrap = getInstallWrap();
    const installTip = getInstallTip();
    if (!installBtn) {
        return;
    }

    if (installWrap) {
        installWrap.style.display = 'grid';
        installBtn.style.display = 'inline-flex';
        installBtn.textContent = 'Install App';
        installBtn.disabled = false;
        if (installTip && !isStandaloneMode()) {
            installTip.textContent = 'Pasang portal staf ke layar utama.';
        }
        return;
    }

    installBtn.style.display = 'none';
    if (installTip) {
        installTip.textContent = '';
    }
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    showInstallButton();
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    const installBtn = getInstallButton();
    const installWrap = getInstallWrap();
    const installTip = getInstallTip();

        if (installWrap && installBtn) {
            installWrap.style.display = 'grid';
            installBtn.style.display = 'inline-flex';
            installBtn.disabled = true;
            installBtn.textContent = 'Sudah Terpasang';
            if (installTip) {
                installTip.textContent = 'Portal staf sudah terpasang.';
            }
            return;
        }

    hideInstallButton();
});

document.addEventListener('DOMContentLoaded', () => {
    const installBtn = getInstallButton();
    if (!installBtn) {
        return;
    }

    if (isStandaloneMode()) {
        const installWrap = getInstallWrap();
        const installTip = getInstallTip();
        if (installWrap) {
            installWrap.style.display = 'grid';
            installBtn.style.display = 'inline-flex';
            installBtn.disabled = true;
            installBtn.textContent = 'Sudah Terpasang';
            if (installTip) {
                installTip.textContent = 'Portal staf sudah terpasang.';
            }
        } else {
            hideInstallButton();
        }
        return;
    }

    hideInstallButton();

    installBtn.addEventListener('click', async () => {
        const installTip = getInstallTip();
        if (!deferredPrompt) {
            if (installTip) {
                installTip.textContent = 'Install belum tersedia di browser ini.';
            } else if (isEmbeddedInstallMenu()) {
                window.alert('Install belum tersedia di browser ini.');
            }
            return;
        }

        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        hideInstallButton();
    });
});

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const toggle = document.getElementById('menu-toggle');
    const setMenu = (open) => {
        sidebar?.classList.toggle('is-open', open);
        backdrop?.classList.toggle('is-open', open);
        toggle?.setAttribute('aria-expanded', String(open));
    };
    toggle?.addEventListener('click', () => setMenu(!sidebar.classList.contains('is-open')));
    backdrop?.addEventListener('click', () => setMenu(false));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') setMenu(false); });
    document.querySelectorAll('[data-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });
    document.querySelectorAll('[data-password]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.password);
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
            button.querySelector('i').className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
    document.querySelectorAll('[data-vehicle-form] input[type="text"], [data-vehicle-form] textarea').forEach(input => {
        const uppercase = () => {
            const value = input.value;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const direction = input.selectionDirection;
            const upper = value.toUpperCase();
            if (value === upper) return;
            input.value = upper;
            input.setSelectionRange(value.slice(0, start).toUpperCase().length, value.slice(0, end).toUpperCase().length, direction);
        };
        uppercase();
        input.addEventListener('input', event => { if (!event.isComposing) uppercase(); });
        input.addEventListener('compositionend', uppercase);
    });
    document.querySelectorAll('[data-count-for]').forEach(counter => {
        const input = document.getElementById(counter.dataset.countFor);
        input?.addEventListener('input', () => { counter.textContent = Array.from(input.value).length; });
    });
    document.querySelectorAll('.app-toast').forEach(toast => {
        if (window.bootstrap) new bootstrap.Toast(toast, { delay: 6500 }).show();
    });

    const documentElement = document.querySelector('.vehicle-document');
    const fitMessage = document.getElementById('fit-message');
    const printButtons = document.querySelectorAll('[data-print]');
    let fits = true;
    let printAssetsReady = false;
    const fitDocument = () => {
        if (!documentElement) return true;
        if (!printAssetsReady) return false;
        const content = documentElement.querySelector('.document-content');
        const rootStyle = getComputedStyle(document.documentElement);
        const base = parseFloat(rootStyle.getPropertyValue('--doc-font')) || 6;
        const baseLine = parseFloat(rootStyle.getPropertyValue('--doc-line')) || 1.2;
        const scale = parseFloat(rootStyle.getPropertyValue('--doc-scale')) || 1;
        // Tidak memotong teks. Turunkan font secara bertahap hanya bila konten melampaui ruang.
        let size = base;
        let line = baseLine;
        documentElement.style.fontSize = `${size}pt`;
        documentElement.style.setProperty('--doc-line', String(line));
        const columns = content.querySelector('.document-columns');
        const approval = content.querySelector('.document-approval');
        const reference = content.querySelector('.internal-reference-code');
        const paper = documentElement.closest('.paper-sheet');
        const outsideSlot = () => {
            if (!paper || !paper.dataset.printPosition) return false;
            const style = getComputedStyle(document.documentElement);
            const slotLeft = parseFloat(style.getPropertyValue('--slot-left')) * 96 / 25.4;
            const slotWidth = parseFloat(style.getPropertyValue('--slot-width')) * 96 / 25.4;
            const page = paper.getBoundingClientRect();
            // Periksa isian juga: tanggal di bawah boleh melewati bidang dasar, tetap di dalam kolom.
            return [documentElement, ...content.querySelectorAll('.document-value dd, .internal-approval, .internal-reference-code img, .internal-reference-code figcaption, .internal-reference-code > span')].some(element => {
                const rect = element.getBoundingClientRect();
                return rect.left < page.left + slotLeft - 1 || rect.right > page.left + slotLeft + slotWidth + 1
                    || rect.top < page.top - 1 || rect.bottom > page.bottom + 1;
            });
        };
        const layoutTop = element => {
            let top = 0;
            while (element && element !== content) {
                top += element.offsetTop;
                element = element.offsetParent;
            }
            return top;
        };
        const overflows = () => outsideSlot() || content.scrollHeight > content.clientHeight + 1
            || content.scrollWidth > content.clientWidth + 1
            || columns.scrollHeight > columns.clientHeight + 1
            || columns.scrollWidth > columns.clientWidth + 1
            || (approval && reference && layoutTop(approval) + approval.offsetHeight > layoutTop(reference) - 4)
            || [...content.querySelectorAll('.document-owner, .document-column')].some(column => column.scrollHeight > column.clientHeight + 1);
        // Rapatkan jarak baris lebih dulu agar ukuran huruf yang dipilih tetap terbaca.
        while (overflows() && line > 1.1) {
            line = Math.max(1.1, Math.round((line - 0.05) * 100) / 100);
            documentElement.style.setProperty('--doc-line', String(line));
        }
        while (overflows() && size > 5.5) {
            size = Math.max(5.5, Math.round((size - 0.1) * 10) / 10);
            documentElement.style.fontSize = `${size}pt`;
        }
        fits = !overflows();
        document.body.classList.toggle('print-overflow', !fits);
        if (fitMessage) {
            fitMessage.hidden = fits && size === base && line === baseLine;
            fitMessage.textContent = !fits
                ? 'Konten melebihi area kertas atau kolom. Cetak dinonaktifkan agar data tidak terpotong. Ringkas isian yang terlalu panjang sebelum mencetak.'
                : `Font cetak disesuaikan menjadi ${(size * scale).toFixed(1)} pt${line < baseLine ? ' dan jarak baris dirapatkan' : ''} agar seluruh data muat. Preview dan cetakan memakai ukuran yang sama.`;
        }
        printButtons.forEach(button => { button.disabled = !fits; });
        return fits;
    };
    // Pakai gambar sumber utuh dan tunggu font serta gambar selesai didekode sebelum cetak.
    const ready = Promise.all([
        document.fonts ? document.fonts.ready : Promise.resolve(),
        ...[...(documentElement?.querySelectorAll('img') || [])].map(image => image.decode())
    ]);
    printButtons.forEach(button => { button.disabled = true; });
    ready.then(() => {
        printAssetsReady = true;
        fitDocument();
    }).catch(() => {
        fits = false;
        document.body.classList.add('print-overflow');
        printButtons.forEach(button => { button.disabled = true; });
        if (fitMessage) {
            fitMessage.hidden = false;
            fitMessage.textContent = 'Font atau gambar dokumen belum berhasil dimuat. Muat ulang halaman sebelum mencetak.';
        }
    });
    window.addEventListener('beforeprint', fitDocument);
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
    printButtons.forEach(button => button.addEventListener('click', async () => {
        try { await ready; } catch { return; }
        if (fitDocument()) window.print();
    }));
});

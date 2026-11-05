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
        // Ukuran huruf dan jarak baris tetap sama pada preview serta setiap cetakan.
        documentElement.style.fontSize = `${base}pt`;
        documentElement.style.setProperty('--doc-line', String(baseLine));
        // Ukur kertas yang terlihat, termasuk setelah pemeriksaan sebelumnya gagal.
        document.body.classList.remove('print-overflow');
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
        const firstSpecification = content.querySelector('.document-primary .document-value dd');
        const ownerOverlapsSpecifications = firstSpecification
            ? [...content.querySelectorAll('.document-owner .document-value dd')].some(value =>
                layoutTop(value) + value.offsetHeight > layoutTop(firstSpecification) - 2)
            : false;
        // Tanggal dan kode boleh melewati lebar bidang dasar; outsideSlot tetap memeriksa batas kertas.
        const overflows = () => ownerOverlapsSpecifications || outsideSlot() || content.scrollHeight > content.clientHeight + 1
            || columns.scrollHeight > columns.clientHeight + 1
            || (approval && reference && layoutTop(approval) + approval.offsetHeight > layoutTop(reference) - 4)
            || [...content.querySelectorAll('.document-owner, .document-column')].some(column => column.scrollHeight > column.clientHeight + 1);
        fits = !overflows();
        document.body.classList.toggle('print-overflow', !fits);
        if (fitMessage) {
            fitMessage.hidden = fits;
            fitMessage.textContent = fits ? '' : ownerOverlapsSpecifications
                ? 'Nama atau alamat terlalu panjang dan bertumpuk dengan data kendaraan. Ringkas isian tersebut sebelum mencetak; ukuran tulisan dan posisi cetak tetap.'
                : 'Konten melebihi area kertas atau kolom. Ukuran tulisan dan jarak baris tetap. Ringkas isian yang terlalu panjang sebelum mencetak.';
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

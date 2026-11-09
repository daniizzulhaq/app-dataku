// Node 22+, Chrome lokal, MySQL aktif. Semua data uji berada dalam database sementara.
import { spawn, spawnSync } from 'node:child_process';
import { mkdir, writeFile, rm } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join, resolve, sep } from 'node:path';
import { tmpdir } from 'node:os';
import { randomBytes } from 'node:crypto';
import assert from 'node:assert/strict';
import WebSocket from './vendor/ws/wrapper.mjs';

const root = resolve(import.meta.dirname, '..');
const artifacts = join(root, 'tests', 'artifacts');
const php = process.env.PHP_BIN || 'C:\\xampp\\php\\php.exe';
const suffix = randomBytes(6).toString('hex');
const testDb = `db_kendaraan_print_test_${suffix}`;
const storage = join(artifacts, `storage-${suffix}`);
const env = { ...process.env, DB_NAME: testDb, KENDARA_STORAGE: storage };
const origin = 'http://127.0.0.1:8026';
let server, chrome, cdp, dbCreated = false, jar = '', pass = 0;
const browserProfile = join(tmpdir(), `kendara-qa-${suffix}`);
const delay = ms => new Promise(r => setTimeout(r, ms));
const report = name => { pass++; console.log(`PASS ${name}`); };
function phpCall(file, ...args) {
    const result = spawnSync(php, [file, ...args], { cwd: root, env, encoding: 'utf8', windowsHide: true });
    if (result.status !== 0) throw new Error(`PHP helper failed: ${result.stderr}`);
    return result.stdout;
}
async function request(path, data) {
    const response = await fetch(`${origin}/${path}`, { redirect: 'manual', headers: { ...(jar ? { Cookie: jar } : {}), ...(data ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}) }, ...(data ? { method: 'POST', body: new URLSearchParams(data) } : {}) });
    const cookie = response.headers.get('set-cookie');
    if (cookie) jar = cookie.split(';')[0];
    return { status: response.status, body: await response.text(), location: response.headers.get('location'), headers: response.headers };
}
function csrf(html) {
    const token = html.match(/name="csrf" value="([a-f0-9]+)"/);
    assert.ok(token, 'CSRF field missing');
    return token[1];
}
const snapshot = () => JSON.parse(phpCall('tests/db-helper.php', 'snapshot'));
const fixture = {
    nomor_polisi: 'B 1234 UJI', nama_pemilik: 'Pemilik Fiktif <UJI>', nik: '0012345678901234',
    alamat: 'Jalan Contoh No. 12, Kelurahan Fiktif, Jakarta Selatan',
    merek: 'Toyota', tipe: 'Avanza 1.5 G', jenis: 'Mobil penumpang', model: 'Minibus', tahun_pembuatan: '2024', tahun_perakitan: '2024',
    isi_silinder: '1496', nomor_rangka: '000VIN-UJI-123456789', nomor_mesin: '000MESIN-UJI-98765', warna: 'Putih', bahan_bakar: 'Bensin', warna_tnkb: 'Putih',
    tahun_registrasi: '2024', nomor_bpkb: '000BPKB-UJI', nomor_urut: '000001', kode_lokasi: 'JKT-01', tanggal_penerbitan: '2026-10-01', berlaku_sampai: '2031-11-04',
    nomor_referensi: 'INT/UJI/0001', teks_atas_qr: 'Teliti ulang <UJI>', wilayah: 'Gresik', tanggal_wilayah: '2026-01-08', wilayah_pengesahan: 'JAWA TIMUR', pengesahan_internal: 'Pemeriksa Fiktif', jabatan_pemeriksa: 'Admin <UJI>', nrp_pemeriksa: '00012345', catatan: 'Data fiktif untuk pengujian. <script>alert(1)</script>'
};

class CDP {
    constructor(socket) {
        this.socket = socket; this.id = 0; this.pending = new Map(); this.listeners = new Map();
        socket.addEventListener('message', ({ data }) => {
            const event = JSON.parse(data);
            if (event.id) {
                const pending = this.pending.get(event.id);
                if (pending) { this.pending.delete(event.id); event.error ? pending.reject(new Error(JSON.stringify(event.error))) : pending.resolve(event.result); }
            } else {
                for (const callback of this.listeners.get(event.method) || []) callback(event.params);
            }
        });
    }
    call(method, params = {}) {
        const id = ++this.id;
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => { this.pending.delete(id); reject(new Error(`CDP timeout: ${method}`)); }, 30000);
            this.pending.set(id, { resolve: value => { clearTimeout(timer); resolve(value); }, reject: error => { clearTimeout(timer); reject(error); } });
            this.socket.send(JSON.stringify({ id, method, params }));
        });
    }
    async evaluate(expression) {
        const result = await this.call('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    }
    async navigate(path) {
        const loaded = new Promise(resolve => this.listeners.set('Page.loadEventFired', [resolve]));
        await this.call('Page.navigate', { url: `${origin}/${path}` });
        await Promise.race([loaded, delay(12000).then(() => { throw new Error('Page load timeout'); })]);
        await this.evaluate('document.fonts.ready.then(() => true)');
        await delay(100);
    }
    async screenshot(name) {
        const metrics = await this.call('Page.getLayoutMetrics');
        const size = metrics.cssContentSize;
        const shot = await this.call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width: size.width, height: size.height, scale: 1 } });
        await writeFile(join(artifacts, name), Buffer.from(shot.data, 'base64'));
    }
}

try {
    await mkdir(artifacts, { recursive: true });
    await mkdir(storage, { recursive: true });
    phpCall('tests/db-helper.php', 'create'); dbCreated = true;
    phpCall('database/migrate.php'); phpCall('database/migrate.php');
    const token = phpCall('setup.php').match(/\n([a-f0-9]{64})\r?\n/)[1];
    server = spawn(php, ['-S', '127.0.0.1:8026', 'router.php'], { cwd: root, env, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    let serverLog = ''; server.stderr.on('data', b => { serverLog += b; });
    await delay(1200);
    let response = await request('index.php'); assert.equal(response.status, 303); assert.equal(response.location, 'login.php'); report('Anonymous dashboard/print protection');
    for (const page of ['print.php?id=1', 'preview.php?id=1', 'print-settings.php', 'print-test.php']) assert.equal((await request(page)).status, 303);
    for (const path of ['database/kendaraan.sql', 'config/database.php', 'storage/setup-token.json', 'tests/db-helper.php', '.git/config']) assert.equal((await request(path)).status, 404);
    report('Private project paths blocked');
    response = await request('setup.php');
    const password = 'Testing-Only-' + randomBytes(10).toString('hex');
    response = await request('setup.php', { csrf: csrf(response.body), setup_token: token, name: 'Admin Pengujian', username: 'qa_admin', password, password_confirm: password });
    assert.equal(response.status, 303); assert.equal(snapshot().users.length, 1); assert.notEqual(snapshot().users[0].password, password);
    assert.equal((await request('setup.php')).status, 403); report('Secure first-admin setup, password hash, setup locked');
    response = await request('login.php'); response = await request('login.php', { csrf: csrf(response.body), username: 'qa_admin', password });
    assert.equal(response.status, 303); response = await request('index.php'); assert.equal(response.status, 200); report('Login and dashboard');
    const add = await request('tambah.php'); const authCsrf = csrf(add.body);
    assert.ok(add.body.indexOf('id="wilayah"') < add.body.indexOf('id="wilayah_pengesahan"'));
    assert.equal((await request('simpan.php', { ...fixture, csrf: authCsrf, wilayah: 'x'.repeat(61) })).status, 422);
    assert.equal((await request('simpan.php', { ...fixture, csrf: authCsrf, tanggal_wilayah: '2026-02-30' })).status, 422);
    assert.match(add.body, /id="teks_atas_qr"[^>]*value="TELITI ULANG"/);
    assert.equal((await request('simpan.php', { ...fixture, csrf: authCsrf, teks_atas_qr: 'x'.repeat(61) })).status, 422);
    assert.equal((await request('simpan.php', { ...fixture, csrf: 'bad' })).status, 419);
    assert.equal((await request('simpan.php', { ...fixture, csrf: authCsrf, nik: '123' })).status, 422);
    assert.equal((await request('simpan.php', { ...fixture, csrf: authCsrf, jabatan_pemeriksa: 'x'.repeat(101) })).status, 422);
    assert.equal((await request('simpan.php', { ...fixture, csrf: authCsrf, berlaku_sampai: '2020-01-01' })).status, 422);
    report('Backend validation and CSRF rejection');
    response = await request('simpan.php', { ...fixture, csrf: authCsrf, action: 'preview' }); assert.equal(response.status, 303); assert.ok(response.location.startsWith('preview.php?id='));
    const id = snapshot().vehicles[0].id;
    assert.equal(snapshot().vehicles[0].nik, fixture.nik); assert.equal(snapshot().vehicles[0].nomor_rangka, fixture.nomor_rangka); assert.equal(snapshot().vehicles[0].nomor_mesin, fixture.nomor_mesin);
    for (const [field, value] of Object.entries(fixture)) {
        assert.equal(String(snapshot().vehicles[0][field]), value.toUpperCase(), `${field} must be stored uppercase`);
    }
    assert.equal(snapshot().vehicles[0].jabatan_pemeriksa, fixture.jabatan_pemeriksa.toUpperCase());
    assert.equal(snapshot().vehicles[0].wilayah_pengesahan, fixture.wilayah_pengesahan);
    response = await request('edit.php?id=' + id);
    assert.match(response.body, /id="wilayah"[^>]*value="GRESIK"/);
    assert.match(response.body, /id="tanggal_wilayah"[^>]*value="2026-01-08"/);
    assert.match(response.body, /id="wilayah_pengesahan"[^>]*value="JAWA TIMUR"/);
    assert.equal(snapshot().vehicles[0].nrp_pemeriksa, fixture.nrp_pemeriksa);
    report('Vehicle persisted; leading zeros preserved');
    assert.equal((await request('simpan.php', { ...fixture, csrf: authCsrf })).status, 422); report('Duplicate plate rejected');
    response = await request('detail.php?id=' + id); assert.ok(response.body.includes('&lt;SCRIPT&gt;ALERT(1)&lt;/SCRIPT&gt;')); assert.ok(!response.body.includes('<SCRIPT>ALERT(1)</SCRIPT>')); report('Uppercase personal data escaped against XSS');
    response = await request('index.php?nomor_polisi=B+1234&nama_pemilik=Fiktif'); assert.ok(response.body.includes('B 1234 UJI'));
    response = await request('index.php?nomor_polisi=%27+OR+1%3D1'); assert.ok(response.body.includes('Data belum ditemukan')); report('Plate/owner search and SQL injection resistance');
    const version = snapshot().vehicles[0].updated_at;
    response = await request('update.php', { ...fixture, csrf: authCsrf, id, version, warna: 'Biru', teks_atas_qr: 'Periksa kembali <UJI>' }); assert.equal(response.status, 303);
    response = await request('update.php', { ...fixture, csrf: authCsrf, id, version, warna: 'Merah' }); assert.equal(response.status, 422);
    response = await request('print.php?id=' + id); assert.ok(response.body.includes('BIRU')); assert.ok(response.body.includes('<figcaption>PERIKSA KEMBALI &lt;UJI&gt;</figcaption>')); report('Edit, stale-write protection, latest data and editable QR caption in print');
    response = await request('edit.php?id=' + id);
    assert.match(response.body, /id="teks_atas_qr"[^>]*value="PERIKSA KEMBALI &lt;UJI&gt;"/);
    for (let n = 2; n <= 12; n++) assert.equal((await request('simpan.php', { ...fixture, nomor_polisi: `B ${1000 + n} UJI`, nama_pemilik: `Pemilik Fiktif ${n}`, csrf: authCsrf })).status, 303);
    response = await request('index.php?page=2'); assert.ok(response.body.includes('11–12')); report('Pagination over twelve records');
    const savedSettings = JSON.stringify(snapshot().settings);
    response = await request('print-settings.php');
    assert.equal(response.status, 200);
    assert.ok(response.body.includes('name="position"'));
    for (const name of ['profile','orientation','content_orientation','paper_width_mm','paper_height_mm','font_size_pt','offset_x_mm','margin_mm']) {
        assert.ok(!response.body.includes(`name="${name}"`));
    }
    assert.equal((await request('print-settings.php', {position:'left',csrf:'bad'})).status,419);
    response = await request('print-settings.php', {csrf:authCsrf,position:'middle',profile:'custom',orientation:'portrait',paper_width_mm:400,font_size_pt:10,action:'save'});
    assert.equal(response.status,303);
    assert.equal(response.location,'print-settings.php?position=middle');
    assert.equal(JSON.stringify(snapshot().settings),savedSettings);
    response = await request('print-test.php'); assert.equal(response.status,303); assert.equal(response.location,'print-settings.php');
    report('Fixed print format: only column selection, legacy settings ignored, old calibration link redirected');

    const chromePath = process.env.CHROME_BIN || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    if (!existsSync(chromePath)) throw new Error('Chrome tidak ditemukan; tentukan CHROME_BIN.');
    chrome = spawn(chromePath, ['--headless=new', '--disable-gpu', '--in-process-gpu', ...(process.env.QA_CHROME_NO_SANDBOX === '1' ? ['--no-sandbox'] : []), '--remote-debugging-port=9228', `--user-data-dir=${browserProfile}`, '--no-first-run', '--disable-background-networking', '--disable-sync', 'about:blank'], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    let chromeLog = '';
    chrome.stderr.on('data', bytes => { chromeLog += bytes; });
    chrome.on('exit', () => { writeFile(join(artifacts, 'chrome.log'), chromeLog).catch(() => {}); });
    let targets;
    for (let attempt = 0; attempt < 30; attempt++) { try { targets = await (await fetch('http://127.0.0.1:9228/json/list', { signal: AbortSignal.timeout(1500) })).json(); if (targets.length) break; } catch {} await delay(300); }
    assert.ok(targets?.length, 'Chrome CDP unavailable');
    const socket = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    cdp = new CDP(socket); await cdp.call('Page.enable'); await cdp.call('Network.enable');
    // QA offline: semua dependensi fungsi lokal; font Inter boleh memakai fallback sistem.
    await cdp.call('Network.setBlockedURLs', { urls: ['https://*'] });
    const [cookieName, cookieValue] = jar.split('=');
    await cdp.call('Network.setCookie', { name: cookieName, value: cookieValue, domain: '127.0.0.1', path: '/', httpOnly: true });
    await cdp.call('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1050, deviceScaleFactor: 1, mobile: false });
    await cdp.navigate('index.php'); await cdp.screenshot('dashboard-desktop.png');
    assert.equal(await cdp.evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
    assert.equal(await cdp.evaluate('getComputedStyle(document.querySelector(".row.g-4") || document.querySelector(".d-flex")).display'), 'flex');
    report('Desktop dashboard rendered with Bootstrap');
    await cdp.navigate('preview.php?id=' + id);
    assert.equal(await cdp.evaluate(`(() => {
        const region = document.querySelector('.internal-approval-region');
        const province = document.querySelector('.internal-approval-province');
        const date = document.querySelector('.internal-approval-date');
        const position = document.querySelector('.document-position');
        const originalTransform = position.style.transform;
        position.style.transform = 'none';
        const regionRect = region.getBoundingClientRect();
        const provinceRect = province.getBoundingClientRect();
        const dateRect = date.getBoundingClientRect();
        const nameRect = document.querySelector('.internal-approval-content').getBoundingClientRect();
        position.style.transform = originalTransform;
        const centerX = rect => rect.left + rect.width / 2;
        const roleStyle = getComputedStyle(document.querySelector('.internal-approval-job'));
        const matchingFonts = [...document.querySelectorAll('.internal-approval-region, .internal-approval-province, .internal-approval-content, .internal-approval-nrp, .internal-approval-date')]
            .every(element => {
                const style = getComputedStyle(element);
                return style.fontSize === roleStyle.fontSize && style.fontFamily === roleStyle.fontFamily
                    && style.fontWeight === roleStyle.fontWeight;
            });
        return region.textContent === 'GRESIK,' && province.textContent === 'JAWA TIMUR'
            && matchingFonts
            && date.textContent === '08-January-2026'
            && dateRect.left >= regionRect.right
            && Math.abs(centerX(regionRect) - centerX(nameRect)) < 1
            && Math.abs(centerX(provinceRect) - centerX(nameRect)) < 1
            && getComputedStyle(date).fontSize === getComputedStyle(region).fontSize
            && provinceRect.top >= regionRect.bottom;
    })()`), true);
    report('Region and province align vertically with a matching date font on the right; all fields persist and reopen for editing');
    const preview = await cdp.evaluate('document.querySelector(".vehicle-document").outerHTML');
    assert.ok(preview.includes('<figcaption>PERIKSA KEMBALI &lt;UJI&gt;</figcaption>'));
    assert.ok(preview.includes('<span>D5H7GUSPR</span>'));
    assert.ok(preview.includes('ADMIN &lt;UJI&gt;'));
    assert.equal(await cdp.evaluate(`(() => {
        return [...document.querySelectorAll('.document-value:not(.document-expiry) dd, .internal-approval-region, .internal-approval-province, .internal-approval-content, .internal-approval-job, .internal-approval-nrp')]
            .every(element => element.textContent === element.textContent.toUpperCase());
    })()`), true);
    assert.ok(preview.includes('NRP 00012345'));
    assert.equal(await cdp.evaluate(`(() => {
        const image=document.querySelector('.internal-approval-signature');
        const qr=document.querySelector('.internal-reference-code img');
        return image.complete && image.naturalWidth>0 && qr.complete && qr.naturalWidth>0
            && getComputedStyle(document.querySelector('.internal-approval-signature-space')).mixBlendMode==='multiply'
            && parseFloat(getComputedStyle(document.querySelector('.internal-approval-content')).borderBottomWidth)>0;
    })()`),true);
    const layout = await cdp.evaluate(`(() => {
        const doc=document.querySelector('.vehicle-document');
        const relative = element => {
            let x=0,y=0;
            while(element && element!==doc) {x+=element.offsetLeft;y+=element.offsetTop;element=element.offsetParent;}
            return {x:x*25.4/96,y:y*25.4/96};
        };
        return {font:parseFloat(getComputedStyle(doc).fontSize)*72/96*parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--doc-scale')),
            owner:relative(document.querySelector('.document-owner')),
            ownerBottom:Math.max(...[...document.querySelectorAll('.document-owner .document-value dd')].map(value => relative(value).y + value.offsetHeight*25.4/96)),
            plate:relative(document.querySelector('.document-plate')),
            nik:relative(document.querySelector('.document-nik')),
            primary:relative(document.querySelector('.document-primary')),
            merek:relative(document.querySelector('[data-field=merek]')),
            model:relative(document.querySelector('[data-field=model]')),
            year:relative(document.querySelector('[data-field=_tahun]')),
            primaryStep:relative(document.querySelector('[data-field=tipe]')).y-relative(document.querySelector('[data-field=merek]')).y,
            ownerStep:relative(document.querySelector('[data-field=nama_pemilik]')).y-relative(document.querySelector('[data-field=nomor_polisi]')).y,
            secondary:relative(document.querySelector('.document-secondary')),
            bottom:relative(document.querySelector('.document-admin-bottom')),
            location:relative(document.querySelector('[data-field=kode_lokasi]')),
            expiry:relative(document.querySelector('.document-expiry')),
            expiryAlign:getComputedStyle(document.querySelector('.document-expiry dd')).textAlign,
            expiryFont:parseFloat(getComputedStyle(document.querySelector('.document-expiry dd')).fontSize)*72/96*parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--doc-scale')),
            approval:relative(document.querySelector('.document-approval')),
            signatureWidth:parseFloat(getComputedStyle(document.querySelector('.internal-approval-signature-space')).width)*25.4/96,
            nameFont:parseFloat(getComputedStyle(document.querySelector('.internal-approval-content')).fontSize)*72/96*parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--doc-scale')),
            qr:relative(document.querySelector('.internal-reference-code')),
            qrCaptionFont:parseFloat(getComputedStyle(document.querySelector('.internal-reference-code>span')).fontSize)*72/96*parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--doc-scale')),
            qrWidth:parseFloat(getComputedStyle(document.querySelector('.internal-reference-code img')).width)*25.4/96};
    })()`);
    assert.ok(Math.abs(layout.font-10)<.05, 'Printed font must be 10pt after layout scaling');
    assert.equal(await cdp.evaluate('getComputedStyle(document.documentElement).getPropertyValue("--doc-line").trim()'), '1.20');
    assert.ok(Math.abs(layout.owner.x-55.1)<.2 && Math.abs(layout.owner.y-23.5)<.2);
    assert.ok(Math.abs(layout.nik.x-126.63)<.3 && Math.abs(layout.nik.y-24)<.2, 'NIK must retain its horizontal correction');
    assert.ok(Math.abs(layout.primary.y-44)<.2 && Math.abs(layout.secondary.x-144.3)<.2 && Math.abs(layout.secondary.y-44)<.2);
    assert.ok(Math.abs(layout.plate.y-24)<.2 && Math.abs(layout.merek.y-44.5)<.3 && Math.abs(layout.model.y-59.5)<.3 && Math.abs(layout.year.y-64)<.3, 'Both specification blocks must leave fixed space for a two-line address at 10pt');
    assert.ok(layout.ownerBottom <= layout.merek.y-.5, 'Owner and address text must leave a visible gap before the first specification row');
    assert.ok(Math.abs(layout.primaryStep-5)<.3 && Math.abs(layout.ownerStep-5)<.3, 'Owner and specification rows must have the larger 5mm spacing');
    assert.ok(Math.abs(layout.qr.x-183.5)<.3 && Math.abs(layout.qr.y-37.5)<.3 && Math.abs(layout.qrWidth-20)<.1);
    assert.ok(Math.abs(layout.qrCaptionFont-9.5)<.08, 'Text beneath QR must scale with the 10pt font');
    assert.ok(Math.abs(layout.approval.x-168.05)<.2 && Math.abs(layout.approval.y-10)<.2 && Math.abs(layout.signatureWidth-48)<.1 && Math.abs(layout.nameFont-8)<.08, 'Approval block must move another 1mm right and use the 8pt role font while retaining its signature size');
    assert.ok(Math.abs(layout.bottom.x-212)<.3 && Math.abs(layout.expiry.x-202.5)<.3 && layout.expiry.y>74.5 && layout.expiry.y<75.8 && layout.expiryAlign==='right' && Math.abs(layout.expiryFont-10.5)<.08, 'Date and code must move 3mm right without changing their vertical positions');
    assert.ok(Math.abs(layout.location.x-223.5)<.3 && Math.abs(layout.location.y-layout.bottom.y+3)<.3, 'Location code must move another 3mm right with the date');
    assert.equal(await cdp.evaluate('document.querySelector(".document-expiry dd").textContent'),'04 November 2031');
    assert.equal(await cdp.evaluate('document.querySelector("[data-field=tanggal_penerbitan]") === null'),true);
    assert.equal(await cdp.evaluate('document.body.classList.contains("print-overflow")'),false);
    assert.equal(await cdp.evaluate('document.getElementById("position").value'),'middle');
    assert.equal(await cdp.evaluate('[...document.querySelectorAll("#position option")].map(o=>o.value).join(",")'),'left,middle,right');
    await cdp.screenshot('preview-fixed.png');
    report('Fixed portrait STNK: original field positions, signature, QR and hidden issue date');
    let pdf;
    const slotPositions = [];
    for (const position of ['left', 'middle', 'right']) {
        const params = new URLSearchParams({id,profile:'custom',orientation:'portrait',content_orientation:'landscape',position,font_size_pt:10,crop:1});
        await cdp.navigate('preview.php?' + params);
        assert.equal(await cdp.evaluate('document.querySelectorAll("#orientation,#content_orientation,#profile,#crop").length'),0);
        assert.equal(await cdp.evaluate('document.getElementById("position").value'), position);
        assert.equal(await cdp.evaluate('document.querySelectorAll(".vehicle-document").length'), 1);
        assert.equal(await cdp.evaluate('document.body.classList.contains("print-overflow")'), false);
        assert.equal(await cdp.evaluate(`(() => {
            const text=document.querySelector('.document-expiry dd');
            const paper=document.querySelector('.paper-sheet').getBoundingClientRect();
            const rect=text.getBoundingClientRect();
            return text.scrollWidth<=text.clientWidth+1 && rect.bottom<=paper.bottom+1;
        })()`),true,'The complete date and year must fit on one line inside the configured paper size');
        assert.equal(await cdp.evaluate(`(() => {
            const page=document.querySelector('.paper-sheet').getBoundingClientRect();
            const style=getComputedStyle(document.documentElement);
            const left=parseFloat(style.getPropertyValue('--slot-left'))*96/25.4;
            const width=parseFloat(style.getPropertyValue('--slot-width'))*96/25.4;
            return [...document.querySelectorAll('.document-value dd')].every(element => {
                const r=element.getBoundingClientRect();
                return r.left>=page.left+left-1 && r.right<=page.left+left+width+1 && r.top>=page.top-1 && r.bottom<=page.bottom+1;
            });
        })()`),true,'Every value, including lowered expiry, must stay inside the chosen print column');
        const slotPreview = await cdp.evaluate('document.querySelector(".vehicle-document").outerHTML');
        const printUrl = await cdp.evaluate('new URL([...document.querySelectorAll("a")].find(a=>a.getAttribute("href")?.startsWith("print.php?")).href).search');
        assert.equal(new URLSearchParams(printUrl).get('position'), position);
        await cdp.navigate('print.php?' + params);
        assert.equal(await cdp.evaluate('document.querySelector(".vehicle-document").outerHTML'), slotPreview);
        const slotBounds = await cdp.evaluate(`(() => {
            const p=document.querySelector('.paper-sheet').getBoundingClientRect();
            const d=document.querySelector('.vehicle-document').getBoundingClientRect();
            return {w:p.width*25.4/96,h:p.height*25.4/96,x:(d.left-p.left)*25.4/96,y:(d.top-p.top)*25.4/96,
                dw:d.width*25.4/96,dh:d.height*25.4/96};
        })()`);
        assert.ok(Math.abs(slotBounds.w-297)<.1 && Math.abs(slotBounds.h-230)<.1, 'Paper must provide the measured 23cm length');
        const expectedLeft=['left','middle','right'].indexOf(position)*99+(99-75*210/230)/2+10.5;
        assert.ok(Math.abs(slotBounds.x-expectedLeft)<.1, 'The entire rotated document must rise by a total of 10.5mm in reading orientation');
        assert.ok(slotBounds.dh>slotBounds.dw && slotBounds.x>=0 && slotBounds.y>=-.1);
        assert.ok(slotBounds.x+slotBounds.dw <= 297.1 && slotBounds.y+slotBounds.dh <= 230.1);
        assert.ok(Math.abs(slotBounds.dh-210)<.1 && Math.abs(slotBounds.y)<.1, 'Existing fields must keep their print scale and starting position');
        slotPositions.push(slotBounds.x);
        assert.equal(await cdp.evaluate('document.body.classList.contains("print-overflow")'), false);
        pdf = await cdp.call('Page.printToPDF', {preferCSSPageSize:true,printBackground:true,displayHeaderFooter:false});
        const slotPdf=Buffer.from(pdf.data,'base64');
        const slotText=slotPdf.toString('latin1');
        assert.equal((slotText.match(/\/Type\s*\/Page\b/g)||[]).length,1);
        const slotBox=slotText.match(/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)/);
        assert.ok(slotBox && Number(slotBox[1]) > Number(slotBox[2]), 'PDF must stay landscape');
        assert.ok(Math.abs(Number(slotBox[2])-230*72/25.4)<1, 'PDF page must retain the full 23cm length');
        await writeFile(join(artifacts,`landscape-portrait-${position}.pdf`),slotPdf);
        const clip=await cdp.evaluate('(() => {const r=document.querySelector(".paper-sheet").getBoundingClientRect();return {x:r.x+scrollX,y:r.y+scrollY,width:r.width,height:r.height,scale:1};})()');
        const shot=await cdp.call('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip});
        await writeFile(join(artifacts,`landscape-portrait-${position}.png`),Buffer.from(shot.data,'base64'));
    }
    assert.ok(Math.abs(slotPositions[1]-slotPositions[0]-99)<.1 && Math.abs(slotPositions[2]-slotPositions[1]-99)<.1);
    await cdp.call('Emulation.setEmulatedMedia', {media:'print'});
    assert.equal(await cdp.evaluate('getComputedStyle(document.querySelector(".paper-slot-guide")).display'), 'none');
    await cdp.call('Emulation.setEmulatedMedia', {media:''});
    await cdp.navigate('preview.php?id='+id);
    assert.equal(await cdp.evaluate('document.getElementById("position").value'),'right');
    await cdp.navigate('print.php?id='+id+'&position=full&orientation=portrait&profile=custom&content_orientation=landscape');
    assert.equal(await cdp.evaluate('document.getElementById("position").value'),'middle');
    assert.equal(await cdp.evaluate('getComputedStyle(document.documentElement).getPropertyValue("--doc-rotation").trim()'),'90deg');
    report('Fixed layout: three columns, unchanged landscape PDF, one STNK, remembered position and legacy URL protection');
    response = await request('update.php', { ...fixture, csrf: authCsrf, id, version: snapshot().vehicles[0].updated_at,
        alamat: 'Klampis Utara RT 3 RW 2 DS Klampis Rejo Kec Kraton Pasuruan', jabatan_pemeriksa: 'Kombes Pol' });
    assert.equal(response.status, 303);
    await cdp.navigate('print.php?id=' + id);
    assert.equal(await cdp.evaluate(`(() => {
        const address = document.querySelector('.document-address dd');
        const scale = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--doc-scale'));
        const font = parseFloat(getComputedStyle(address).fontSize)*72/96*scale;
        const position = document.querySelector('.document-position');
        const originalTransform = position.style.transform;
        position.style.transform = 'none';
        const boundary = document.querySelector('.internal-approval-job').getBoundingClientRect().left;
        const addressBounds = address.getBoundingClientRect();
        const wordTop = word => {
            const range = document.createRange();
            const start = address.firstChild.textContent.indexOf(word);
            range.setStart(address.firstChild, start);
            range.setEnd(address.firstChild, start + word.length);
            return range.getBoundingClientRect().top;
        };
        const wordsWrap = Math.abs(wordTop('KRATON') - addressBounds.top) < 2
            && wordTop('PASURUAN') > wordTop('KRATON') + 2;
        position.style.transform = originalTransform;
        return !document.querySelector('[data-print]').disabled && address.scrollWidth <= address.clientWidth + 1
            && Math.abs(font - 10) < .05 && getComputedStyle(address).whiteSpace === 'normal'
            && address.offsetHeight > parseFloat(getComputedStyle(address).lineHeight) * 1.5
            && addressBounds.right < boundary - 2 && wordsWrap;
    })()`), true);
    assert.equal(await cdp.evaluate(`(() => {
        window.print = () => { window.testPrinted = true; };
        document.querySelector('[data-print]').click();
        return new Promise(resolve => setTimeout(() => resolve(window.testPrinted === true), 100));
    })()`), true);
    await cdp.evaluate('window.testPrinted = false; window.dispatchEvent(new Event("beforeprint"))');
    assert.equal(await cdp.evaluate('document.querySelector("[data-print]").disabled'), false);
    report('Address wraps before the approval area at 10pt; Print click and repeated checks stay enabled');
    await cdp.call('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
    await cdp.navigate('index.php'); assert.equal(await cdp.evaluate('document.documentElement.scrollWidth <= innerWidth'), true); await cdp.screenshot('dashboard-mobile.png');
    await cdp.evaluate('document.getElementById("menu-toggle").click()'); assert.equal(await cdp.evaluate('document.getElementById("sidebar").classList.contains("is-open")'), true);
    await cdp.navigate('tambah.php'); assert.equal(await cdp.evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
    assert.equal(await cdp.evaluate(`(() => {
        const name = document.getElementById('nama_pemilik');
        name.value = 'Budi santoso'; name.setSelectionRange(5, 5);
        name.dispatchEvent(new Event('input', { bubbles: true }));
        const address = document.getElementById('alamat');
        address.value = 'Jalan melati\\nJakarta';
        address.dispatchEvent(new Event('input', { bubbles: true }));
        return name.value === 'BUDI SANTOSO' && name.selectionStart === 5
            && address.value === 'JALAN MELATI\\nJAKARTA'
            && document.querySelector('[data-count-for="alamat"]').textContent === String(address.value.length)
            && [...document.querySelectorAll('#bahan_bakar option, #warna_tnkb option')].filter(option => option.value).every(option => option.value === option.value.toUpperCase());
    })()`), true);
    await cdp.screenshot('form-mobile.png'); report('Mobile form: uppercase input, caret, multiline address and dropdowns');
    const long = { ...fixture, csrf: authCsrf, id, version: snapshot().vehicles[0].updated_at, alamat: 'Alamat fiktif yang panjang untuk memastikan pembungkusan teks. '.repeat(5).slice(0, 300), nama_pemilik: 'Nama pemilik panjang '.repeat(5).slice(0, 100), tipe: 'Tipe dengan informasi panjang '.repeat(3).slice(0, 80) };
    assert.equal((await request('update.php', long)).status, 303); await cdp.navigate('preview.php?id=' + id);
    const longFits = await cdp.evaluate('!document.body.classList.contains("print-overflow")');
    await cdp.screenshot('preview-long-text.png');
    assert.equal(longFits,false);
    assert.equal(await cdp.evaluate('document.getElementById("fit-message").hidden'),false);
    assert.ok(Math.abs(await cdp.evaluate(`parseFloat(getComputedStyle(document.querySelector('.vehicle-document')).fontSize)*72/96*parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--doc-scale'))`) - 10)<.05);
    assert.equal(await cdp.evaluate(`document.querySelector('.vehicle-document').style.getPropertyValue('--doc-line')`),'1.2');
    report('Long data is flagged without changing the fixed font or line spacing');
    assert.ok(Math.abs(await cdp.evaluate(`(() => {
        document.body.classList.remove('print-overflow');
        const top = document.querySelector('.document-primary').offsetTop*25.4/96;
        document.body.classList.add('print-overflow');
        return top;
    })()`) - layout.primary.y) < .1, 'Long address must not shift specification rows');
    response = await request('update.php', { ...fixture, csrf: authCsrf, id, version: snapshot().vehicles[0].updated_at, teks_atas_qr: '', tanggal_wilayah: '' });
    assert.equal(response.status, 303);
    assert.equal(snapshot().vehicles.find(vehicle => vehicle.id === id).teks_atas_qr, null);
    assert.equal(snapshot().vehicles.find(vehicle => vehicle.id === id).tanggal_wilayah, null);
    response = await request('preview.php?id=' + id); assert.ok(!response.body.includes('<figcaption>'));
    assert.ok(!response.body.includes('class="internal-approval-date"'));
    assert.ok(response.body.includes('class="internal-approval-region">GRESIK</div>'));
    response = await request('print.php?id=' + id); assert.ok(!response.body.includes('<figcaption>')); assert.ok(response.body.includes('D5H7GUSPR'));
    response = await request('edit.php?id=' + id); assert.match(response.body, /id="teks_atas_qr"[^>]*value=""/);
    report('QR caption: editable per vehicle, escaped, uppercase, optional and retained on edit');
    assert.equal((await request('hapus.php?id=' + id)).status, 405); assert.equal((await request('hapus.php', { id, csrf: 'bad' })).status, 419);
    assert.equal((await request('hapus.php', { id, csrf: authCsrf })).status, 303); assert.equal((await request('detail.php?id=' + id)).status, 404); report('Delete uses POST+CSRF; removed vehicle inaccessible');
    assert.equal((await request('logout.php', { csrf: authCsrf })).status, 303); assert.equal((await request('index.php')).status, 303); report('Logout revokes session');
    for (let n = 0; n < 5; n++) {
        response = await request('login.php'); response = await request('login.php', { csrf: csrf(response.body), username: 'qa_admin', password: 'incorrect-password' }); assert.equal(response.status, 422);
    }
    jar = ''; response = await request('login.php'); response = await request('login.php', { csrf: csrf(response.body), username: 'qa_admin', password }); assert.equal(response.status, 429); report('Database login limit survives new session');
    assert.ok(!/PHP (Warning|Fatal|Deprecated)|Uncaught/.test(serverLog), 'PHP error found in server log');
    await writeFile(join(artifacts, 'results.txt'), `${pass} checks passed\nPHP ${phpCall('-v').split('\n')[0]}\nChrome headless + MySQL temporary database\nPhysical printer not tested.\n`);
    console.log(`\n${pass} checks passed. Artifacts: tests/artifacts`);
} catch (error) {
    console.error(error.stack || error); process.exitCode = 1;
} finally {
    if (cdp) { try { await cdp.call('Browser.close'); } catch {} cdp.socket.close(); }
    chrome?.kill(); server?.kill();
    await delay(500);
    if (dbCreated) phpCall('tests/db-helper.php', 'drop');
    if (storage.startsWith(artifacts + sep)) await rm(storage, { recursive: true, force: true });
    if (browserProfile.startsWith(resolve(tmpdir()) + sep + 'kendara-qa-')) await rm(browserProfile, { recursive: true, force: true, maxRetries: 4, retryDelay: 300 }).catch(() => {});
}

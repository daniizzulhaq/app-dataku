// Pemeriksaan terarah: tidak membuat, mengubah, atau menghapus data kendaraan.
import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { mkdir, writeFile, rm } from 'node:fs/promises';
import { resolve, join, sep } from 'node:path';
import { tmpdir } from 'node:os';
import { randomBytes } from 'node:crypto';
import WebSocket from './vendor/ws/wrapper.mjs';

const origin = process.env.KENDARA_QA_URL || 'http://localhost/stnk';
const root = resolve(import.meta.dirname, '..');
const profile = join(tmpdir(), 'kendara-inspect-' + randomBytes(5).toString('hex'));
const artifacts = join(root, 'tests', 'artifacts');
const password = readFileSync(0, 'utf8').trim();
let browser, socket, cookie = '', timer;
const pending = new Map();
const events = new Map();
let counter = 0;
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function request(path, data) {
    const result = await fetch(`${origin}/${path}`, {
        redirect: 'manual', signal: AbortSignal.timeout(10000),
        headers: { ...(cookie ? { Cookie: cookie } : {}), ...(data ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}) },
        ...(data ? { method: 'POST', body: new URLSearchParams(data) } : {}),
    });
    if (result.headers.get('set-cookie')) cookie = result.headers.get('set-cookie').split(';')[0];
    return { status: result.status, body: await result.text() };
}
function call(method, params = {}) {
    const id = ++counter;
    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => { pending.delete(id); reject(new Error(`Timeout: ${method}`)); }, 20000);
        pending.set(id, { resolve: data => { clearTimeout(timeout); resolve(data); }, reject: error => { clearTimeout(timeout); reject(error); } });
        socket.send(JSON.stringify({ id, method, params }));
    });
}
async function evaluate(expression) {
    const data = await call('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (data.exceptionDetails) throw new Error(JSON.stringify(data.exceptionDetails));
    return data.result.value;
}
try {
    if (!password) throw new Error('Masukkan password akun pengujian melalui stdin.');
    const login = await request('login.php');
    const csrf = login.body.match(/name="csrf" value="([a-f0-9]+)"/);
    if (!csrf) throw new Error(`Halaman login tidak memuat token (HTTP ${login.status}).`);
    const authenticated = await request('login.php', { csrf: csrf[1], username: process.env.KENDARA_QA_USER || 'admin', password });
    if (authenticated.status !== 303) throw new Error(`Login diagnostik gagal (HTTP ${authenticated.status}).`);

    browser = spawn(process.env.CHROME_BIN || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        ['--headless=new', '--disable-gpu', '--in-process-gpu', ...(process.env.QA_CHROME_NO_SANDBOX === '1' ? ['--no-sandbox'] : []), '--remote-debugging-port=9230', `--user-data-dir=${profile}`, '--no-first-run', '--disable-background-networking', 'about:blank'],
        { windowsHide: true, stdio: 'ignore' });
    let targets;
    for (let attempt = 0; attempt < 20; attempt++) {
        try { targets = await (await fetch('http://127.0.0.1:9230/json/list', { signal: AbortSignal.timeout(1000) })).json(); if (targets.length) break; } catch {}
        await pause(300);
    }
    if (!targets?.length) throw new Error('Chrome diagnostik tidak tersedia.');
    socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.once('open', resolve); socket.once('error', reject); });
    socket.on('message', bytes => {
        const event = JSON.parse(bytes);
        if (event.id && pending.has(event.id)) {
            const operation = pending.get(event.id); pending.delete(event.id);
            event.error ? operation.reject(new Error(JSON.stringify(event.error))) : operation.resolve(event.result);
        } else { for (const listener of events.get(event.method) || []) listener(event.params); }
    });
    await call('Page.enable'); await call('Network.enable');
    await call('Network.setBlockedURLs', { urls: ['https://*'] });
    const [name, value] = cookie.split('=');
    await call('Network.setCookie', { name, value, url: origin, path: '/', httpOnly: true });
    await call('Emulation.setDeviceMetricsOverride', { width: 1440, height: 950, deviceScaleFactor: 1, mobile: false });
    await call('Page.addScriptToEvaluateOnNewDocument', { source: 'window.appErrors = []; addEventListener("error", event => appErrors.push(event.message)); addEventListener("unhandledrejection", event => appErrors.push(String(event.reason)));' });
    await mkdir(artifacts, { recursive: true });
    for (const path of ['index.php', 'tambah.php', 'edit.php?id=1', 'detail.php?id=1', 'preview.php?id=1', 'print.php?id=1', 'print.php?id=1&profile=a4']) {
        const response = await request(path);
        let loaded;
        const load = new Promise(resolve => { loaded = resolve; });
        events.set('Page.loadEventFired', [loaded]);
        await call('Page.navigate', { url: `${origin}/${path}` });
        await Promise.race([load, new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('Halaman tidak selesai dimuat.')), 15000); })]);
        clearTimeout(timer);
        await evaluate('document.fonts.ready.then(() => true)'); await pause(100);
        const diagnostic = await evaluate(`(() => {
            const content = document.querySelector('.document-content');
            const columns = document.querySelector('.document-columns');
            const size = element => element ? { height: element.clientHeight, scroll: element.scrollHeight, width: element.clientWidth, scrollWidth: element.scrollWidth } : null;
            return { title: document.title, errors: window.appErrors, formInputs: document.querySelectorAll('[data-vehicle-form] [name]').length,
                approvalInput: !!document.getElementById('pengesahan_internal'), qrPresent: !!document.querySelector('.internal-reference-code img'),
                printBlocked: document.body.classList.contains('print-overflow'), fitMessage: document.getElementById('fit-message')?.textContent || '',
                content: size(content), columns: size(columns), parts: [...document.querySelectorAll('.document-column')].map(element => ({ class: element.className, ...size(element) })) };
        })()`);
        console.log(JSON.stringify({ path, http: response.status, ...diagnostic }));
        if (path === 'preview.php?id=1') {
            const image = await call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
            await writeFile(join(artifacts, 'current-preview.png'), Buffer.from(image.data, 'base64'));
        }
    }
} catch (error) {
    console.error(error.message); process.exitCode = 1;
} finally {
    clearTimeout(timer);
    if (socket?.readyState === WebSocket.OPEN) {
        try { await call('Browser.close'); } catch {}
        socket.close();
    }
    browser?.kill();
    try {
        const form = await request('index.php');
        const csrf = form.body.match(/name="csrf" value="([a-f0-9]+)"/);
        if (csrf) await request('logout.php', { csrf: csrf[1] });
    } catch {}
    await pause(400);
    if (profile.startsWith(resolve(tmpdir()) + sep + 'kendara-inspect-')) await rm(profile, { recursive: true, force: true, maxRetries: 4, retryDelay: 300 }).catch(() => {});
}

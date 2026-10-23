import fs from 'node:fs/promises';

// Requires an isolated Chrome profile on port 9225 and fixture server on 8001.
const out = new URL('../storage/release-acceptance/', import.meta.url);
const tabs = await (await fetch('http://127.0.0.1:9225/json')).json();
const tab = tabs.find(tab => tab.type === 'page');
if (!tab) throw new Error('No isolated browser tab');
const socket = new WebSocket(tab.webSocketDebuggerUrl);
await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
let id = 0;
const pending = new Map();
const events = [];
socket.onmessage = event => {
    const message = JSON.parse(event.data);
    if (message.id) {
        const request = pending.get(message.id);
        if (!request) return;
        pending.delete(message.id);
        message.error ? request.reject(new Error(JSON.stringify(message.error))) : request.resolve(message.result);
    } else if (['Runtime.exceptionThrown', 'Log.entryAdded', 'Network.loadingFailed'].includes(message.method)
        || (message.method === 'Network.responseReceived' && message.params.response.status >= 400)) {
        events.push(message);
    }
};
const send = (method, params = {}) => new Promise((resolve, reject) => {
    const requestId = ++id;
    pending.set(requestId, {resolve, reject});
    socket.send(JSON.stringify({id: requestId, method, params}));
});
const evaluate = async expression => {
    const result = await send('Runtime.evaluate', {expression, awaitPromise: true, returnByValue: true});
    if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
};
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const navigate = async path => {
    await send('Page.navigate', {url: 'http://127.0.0.1:8001' + path});
    for (let i = 0; i < 150; i++) {
        await delay(100);
        if (await evaluate(`document.readyState === 'complete' && location.pathname === ${JSON.stringify(path)}`)) return;
    }
    throw new Error('Navigation timeout: '+path);
};
await send('Page.enable');
await send('Runtime.enable');
await send('Network.enable');
await send('Log.enable');
await send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false});
await navigate('/login');
await delay(500);
await evaluate(`document.querySelector('input[autocomplete="username"]').focus()`);
await send('Input.insertText', {text:'release-browser@example.test'});
await evaluate(`document.querySelector('input[type="password"]').focus()`);
await send('Input.insertText', {text:'Release-browser-fixture-2026!'});
await evaluate(`document.querySelector('input[type="password"]').closest('form').requestSubmit()`);
for (let i = 0; i < 100; i++) {
    await delay(100);
    if (await evaluate(`location.pathname === '/dashboard' && document.readyState === 'complete'`)) break;
}
if (await evaluate(`location.pathname !== '/dashboard'`)) {
    await fs.writeFile(new URL('login-failure.json',out), JSON.stringify({page:await evaluate(`({url:location.href,text:document.body.innerText,livewire:!!window.Livewire,alpine:!!window.Alpine,inputs:[...document.querySelectorAll('input')].map(i=>({name:i.name,type:i.type,filled:!!i.value})),scripts:[...document.scripts].map(s=>s.src)})`),events},null,2));
    const shot = await send('Page.captureScreenshot', {format:'png'});
    await fs.writeFile(new URL('login-failure.png',out), Buffer.from(shot.data,'base64'));
    socket.close();
    throw new Error('Login did not reach dashboard; inspect login-failure.json');
}
const results = [];
const paths = ['/dashboard','/whats-new','/notifications','/planner','/planner/create','/profile','/intents','/needs-offers','/businesses','/work','/bookkeeping','/contacts','/messages'];
for (const locale of ['en','fa','ar','zh_CN']) {
    await evaluate(`fetch('/locale', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({locale:${JSON.stringify(locale)},return_to:'/dashboard'})}).then(r=>r.ok)`);
    for (const width of [1440, 390]) {
        await send('Emulation.setDeviceMetricsOverride', {width, height: 1000, deviceScaleFactor: 1, mobile: width < 600});
        for (const path of paths) {
            const start = events.length;
            await navigate(path);
            await delay(250);
            const data = await evaluate(`({path:location.pathname, lang:document.documentElement.lang, title:document.title, version:document.querySelector('[data-app-version]')?.textContent?.trim(), overflow:document.documentElement.scrollWidth > innerWidth+1, pushState:document.querySelector('[data-device-notifications]') ? window.Alpine?.$data(document.querySelector('[data-device-notifications]'))?.state : null})`);
            results.push({locale,width,...data,errors:events.slice(start)});
            await fs.writeFile(new URL('browser-results.json',out), JSON.stringify({results,events},null,2));
            if (['/whats-new','/notifications','/messages'].includes(path)) {
                const shot = await send('Page.captureScreenshot', {format:'png'});
                await fs.writeFile(new URL(`${locale}-${width}-${path.slice(1)}.png`,out), Buffer.from(shot.data,'base64'));
            }
        }
    }
    console.log(`Completed ${locale}: ${results.length} page checks`);
}
await fs.writeFile(new URL('browser-results.json',out), JSON.stringify({results,events},null,2));
console.log(JSON.stringify({pages:results.length,overflow:results.filter(r=>r.overflow).length,errors:events.length}));
socket.close();

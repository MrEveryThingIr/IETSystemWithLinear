import fs from 'node:fs/promises';

// Small companion to release-browser-smoke.mjs. Uses only the isolated fixture server/profile.
const base = process.env.MOBILE_SHELL_BASE_URL ?? 'http://127.0.0.1:8012';
const debugging = process.env.MOBILE_SHELL_CDP_URL ?? 'http://127.0.0.1:9238';
const out = new URL('../storage/release-acceptance/v1.12-mobile-messaging-shell/', import.meta.url);
const fixture = JSON.parse(await fs.readFile(new URL('fixture.json', out), 'utf8'));
const tabs = await (await fetch(debugging + '/json')).json();
const tab = tabs.find(tab => tab.type === 'page');
if (!tab) throw new Error('Start an isolated headless Chrome profile before this test.');
const socket = new WebSocket(tab.webSocketDebuggerUrl);
await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
let id = 0;
const pending = new Map();
const errors = [];
let currentPhase = "startup";
socket.onmessage = event => {
    const message = JSON.parse(event.data);
    if (message.id) {
        const request = pending.get(message.id);
        if (!request) return;
        pending.delete(message.id);
        message.error ? request.reject(new Error(JSON.stringify(message.error))) : request.resolve(message.result);
    } else if (message.method === 'Runtime.exceptionThrown') errors.push({phase:currentPhase,...message.params});
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
const waitFor = async expression => {
    for (let i=0; i<150; i++) { if (await evaluate(expression)) return; await delay(100); }
    throw new Error('Timed out: ' + expression);
};
const navigate = async path => {
    currentPhase = path;
    await send('Page.navigate', {url: base + path});
    await waitFor(`document.readyState === 'complete' && location.href === ${JSON.stringify(base + path)}`);
    await delay(200);
};
const assert = (condition, message) => { if (!condition) throw new Error(message); };
const results = [];
try {
    await send('Page.enable');
    await send('Runtime.enable');
    await send('Network.enable');
    await send('Page.navigate', {url:'about:blank'});
    await delay(5000); // Drain the previous fixture tab's polling before clearing its session.
    await send('Network.clearBrowserCookies');
    await send('Emulation.setDeviceMetricsOverride', {width:390,height:844,deviceScaleFactor:1,mobile:true});
    await navigate('/login');
    await evaluate(`document.querySelector('input[autocomplete="username"]').focus()`);
    await send('Input.insertText', {text:'mobile-shell-viewer'});
    await evaluate(`document.querySelector('input[type="password"]').focus()`);
    await send('Input.insertText', {text:'Mobile-shell-fixture-2026!'});
    await evaluate(`document.querySelector('input[type="password"]').closest('form').requestSubmit()`);
    await waitFor(`location.pathname === '/dashboard' && !!document.querySelector('[data-launcher-fab]')`);
    for (const locale of ['en','fa']) {
        const ok = await evaluate(`fetch('/locale',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({locale:${JSON.stringify(locale)},return_to:'/dashboard'})}).then(r=>r.ok)`);
        assert(ok, 'Locale update failed');
        for (const theme of ['light','dark']) {
            await evaluate(`localStorage.setItem('flux.appearance', ${JSON.stringify(theme)})`);
            await send('Emulation.setEmulatedMedia', {features:[{name:'prefers-color-scheme',value:theme}]});
            for (const [width,height] of [[360,640],[390,844],[768,1024],[1440,900],[390,420],[844,390]]) {
                await send('Emulation.setDeviceMetricsOverride', {width,height,deviceScaleFactor:1,mobile:width<1024});
                await navigate('/messages?c='+fixture.conversation);
                await waitFor(`!!document.querySelector('[data-composer]') && !!window.Alpine && document.body.style.height !== ''`);
                await evaluate(`document.documentElement.classList.toggle('dark',${theme==='dark'})`);
                await evaluate(`document.querySelector('[data-composer]').focus()`);
                await send('Input.insertText', {text:'Draft — پیش‌نویس\n'+'LongInputWithoutSpaces'.repeat(24)});
                await delay(150);
                const data = await evaluate(`(() => {
                    const box = selector => { const r=document.querySelector(selector).getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height}; };
                    const list=document.querySelector('[data-message-list]');
                    list.scrollTop=0; list.scrollTop=30;
                    const composer=box('[data-message-composer]'),input=box('[data-composer]'),send=box('[data-send]');
                    const bar=document.querySelector('[data-bottom-bar]');
                    const barTop=getComputedStyle(bar).display==='none'?innerHeight:bar.getBoundingClientRect().top;
                    const hit=document.elementFromPoint(send.left+send.width/2,send.top+send.height/2);
                    const controls=[document.querySelector('[data-message-composer] button'),document.querySelector('[data-voice-button]'),document.querySelector('[data-send]')];
                    const controlsReachable=controls.every(el=>{const r=el.getBoundingClientRect();const hit=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);return r.width>0 && r.left>=0 && r.right<=innerWidth && (hit===el || el.contains(hit));});
                    return {lang:document.documentElement.lang,dir:document.documentElement.dir,dark:document.documentElement.classList.contains('dark'),width:innerWidth,visualHeight:visualViewport.height,bodyWidth:document.body.scrollWidth,documentWidth:document.documentElement.scrollWidth,composer,input,send,barTop,listHeight:list.clientHeight,listScrollable:list.scrollHeight>list.clientHeight && list.scrollTop>0,controlsReachable,sendReachable:!!hit?.closest('[data-send]'),launcher:!!document.querySelector('[data-launcher-fab], [data-launcher-open]'),threadOverflow:document.querySelector('[data-message-thread]').scrollWidth>document.querySelector('[data-message-thread]').clientWidth+1};
                })()`);
                results.push({locale,theme,viewport:[width,height],...data});
                await fs.writeFile(new URL('browser-results.json',out),JSON.stringify({results,errors},null,2));
                assert(data.dir===(locale==='fa'?'rtl':'ltr'), 'Wrong direction');
                assert(data.dark===(theme==='dark'),'Wrong theme');
                assert(data.bodyWidth<=width+1 && data.documentWidth<=width+1,'Horizontal page overflow: '+JSON.stringify(data));
                assert(!data.threadOverflow,'Horizontal thread overflow');
                assert(data.composer.top>=0 && data.composer.bottom<=data.barTop+1 && data.composer.bottom<=data.visualHeight+1,'Composer outside usable viewport: '+JSON.stringify(data));
                assert(data.input.width>40 && data.input.left>=0 && data.send.right<=width+1 && data.sendReachable && data.controlsReachable,'Unreachable composer control: '+JSON.stringify(data));
                assert(data.listScrollable && data.listHeight>0,'History does not scroll internally');
                assert(!data.launcher,'Global launcher overlaps focused workflow');
                if(width===360) {
                    const shot=await send('Page.captureScreenshot',{format:'png'});
                    await fs.writeFile(new URL(`${locale}-${theme}-360.png`,out),Buffer.from(shot.data,'base64'));
                }
            }
        }
    }
    // Exercise real sending, then SPA navigation out of and back into the viewport shell.
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await navigate('/messages?c='+fixture.conversation);
    const sentText='Mobile shell send '+Date.now();
    await evaluate(`document.querySelector('[data-composer]').focus()`);
    await send('Input.insertText',{text:sentText});
    await evaluate(`document.querySelector('[data-send]').click()`);
    await waitFor(`document.querySelector('[data-message-list]').textContent.includes(${JSON.stringify(sentText)}) && document.querySelector('[data-composer]').value === ''`);
    currentPhase='spa-dashboard';
    await evaluate(`document.querySelector('[data-bottom-item="today"]').click()`);
    await waitFor(`location.pathname === '/dashboard' && !document.querySelector('[data-messaging-layout]') && !!document.querySelector('[data-launcher-fab]')`);
    currentPhase='spa-messages';
    await evaluate(`document.querySelector('[data-bottom-item="messages"]').click()`);
    await waitFor(`location.pathname === '/messages' && !!document.querySelector('[data-messaging-layout]') && !document.querySelector('[data-launcher-fab]')`);
    // Resize a mounted thread without reloading, including a keyboard-sized height.
    await navigate('/messages?c='+fixture.conversation);
    for (const height of [420,844]) {
        await send('Emulation.setDeviceMetricsOverride',{width:390,height,deviceScaleFactor:1,mobile:true});
        await delay(250);
        assert(await evaluate(`document.querySelector('[data-message-composer]').getBoundingClientRect().bottom <= visualViewport.height && Math.abs(document.body.getBoundingClientRect().height - visualViewport.height) < 1`),'Mounted viewport resize failed');
    }
    // Legacy context conversations retain document scrolling and reserved chrome space.
    await navigate('/contexts/'+fixture.context+'/conversation');
    await evaluate(`window.scrollTo(0,document.scrollingElement.scrollHeight)`);
    assert(await evaluate(`(() => {const c=document.querySelector('[data-message-composer]').getBoundingClientRect(),b=document.querySelector('[data-bottom-bar]').getBoundingClientRect();return c.top>=0 && c.bottom<=b.top && !document.querySelector('[data-launcher-fab]')})()`),'Legacy composer unreachable after page scrolling');
    console.log(JSON.stringify({layout:'passed',matrix:results.length,sent:true,spaNavigation:true,mountedResize:true,legacyContext:true,runtimeErrors:errors.length}));
    assert(errors.length===0,'Browser runtime errors: '+errors.map(e=>e.exceptionDetails.exception?.description?.split('\n')[0]).join('; '));
} catch(error) {
    const shot=await send('Page.captureScreenshot',{format:'png'});
    await fs.writeFile(new URL('failure.png',out),Buffer.from(shot.data,'base64'));
    throw error;
} finally {
    await fs.writeFile(new URL('browser-results.json',out),JSON.stringify({results,errors},null,2));
    socket.close();
}

// Run against the isolated demo server (8001) and isolated headless Chrome (9225).
import fs from 'node:fs/promises';
const out = new URL('../storage/release-acceptance/clock/', import.meta.url);
await fs.mkdir(out, {recursive:true});
const tabs = await (await fetch('http://127.0.0.1:9225/json')).json();
const socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
await new Promise((resolve,reject) => {socket.onopen=resolve;socket.onerror=reject});
let id=0;
const pending=new Map(), errors=[], results=[];
socket.onmessage=event=>{
    const message=JSON.parse(event.data);
    if(message.id){const request=pending.get(message.id);if(request){pending.delete(message.id);message.error?request.reject(Error(JSON.stringify(message.error))):request.resolve(message.result)}}
    else if(message.method==='Runtime.exceptionThrown' || (message.method==='Network.responseReceived' && message.params.response.status>=400)) errors.push(message);
};
const send=(method,params={})=>new Promise((resolve,reject)=>{const request=++id;pending.set(request,{resolve,reject});socket.send(JSON.stringify({id:request,method,params}))});
const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value};
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const wait=async expression=>{for(let i=0;i<250;i++){await delay(100);if(await evaluate(expression))return}throw Error('Timeout: '+expression)};
const assert=(condition,message)=>{if(!condition)throw Error(message)};
const navigate=async path=>{await send('Page.navigate',{url:'http://127.0.0.1:8001'+path});await wait(`location.pathname===${JSON.stringify(path)} && document.readyState==='complete'`)};
const shot=async name=>{const r=await send('Page.captureScreenshot',{format:'png'});await fs.writeFile(new URL(name+'.png',out),Buffer.from(r.data,'base64'))};
const locale=async value=>evaluate(`fetch('/locale',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]').value},body:JSON.stringify({locale:${JSON.stringify(value)},return_to:'/'})}).then(r=>r.ok)`);
try {
    await send('Page.enable');await send('Runtime.enable');await send('Network.enable');await send('Network.clearBrowserCookies');
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`window.__intervals=new Set();const originalSetInterval=window.setInterval,originalClearInterval=window.clearInterval;window.setInterval=(...args)=>{const id=originalSetInterval(...args);window.__intervals.add(id);return id};window.clearInterval=id=>{window.__intervals.delete(id);return originalClearInterval(id)}`});
    await navigate('/');
    for(const language of ['en','fa','ar','zh_CN']) {
        await locale(language);
        for(const width of [1440,390]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
            await navigate('/');
            await wait(`!!document.querySelector('[data-clock-second]')?.getAttribute('transform')`);
            const state=await evaluate(`({bars:document.querySelectorAll('[data-ambient-bar]').length,faces:document.querySelectorAll('[data-analog-clock]').length,overflow:document.documentElement.scrollWidth>innerWidth+1,text:document.querySelector('.ambient-expanded [data-ambient-clock]').textContent,barTop:document.querySelector('[data-ambient-bar]').getBoundingClientRect().top})`);
            assert(state.bars===1&&state.faces===1&&!state.overflow&&state.barTop===0,'Guest layout: '+JSON.stringify(state));
            const equivalent=await evaluate(`(()=>{const c=document.querySelector('.ambient-expanded');if(c.dataset.calendar==='gregory')return true;const t=c.querySelector('.ambient-time'),e=c.equivalent;const hidden=getComputedStyle(e).display==='none';t.focus();const shown=getComputedStyle(e).display!=='none';t.blur();return hidden&&shown})()`);
            assert(equivalent,'Gregorian equivalent must appear on keyboard focus');
            await shot(`guest-${language}-${width}`);
            await evaluate('window.scrollTo(0,document.body.scrollHeight)');
            assert(await evaluate(`document.querySelector('[data-ambient-bar]').getBoundingClientRect().top===0`),'Clock bar must remain visible while scrolling');
            results.push({kind:'guest',language,width,...state});
        }
    }
    await locale('en');await navigate('/');
    const lifecycle=await evaluate(`(()=>{const clock=document.querySelector('.ambient-expanded'),parent=clock.parentNode,next=clock.nextSibling;const before=window.__intervals.size;let cleaned=true;for(let i=0;i<15;i++){const a=clock.clockTimer,b=clock.messageTimer;clock.remove();cleaned&&=!window.__intervals.has(a)&&!window.__intervals.has(b)&&clock.frame===null&&clock.lifecycle.signal.aborted;parent.insertBefore(clock,next)}return {cleaned,before,after:window.__intervals.size,initialized:clock.initialized}})()`);
    assert(lifecycle.cleaned&&lifecycle.before===lifecycle.after&&lifecycle.initialized,'Clock lifecycle leak: '+JSON.stringify(lifecycle));results.push({kind:'reconnect15',...lifecycle});
    const angles=await evaluate(`(()=>{const c=document.querySelector('.ambient-expanded');c.dataset.timezone='Asia/Tehran';c.renderHands(new Date('2026-10-23T09:10:20.500Z'));return {hour:c.hourHand.getAttribute('transform'),minute:c.minuteHand.getAttribute('transform'),second:c.secondHand.getAttribute('transform')}})()`);
    assert(Math.abs(parseFloat(angles.hour.slice(7))-20.1708333333)<.0001&&Math.abs(parseFloat(angles.minute.slice(7))-242.05)<.0001&&angles.second==='rotate(123 120 120)','Zoned clock geometry: '+JSON.stringify(angles));results.push({kind:'zoned-hands',...angles});
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});await wait(`document.querySelector('.ambient-expanded').frame===null`);
    assert(await evaluate(`document.querySelector('.ambient-expanded').frame===null`),'Reduced motion must stop RAF');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'no-preference'}]});await wait(`document.querySelector('.ambient-expanded').frame!==null`);
    assert(await evaluate(`document.querySelector('.ambient-expanded').frame!==null`),'Animation must resume after reduced motion changes');results.push({kind:'reduced-motion',passed:true});
    await evaluate(`document.querySelector('.ambient-expanded [data-ambient-pause]').click()`);
    assert(await evaluate(`document.querySelector('.ambient-expanded').paused===true`),'Pause rotation control');
    await locale('en');await navigate('/');
    await evaluate(`window.__navigated=0;document.addEventListener('livewire:navigated',()=>window.__navigated++)`);
    await evaluate(`[...document.querySelectorAll('a[href]')].find(a=>a.hasAttribute('wire:navigate')&&a.href.endsWith('/login')).click()`);
    await wait(`location.pathname==='/login' && !!document.querySelector('input[type="password"]')`);
    assert(await evaluate('window.__navigated>0'),'Guest navigation must use Livewire');
    await evaluate(`document.querySelector('input[autocomplete="username"]').focus()`);await send('Input.insertText',{text:'release-browser@example.test'});
    await evaluate(`document.querySelector('input[type="password"]').focus()`);await send('Input.insertText',{text:'Release-browser-fixture-2026!'});
    await evaluate(`document.querySelector('input[type="password"]').closest('form').requestSubmit()`);await wait(`location.pathname==='/dashboard' && document.readyState==='complete'`);
    for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
        for(const path of ['/','/dashboard','/planner','/messages','/profile/professions']) {
            await navigate(path);
            const state=await evaluate(`({bars:document.querySelectorAll('[data-ambient-bar]').length,overflow:document.documentElement.scrollWidth>innerWidth+1,barTop:document.querySelector('[data-ambient-bar]').getBoundingClientRect().top})`);
            assert(state.bars===1&&!state.overflow&&state.barTop===0,'Authenticated layout: '+JSON.stringify(state));
            if(path==='/')await shot('authenticated-'+width);
            results.push({kind:'authenticated',width,path,...state});
        }
    }
    assert(errors.length===0,'Application browser errors: '+JSON.stringify(errors));
    console.log(JSON.stringify({passed:true,checks:results.length}));
} catch(error) {results.push({kind:'failure',error:String(error)});await shot('failure');console.error(String(error));process.exitCode=1}
await fs.writeFile(new URL('results.json',out),JSON.stringify({results,errors},null,2));socket.close();

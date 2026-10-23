// Exercises real rendered forms and controls against the isolated demo on 8002.
import fs from 'node:fs/promises';
const origin = process.env.LIVING_BASE_URL || 'http://127.0.0.1:8002';
const out = new URL('../storage/release-acceptance/living/', import.meta.url);
const credentials = JSON.parse(await fs.readFile(new URL('accounts.json', out)));
const target = await (await fetch('http://127.0.0.1:9225/json/new?about:blank', {method:'PUT'})).json();
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve,reject)=>{socket.onopen=resolve;socket.onerror=reject});
let id=0; const pending=new Map(), errors=[], results=[];
socket.onmessage=event=>{const m=JSON.parse(event.data);if(m.id){const p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result)}}else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text)};
const send=(method,params={})=>new Promise((resolve,reject)=>{const request=++id;pending.set(request,{resolve,reject});socket.send(JSON.stringify({id:request,method,params}))});
const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value};
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const wait=async expression=>{for(let i=0;i<400;i++){if(await evaluate(expression))return;await delay(100)}throw Error('Timeout: '+expression)};
const assert=(value,message)=>{if(!value)throw Error(message)};
const shot=async name=>{const data=await send('Page.captureScreenshot',{format:'png'});await fs.writeFile(new URL(name+'.png',out),Buffer.from(data.data,'base64'))};
const navigate=async path=>{await send('Page.navigate',{url:origin+path});await wait(`document.readyState==='complete' && location.pathname===${JSON.stringify(path.split('?')[0])}`);await wait('!!window.Livewire')};
const field=async(name,value)=>{assert(await evaluate(`(()=>{const el=[...document.querySelectorAll('input,textarea,select')].find(e=>e.getAttribute('wire:model')===${JSON.stringify(name)}||e.getAttribute('wire:model.live')===${JSON.stringify(name)});if(!el)return false;let ancestor=el.parentElement;while(ancestor){if(ancestor.tagName==='DETAILS')ancestor.open=true;ancestor=ancestor.parentElement}el.value=${JSON.stringify(value)};el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}));return true})()`),'Missing field '+name)};
const submit=async method=>{await evaluate(`[...document.forms].find(e=>e.getAttribute('wire:submit')===${JSON.stringify(method)}).requestSubmit()`);await delay(400);await wait(`![...document.querySelectorAll('button')].some(e=>e.hasAttribute('wire:loading.attr')&&e.disabled)`)};
try{
 await send('Page.enable');await send('Runtime.enable');await send('Network.enable');await send('Network.clearBrowserCookies');
 await navigate('/login');
 await evaluate(`(()=>{for(const [selector,value] of [['input[autocomplete="username"]',${JSON.stringify(credentials.owner)}],['input[type="password"]',${JSON.stringify(credentials.password)}]]){const el=document.querySelector(selector);el.value=value;el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}))}document.querySelector('input[type="password"]').closest('form').requestSubmit()})()`);
 await wait(`location.pathname==='/dashboard'`);await navigate('/plans');await wait(`!!document.querySelector('[data-living-planner]')`);
 results.push({scenario:'authenticated Plans entry',passed:true});
 for(const locale of (process.env.LIVING_SKIP_LAYOUT ? [] : ['en','fa','ar','zh_CN'])){
  await evaluate(`fetch('/locale',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({locale:${JSON.stringify(locale)},return_to:'/plans'})}).then(r=>{if(!r.ok)throw Error('Locale rejected')})`);
  for(const width of [1440,390]){
   await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
   for(const tab of ['today','routines','purposes','insights','history']){
    await navigate('/plans?tab='+tab);await wait(`!!document.querySelector('[data-living-planner]')`);
    const state=await evaluate(`({overflow:document.documentElement.scrollWidth>innerWidth+1,bars:document.querySelectorAll('[data-ambient-bar]').length,raw:document.body.innerText.includes('living_planner.')})`);
    assert(!state.overflow&&state.bars===1&&!state.raw,JSON.stringify({locale,width,tab,...state}));results.push({scenario:'layout',locale,width,tab,passed:true});
   }
   await shot(locale+'-'+width);
  }
 }
 await evaluate(`fetch('/locale',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({locale:'en',return_to:'/plans'})})`);
 await navigate('/plans');
 const title='Browser execution '+Date.now();await field('newTitle',title);await field('newFrequency','once');await submit('createActivity');await wait(`document.body.innerText.includes(${JSON.stringify(title)})`);
 const article=`[...document.querySelectorAll('[data-living-occurrence]')].find(e=>e.innerText.includes(${JSON.stringify(title)}))`;
 const click=async action=>{await evaluate(`(()=>{const card=${article};const button=[...card.querySelectorAll('button')].find(b=>(b.getAttribute('wire:click')||'').includes("'${action}'"));if(!button||button.disabled)throw Error('Unavailable ${action}');button.click()})()`);await delay(500)};
 await click('start');await wait(`${article}?.innerText.includes('Pause')`);
 await click('pause');await wait(`${article}?.innerText.includes('Resume')`);
 await click('resume');await wait(`${article}?.innerText.includes('Pause')`);
 await field('finishOutcome','partial');await click('finish');await wait(`${article}?.innerText.includes('Partially completed') || ${article}?.innerText.includes('Partial')`);
 results.push({scenario:'create/start/pause/resume/partial finish through controls',passed:true});
 await evaluate(`[...(${article}).querySelectorAll('button')].find(b=>(b.getAttribute('wire:click')||'').startsWith('selectReport')).click()`);
 await wait(`document.body.innerText.includes('Correcting the latest record')`);
 await field('correctionReason','Browser correction retains the original report.');await submit('report');
 await navigate('/plans?tab=history');await wait(`document.body.innerText.includes('Browser correction retains the original report.')`);
 results.push({scenario:'guided historical correction without UUID input',passed:true});
 await navigate('/plans?tab=routines');
 const planUuid=await evaluate(`[...document.querySelectorAll('option')].find(o=>o.textContent===${JSON.stringify(title)})?.value`);assert(planUuid,'Created plan selector');
 await field('planUuid',planUuid);await delay(800);const routine='Browser routine '+Date.now();await field('routineName',routine);await submit('capture');await wait(`document.body.innerText.includes(${JSON.stringify(routine)})`);
 results.push({scenario:'capture reusable routine through form',passed:true});
 await navigate('/plans?tab=insights');await field('planUuid',planUuid);await delay(800);
 const hypothesis='Browser hypothesis '+Date.now();await field('experimentForm.title','Browser private experiment');await field('experimentForm.hypothesis',hypothesis);await field('experimentForm.selected_change','Prepare shoes the night before');await submit('createExperiment');await wait(`document.body.innerText.includes(${JSON.stringify(hypothesis)})`);
 await field('observationForm.value','12');await field('observationForm.reflection','Browser private reflection');await submit('observe');await wait(`document.body.innerText.includes('Browser private reflection')`);
 results.push({scenario:'private experiment and observation through forms',passed:true});
 await navigate('/plans?tab=purposes');await field('purposeTitle','Browser purpose '+Date.now());await field('purposeDescription','A private browser acceptance goal.');await submit('createPurpose');await wait(`document.body.innerText.includes('A private browser acceptance goal.')`);
 results.push({scenario:'create purpose through form',passed:true});
 await navigate('/planner?view=calendar');assert(await evaluate(`document.body.innerText.includes('Recurrence in this period')`),'Recurrence summary missing');results.push({scenario:'calendar connected',passed:true});
 assert(errors.length===0,'Browser exceptions: '+JSON.stringify(errors));await shot('final');
 console.log(JSON.stringify({passed:true,checks:results.length}));
}catch(error){results.push({scenario:'failure',error:String(error)});await shot('failure');console.error(String(error));process.exitCode=1}
await fs.writeFile(new URL('browser-results.json',out),JSON.stringify({results,errors},null,2));socket.close();

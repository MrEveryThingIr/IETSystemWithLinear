<style>
    .media-preview{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;margin-top:14px}
    .media-preview img{width:100%;height:95px;object-fit:cover;border-radius:12px;border:1px solid #cbd5e1}
    .recorder-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px}
    .recorder{border:1px solid #cbd5e1;background:#f8fafc;border-radius:18px;padding:16px}
    .recorder-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
    .record-btn,.stop-btn,.remove-btn{width:auto!important;border:0!important;border-radius:12px;padding:10px 13px;font-weight:900;cursor:pointer}
    .record-btn{background:#047857!important;color:#fff!important}.stop-btn{background:#0f172a!important;color:#fff!important}
    .remove-btn{background:#fff1f2!important;color:#be123c!important;border:1px solid #fecdd3!important}
    .record-btn:disabled,.stop-btn:disabled{opacity:.45;cursor:not-allowed}
    @media(max-width:700px){.recorder-grid{grid-template-columns:1fr}}
</style>

<section class="section">
    <h2>۶) عکس، صدا و ویدئو <span style="font-size:13px;color:#64748b;font-weight:600">(اختیاری)</span></h2>
    <p class="hint">چند عکس/ویدئو انتخاب کنید یا همین‌جا توضیح صوتی و ویدئو ضبط کنید.</p>

    <div class="grid">
        <label><span>گالری تصاویر</span>
            <input id="imagesInput" type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple>
            <small class="hint">حداکثر ۲۰ تصویر؛ هر تصویر تا ۱۲MB</small>
        </label>
        <label><span>ویدئو از دستگاه</span>
            <input type="file" name="videos[]" accept="video/mp4,video/webm,video/quicktime" multiple>
            <small class="hint">حداکثر ۶ ویدئو؛ هر ویدئو تا ۱۰۰MB</small>
        </label>
        <label><span>فایل صوتی</span>
            <input type="file" name="audios[]" accept="audio/*" multiple>
            <small class="hint">حداکثر ۶ فایل؛ هر فایل تا ۲۵MB</small>
        </label>
        <label><span>فیلم مستقیم با دوربین گوشی</span>
            <input type="file" name="videos[]" accept="video/*" capture="environment">
            <small class="hint">راه جایگزین ساده برای مرورگرهایی که ضبط درون‌صفحه‌ای ندارند.</small>
        </label>
    </div>

    <div id="imagePreview" class="media-preview"></div>

    <div class="recorder-grid">
        <div class="recorder">
            <strong>🎙 ضبط توضیح صوتی</strong>
            <p class="hint">برای توضیح مالک/مراجعه‌کننده درباره ملک.</p>
            <div class="recorder-actions">
                <button type="button" class="record-btn" id="startAudio">شروع ضبط صدا</button>
                <button type="button" class="stop-btn" id="stopAudio" disabled>پایان ضبط</button>
                <button type="button" class="remove-btn" id="removeAudio" hidden>حذف ضبط</button>
            </div>
            <audio id="audioPreview" controls hidden style="width:100%;margin-top:10px"></audio>
            <input id="recordedAudio" type="file" name="recorded_audio" hidden>
            <div id="audioState" class="hint"></div>
        </div>

        <div class="recorder">
            <strong>🎥 ضبط ویدئو</strong>
            <p class="hint">مثلاً یک فیلم کوتاه از ملک یا توضیح مراجعه‌کننده.</p>
            <div class="recorder-actions">
                <button type="button" class="record-btn" id="startVideo">شروع ضبط ویدئو</button>
                <button type="button" class="stop-btn" id="stopVideo" disabled>پایان ضبط</button>
                <button type="button" class="remove-btn" id="removeVideo" hidden>حذف ضبط</button>
            </div>
            <video id="videoLive" playsinline muted hidden style="width:100%;margin-top:10px;border-radius:14px;background:#000"></video>
            <video id="videoPreview" controls playsinline hidden style="width:100%;margin-top:10px;border-radius:14px;background:#000"></video>
            <input id="recordedVideo" type="file" name="recorded_video" hidden>
            <div id="videoState" class="hint"></div>
        </div>
    </div>
</section>

<script>
(() => {
    const imagesInput = document.getElementById('imagesInput');
    const imagePreview = document.getElementById('imagePreview');
    imagesInput?.addEventListener('change', () => {
        imagePreview.innerHTML = '';
        [...imagesInput.files].slice(0, 20).forEach(file => {
            if (!file.type.startsWith('image/')) return;
            const url = URL.createObjectURL(file);
            const img = document.createElement('img');
            img.alt = file.name; img.src = url;
            img.onload = () => URL.revokeObjectURL(url);
            imagePreview.appendChild(img);
        });
    });

    const supportedMime = candidates => candidates.find(t => window.MediaRecorder && MediaRecorder.isTypeSupported(t)) || '';
    const attachBlob = (input, blob, name) => {
        const file = new File([blob], name, {type: blob.type || 'application/octet-stream'});
        const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
    };
    const clearFile = input => { const dt = new DataTransfer(); input.files = dt.files; };

    let ar, as, ac = [];
    const aStart=document.getElementById('startAudio'), aStop=document.getElementById('stopAudio'), aRemove=document.getElementById('removeAudio');
    const aPreview=document.getElementById('audioPreview'), aInput=document.getElementById('recordedAudio'), aState=document.getElementById('audioState');
    aStart?.addEventListener('click', async () => {
        try {
            as = await navigator.mediaDevices.getUserMedia({audio:true}); ac=[];
            const mime = supportedMime(['audio/webm;codecs=opus','audio/webm','audio/ogg;codecs=opus','audio/mp4']);
            ar = mime ? new MediaRecorder(as,{mimeType:mime}) : new MediaRecorder(as);
            ar.ondataavailable=e=>{if(e.data.size) ac.push(e.data)};
            ar.onstop=()=>{
                const blob=new Blob(ac,{type:ar.mimeType||'audio/webm'}); attachBlob(aInput,blob,'recorded-audio.webm');
                aPreview.src=URL.createObjectURL(blob); aPreview.hidden=false; aRemove.hidden=false; aState.textContent='ضبط صدا آماده ارسال است.';
                as?.getTracks().forEach(t=>t.stop());
            };
            ar.start(); aStart.disabled=true; aStop.disabled=false; aState.textContent='در حال ضبط صدا...';
        } catch(e) { aState.textContent='مرورگر اجازه میکروفن نداد یا از ضبط پشتیبانی نمی‌کند.'; }
    });
    aStop?.addEventListener('click',()=>{if(ar?.state==='recording') ar.stop(); aStart.disabled=false; aStop.disabled=true});
    aRemove?.addEventListener('click',()=>{clearFile(aInput);aPreview.removeAttribute('src');aPreview.load();aPreview.hidden=true;aRemove.hidden=true;aState.textContent='ضبط حذف شد.'});

    let vr, vs, vc=[];
    const vStart=document.getElementById('startVideo'),vStop=document.getElementById('stopVideo'),vRemove=document.getElementById('removeVideo');
    const vLive=document.getElementById('videoLive'),vPreview=document.getElementById('videoPreview'),vInput=document.getElementById('recordedVideo'),vState=document.getElementById('videoState');
    vStart?.addEventListener('click',async()=>{
        try {
            vs=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:true}); vc=[];
            vLive.srcObject=vs;vLive.hidden=false;await vLive.play();
            const mime=supportedMime(['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm','video/mp4']);
            vr=mime?new MediaRecorder(vs,{mimeType:mime}):new MediaRecorder(vs);
            vr.ondataavailable=e=>{if(e.data.size) vc.push(e.data)};
            vr.onstop=()=>{
                const blob=new Blob(vc,{type:vr.mimeType||'video/webm'});attachBlob(vInput,blob,'recorded-video.webm');
                vPreview.src=URL.createObjectURL(blob);vPreview.hidden=false;vRemove.hidden=false;vLive.hidden=true;vLive.srcObject=null;
                vState.textContent='ضبط ویدئو آماده ارسال است.';vs?.getTracks().forEach(t=>t.stop());
            };
            vr.start();vStart.disabled=true;vStop.disabled=false;vState.textContent='در حال ضبط ویدئو...';
        } catch(e){vState.textContent='مرورگر اجازه دوربین/میکروفن نداد یا از ضبط پشتیبانی نمی‌کند.'}
    });
    vStop?.addEventListener('click',()=>{if(vr?.state==='recording')vr.stop();vStart.disabled=false;vStop.disabled=true});
    vRemove?.addEventListener('click',()=>{clearFile(vInput);vPreview.removeAttribute('src');vPreview.load();vPreview.hidden=true;vRemove.hidden=true;vState.textContent='ضبط حذف شد.'});

    document.querySelector('form')?.addEventListener('submit',e=>{
        if(ar?.state==='recording'||vr?.state==='recording'){
            e.preventDefault(); alert('ابتدا ضبط صدا/ویدئو را متوقف کنید، سپس پرونده را ثبت کنید.');
        }
    });
})();
</script>

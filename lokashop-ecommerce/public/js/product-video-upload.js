/* Browser-only product video resizing. No external downloads, worker, WASM, or FFmpeg. */
(() => {
  const MAX_UPLOAD = 20 * 1024 * 1024;
  const MAX_SOURCE = 100 * 1024 * 1024;
  function validateDuration(duration) {
    if (!Number.isFinite(duration) || duration <= 0) throw new Error('This video has no readable duration.');
    if (duration > 180) throw new Error('Product videos must be 3 minutes or shorter. Choose a shorter video.');
  }
  function dimensions(width, height) {
    const landscape = width >= height;
    const ratio = Math.min(1, (landscape ? 748 : 420) / width, (landscape ? 420 : 748) / height);
    return {width: Math.max(2, Math.floor(width * ratio / 2) * 2), height: Math.max(2, Math.floor(height * ratio / 2) * 2), resize: ratio < 1};
  }
  function listen(target, event, signal, timeout = 20000) {
    return new Promise((resolve, reject) => {
      const done = () => { cleanup(); resolve(); };
      const fail = () => { cleanup(); reject(new Error('This browser could not read the video. Use a playable MP4 or WebM.')); };
      const abort = () => { cleanup(); reject(new DOMException('Cancelled', 'AbortError')); };
      const timer = setTimeout(fail, timeout);
      function cleanup() { clearTimeout(timer); target.removeEventListener(event, done); target.removeEventListener('error', fail); signal.removeEventListener('abort', abort); }
      target.addEventListener(event, done, {once: true}); target.addEventListener('error', fail, {once: true}); signal.addEventListener('abort', abort, {once: true});
      if (signal.aborted) abort();
    });
  }
  async function prepare(file, signal, report) {
    const video = document.createElement('video');
    video.preload = 'auto'; video.muted = true; video.playsInline = true;
    const url = URL.createObjectURL(file);
    let context, recorder, stream, animation, sourceNode;
    let stopEncoding = () => {};
    try {
      const metadata = listen(video, 'loadedmetadata', signal); video.src = url; await metadata;
      validateDuration(video.duration);
      if (!video.videoWidth || !video.videoHeight) throw new Error('This video has no readable dimensions.');
      const target = dimensions(video.videoWidth, video.videoHeight);
      if (!target.resize && file.size <= MAX_UPLOAD) return {file, label: `Ready · ${video.videoWidth} × ${video.videoHeight} · already within 420p`};
      if (!window.MediaRecorder || !HTMLCanvasElement.prototype.captureStream || !window.DataTransfer) throw new Error('This browser cannot resize videos. Use a current Chrome or Edge browser, or upload a video already at 420p or lower.');
      const mime = ['video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4;codecs=avc1.42E01E,mp4a.40.2'].find(type => MediaRecorder.isTypeSupported(type) && video.canPlayType(type));
      if (!mime) throw new Error('No supported browser video encoder is available. Try Chrome or Edge.');
      if (document.hidden) throw new Error('Keep this tab open while preparing the video.');
      if (video.readyState < 2) await listen(video, 'loadeddata', signal);
      const canvas = document.createElement('canvas'); canvas.width = target.width; canvas.height = target.height;
      const painter = canvas.getContext('2d', {alpha: false});
      if (!painter) throw new Error('Video resizing is unavailable in this browser.');
      stream = canvas.captureStream(24);
      const Audio = window.AudioContext || window.webkitAudioContext;
      if (!Audio) throw new Error('This browser cannot preserve video audio during compression. Try Chrome or Edge.');
      context = new Audio(); sourceNode = context.createMediaElementSource(video);
      const audioOutput = context.createMediaStreamDestination(); sourceNode.connect(audioOutput);
      audioOutput.stream.getAudioTracks().forEach(track => stream.addTrack(track));
      await context.resume();
      if (context.state !== 'running') throw new Error('Audio preparation was blocked. Select the video again and keep this tab open.');
      const chunks = [];
      recorder = new MediaRecorder(stream, {mimeType: mime, videoBitsPerSecond: Math.max(180000, Math.min(1600000, Math.floor(file.size * 8 / video.duration * 0.75), Math.floor(MAX_UPLOAD * 8 / video.duration * 0.85) - 64000)), audioBitsPerSecond: 64000});
      const encoded = new Promise((resolve, reject) => {
        let timeout;
        const cleanup = () => { clearTimeout(timeout); signal.removeEventListener('abort', abort); document.removeEventListener('visibilitychange', hidden); video.removeEventListener('ended', end); video.removeEventListener('error', fail); };
        stopEncoding = cleanup;
        const abort = () => { cleanup(); reject(new DOMException('Cancelled', 'AbortError')); };
        const hidden = () => { if (document.hidden) { cleanup(); reject(new Error('Compression stopped because the tab was hidden. Select the video again and keep this tab open.')); } };
        const fail = () => { cleanup(); reject(new Error('Video compression failed. Try another MP4 or WebM.')); };
        const end = () => { painter.drawImage(video, 0, 0, target.width, target.height); if (recorder.state !== 'inactive') recorder.stop(); };
        recorder.ondataavailable = event => { if (event.data.size) chunks.push(event.data); };
        recorder.onerror = fail;
        recorder.onstop = () => { cleanup(); resolve(new Blob(chunks, {type: mime.split(';')[0]})); };
        video.addEventListener('ended', end, {once: true}); video.addEventListener('error', fail, {once: true}); signal.addEventListener('abort', abort, {once: true}); document.addEventListener('visibilitychange', hidden);
        timeout = setTimeout(fail, video.duration * 2000 + 30000);
        recorder.start(1000);
      });
      // Attach error handling before starting playback; clean up a blocked play attempt too.
      const playback = video.play();
      function draw() { if (!signal.aborted && !video.ended) { painter.drawImage(video, 0, 0, target.width, target.height); report(Math.min(99, Math.floor(video.currentTime / video.duration * 100))); animation = requestAnimationFrame(draw); } }
      draw();
      const [blob] = await Promise.all([encoded, playback]);
      if (signal.aborted) throw new DOMException('Cancelled', 'AbortError');
      if (!blob.size || blob.size > MAX_UPLOAD) throw new Error('The compressed video is still over 20 MB. Choose a shorter video.');
      const extension = mime.startsWith('video/mp4') ? 'mp4' : 'webm';
      const output = new File([blob], file.name.replace(/\.[^.]+$/, '') + '-420p.' + extension, {type: blob.type, lastModified: Date.now()});
      // Decode a frame of the generated file before replacing the upload selection.
      const probe = document.createElement('video'); probe.muted = true; probe.playsInline = true;
      const probeUrl = URL.createObjectURL(output);
      try {
        const decoded = listen(probe, 'loadeddata', signal, 20000); probe.preload = 'auto'; probe.src = probeUrl; await decoded;
        if (!probe.videoWidth || !probe.videoHeight || probe.videoWidth > target.width || probe.videoHeight > target.height) throw new Error('The browser could not create a valid 420p video. Select the original video again or try Chrome/Edge.');
      } finally { probe.removeAttribute('src'); probe.load(); URL.revokeObjectURL(probeUrl); }

      return {file: output, label: `Ready · ${target.width} × ${target.height} · ${(output.size / 1048576).toFixed(1)} MB`};
    } finally {
      stopEncoding(); cancelAnimationFrame(animation); video.pause();
      if (recorder?.state !== undefined && recorder.state !== 'inactive') { recorder.onstop = null; recorder.stop(); }
      stream?.getTracks().forEach(track => track.stop()); sourceNode?.disconnect();
      if (context) await context.close().catch(() => {});
      video.removeAttribute('src'); video.load(); URL.revokeObjectURL(url);
    }
  }
  document.querySelectorAll('form input[type="file"][name="video"]').forEach(input => {
    const form = input.form;
    const area = document.createElement('div'); area.className = 'video-upload-status';
    const text = document.createElement('p'); text.setAttribute('role', 'status'); text.setAttribute('aria-live', 'polite');
    const progress = document.createElement('progress'); progress.max = 100; progress.hidden = true; progress.setAttribute('aria-label', 'Video compression progress');
    const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn alt'; cancel.textContent = 'Cancel video preparation'; cancel.hidden = true;
    area.append(text, progress, cancel); input.closest('label').after(area);
    let controller, revision = 0;
    cancel.addEventListener('click', () => { controller?.abort(); revision++; input.value = ''; input.setCustomValidity(''); text.textContent = 'Video removed. Select another video if needed.'; progress.hidden = cancel.hidden = true; });
    input.closest('dialog')?.addEventListener('close', () => { if (cancel.hidden === false) cancel.click(); });
    input.addEventListener('change', async () => {
      controller?.abort(); controller = new AbortController(); const current = ++revision;
      const file = input.files?.[0]; input.setCustomValidity('');
      progress.hidden = cancel.hidden = true; text.textContent = '';
      if (!file) return;
      if (!/\.(mp4|webm)$/i.test(file.name) || file.size > MAX_SOURCE) { input.setCustomValidity('Choose an MP4 or WebM source video up to 100 MB.'); text.textContent = input.validationMessage; return; }
      input.setCustomValidity('Please wait until video preparation finishes.'); text.textContent = 'Checking video…'; cancel.hidden = false;
      try {
        const result = await prepare(file, controller.signal, value => { if (current === revision) { progress.hidden = false; progress.value = value; text.textContent = `Compressing video: ${value}% — keep this tab open. This can take about the video’s running time.`; } });
        if (current !== revision) return;
        if (result.file !== file) { const transfer = new DataTransfer(); transfer.items.add(result.file); input.files = transfer.files; }
        input.setCustomValidity(''); text.textContent = result.label;
      } catch (error) {
        if (current !== revision) return;
        input.setCustomValidity(error.name === 'AbortError' ? 'Select the video again to prepare it.' : error.message); text.textContent = input.validationMessage;
      } finally { if (current === revision) progress.hidden = cancel.hidden = true; }
    });
    // Invalid or unfinished preparation is blocked even by scripts using requestSubmit().
    form.addEventListener('submit', event => { if (input.validationMessage) { event.preventDefault(); input.reportValidity(); } });
  });
})();

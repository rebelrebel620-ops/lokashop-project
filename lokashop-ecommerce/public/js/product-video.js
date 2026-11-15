(() => {
  const video = document.getElementById('product-main-video');
  const image = document.getElementById('product-main-image');
  const thumbnail = document.querySelector('[data-product-video]');
  const player = document.querySelector('[data-product-player]');
  const frame = document.querySelector('[data-product-media-frame]');
  if (!video || !image || !thumbnail || !player || !frame) return;
  const toggles = [...player.querySelectorAll('[data-video-toggle]')];
  const mute = player.querySelector('[data-video-mute]');
  const message = player.querySelector('[data-video-message]');
  function state() {
    const playing = !video.paused && !video.ended;
    player.classList.toggle('is-playing', playing);
    toggles.forEach(button => {
      button.setAttribute('aria-label', playing ? 'Pause product video' : 'Play product video');
      if (!button.classList.contains('product-video-overlay')) button.textContent = playing ? 'Pause' : (video.ended ? 'Replay' : 'Play');
    });
    mute.textContent = video.muted ? 'Unmute' : 'Mute';
    mute.setAttribute('aria-label', video.muted ? 'Unmute product video' : 'Mute product video');
    mute.setAttribute('aria-pressed', String(video.muted));
  }
  let attempt = 0;
  async function failure(error, current) {
    if (current !== attempt || player.hidden) return;
    if (error?.name === 'AbortError') return;
    let detail = 'This video could not be played. Please reload the page or ask the seller to upload the original video again.';
    try {
      const response = await fetch(video.currentSrc || video.src, {method: 'HEAD', credentials: 'same-origin', cache: 'no-store'});
      if (response.status === 404) detail = 'The video file is missing or unavailable. The seller needs to upload it again.';
      else if (!response.ok) detail = 'The video could not be loaded. Please reload the page and try again.';
      else if (video.error?.code === 3 || video.error?.code === 4 || error?.name === 'NotSupportedError') detail = 'This video file cannot be decoded by the browser. The seller should upload the original video again.';
    } catch { detail = 'The video could not be loaded. Check your connection and try Play again.'; }
    if (current === attempt && !player.hidden) { message.textContent = detail; message.hidden = false; }
  }
  async function play() {
    const current = ++attempt;
    message.hidden = true;
    if (video.error) video.load();
    if (video.ended) video.currentTime = 0;
    try { await video.play(); }
    catch (error) {
      if (error.name === 'NotAllowedError') {
        video.muted = true;
        try { await video.play(); } catch (retry) { await failure(retry, current); }
      } else await failure(error, current);
    }
    state();
  }
  thumbnail.addEventListener('click', () => {
    frame.classList.add('show-product-video'); player.hidden = false;
    thumbnail.setAttribute('aria-pressed', 'true');
    document.querySelectorAll('[data-gallery-image]').forEach(button => button.setAttribute('aria-pressed', 'false'));
    play();
  });
  function photo() {
    attempt++; video.pause(); player.hidden = true; frame.classList.remove('show-product-video');
    thumbnail.setAttribute('aria-pressed', 'false'); message.hidden = true;
  }
  toggles.forEach(button => button.addEventListener('click', () => video.paused || video.ended ? play() : video.pause()));
  mute.addEventListener('click', () => { video.muted = !video.muted; state(); });
  ['play', 'pause', 'ended', 'volumechange'].forEach(event => video.addEventListener(event, state));
  video.addEventListener('error', () => { failure(video.error, attempt); state(); });
  video.addEventListener('contextmenu', event => event.preventDefault());
  video.addEventListener('dblclick', event => event.preventDefault());
  document.querySelectorAll('[data-gallery-image]').forEach(button => button.addEventListener('click', photo));
  document.querySelector('[name="variation_id"]')?.addEventListener('change', photo);
  document.addEventListener('visibilitychange', () => { if (document.hidden) video.pause(); });
  state();
})();

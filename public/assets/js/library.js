// Media Library: chunked uploads (same protocol as the composer), then keep the files in the library.
(() => {
  const root = document.querySelector('[data-lib]');
  if (!root) return;
  const token = root.dataset.token;
  const input = root.querySelector('[data-lib-file]');
  const drop = root.querySelector('[data-lib-drop]');
  const status = root.querySelector('[data-lib-status]');
  const bar = root.querySelector('[data-lib-progress]');
  const TYPES = { 'image/jpeg': 'image', 'image/png': 'image', 'image/webp': 'image', 'image/gif': 'image', 'video/mp4': 'video', 'video/quicktime': 'video', 'video/webm': 'video' };
  const MAX = { image: 10 * 1024 * 1024, video: 200 * 1024 * 1024 };
  const mb = (n) => `${(n / 1048576).toFixed(1)} MB`;
  const json = async (res) => {
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.status !== 'ok') throw new Error(data.message || 'Upload failed. Please try again.');
    return data;
  };
  let busy = false;

  const uploadOne = async (file, index, total) => {
    const kind = TYPES[file.type];
    if (!kind) throw new Error(`${file.name}: upload a JPG, PNG, WebP or GIF image, or an MP4, MOV or WebM video.`);
    if (file.size > MAX[kind]) throw new Error(`${file.name}: ${kind === 'image' ? 'images can be up to 10 MB' : 'videos can be up to 200 MB'}.`);
    const start = new FormData();
    start.set('_token', token);
    start.set('name', file.name);
    start.set('size', String(file.size));
    start.set('type', file.type);
    const opened = await json(await fetch('/app/media', { method: 'POST', body: start, headers: { Accept: 'application/json' }, credentials: 'same-origin' }));
    let offset = 0;
    while (offset < file.size) {
      const sent = await json(await fetch(`/app/media/${opened.id}/chunk`, {
        method: 'POST', body: file.slice(offset, offset + opened.chunk), credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/octet-stream', 'X-CSRF-Token': token, 'X-Upload-Offset': String(offset) },
      }));
      offset = sent.offset;
      bar.firstElementChild.style.width = `${Math.round((offset / file.size) * 100)}%`;
      status.textContent = `Uploading ${index + 1} of ${total}: ${file.name} (${mb(offset)} of ${mb(file.size)})`;
    }
    const done = new FormData();
    done.set('_token', token);
    const result = await json(await fetch(`/app/media/${opened.id}/complete`, { method: 'POST', body: done, headers: { Accept: 'application/json' }, credentials: 'same-origin' }));
    return result.media.id;
  };

  const uploadAll = async (files) => {
    if (busy || !files.length) return;
    busy = true;
    bar.hidden = false;
    const ids = [];
    const errors = [];
    for (let i = 0; i < files.length; i++) {
      try {
        ids.push(await uploadOne(files[i], i, files.length));
      } catch (err) {
        errors.push(err.message);
      }
    }
    if (ids.length) {
      const keep = new FormData();
      keep.set('_token', token);
      keep.set('action', 'keep');
      ids.forEach((id) => keep.append('ids[]', String(id)));
      await fetch('/app/library', { method: 'POST', body: keep, credentials: 'same-origin' }).catch(() => {});
    }
    busy = false;
    bar.hidden = true;
    if (errors.length) {
      status.textContent = errors.join(' ') + (ids.length ? ` ${ids.length} uploaded. Refresh to see them.` : '');
      status.classList.add('is-error');
      if (ids.length) setTimeout(() => window.location.reload(), 2500);
      return;
    }
    window.location.reload();
  };

  input.addEventListener('change', () => { uploadAll([...input.files]); input.value = ''; });
  ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
  ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, () => drop.classList.remove('is-over')));
  drop.addEventListener('drop', (e) => { e.preventDefault(); uploadAll([...e.dataTransfer.files]); });
  window.addEventListener('beforeunload', (e) => { if (busy) e.preventDefault(); });
})();

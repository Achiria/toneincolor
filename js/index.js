const PROXY = '/git/photos.php';

let photos     = [];
let lgInstance = null;

// ── Fetch ─────────────────────────────────────────────────
async function fetchPhotos() {
  const res  = await fetch(PROXY);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const json = await res.json();
  if (!json.success) throw new Error(json.error || 'Could not load photos');
  return json.data?.list || [];
}

// ── Fisher-Yates shuffle ──────────────────────────────────
function shuffle(arr) {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

// ── Filter buttons ────────────────────────────────────────
function buildFilters(list) {
  const allTags = new Set(list.flatMap(p => p.tags || []));
  if (!allTags.size) return;
  const bar = document.getElementById('filter-bar');
  [...allTags].sort().forEach(tag => {
    const btn = document.createElement('button');
    btn.className      = 'filter-btn';
    btn.dataset.filter = tag;
    btn.textContent    = tag.charAt(0).toUpperCase() + tag.slice(1);
    btn.addEventListener('click', () => applyFilter(tag));
    bar.appendChild(btn);
  });
}

function applyFilter(tag) {
  document.querySelectorAll('.filter-btn').forEach(b =>
    b.classList.toggle('active', b.dataset.filter === tag)
  );
  const filtered = tag === 'all'
    ? photos
    : photos.filter(p => (p.tags || []).includes(tag));
  renderPhotos(filtered);
}

// ── Render grid + init lightGallery ───────────────────────
function renderPhotos(list) {
  const grid = document.getElementById('grid');

  if (lgInstance) { lgInstance.destroy(); lgInstance = null; }
  grid.innerHTML = '';

  if (!list.length) {
    grid.innerHTML = `<div class="state-message">
      <div class="icon">◻</div>
      <h2>No photos found</h2>
      <p>Upload images to the <code>/photos</code> folder on your server.</p>
    </div>`;
    return;
  }

  list.forEach(photo => {
    const a = document.createElement('a');
    a.href            = photo.full;
    a.dataset.src     = photo.full;
    a.dataset.thumb   = photo.thumb;
    a.dataset.subHtml = `<p>${photo.caption}</p>`;
    a.className       = 'grid-item';

    a.innerHTML = `
      <div class="grid-item-inner">
        <img src="${photo.thumb}" alt="${photo.caption}" loading="lazy" />
        <div class="overlay">
          <div class="overlay-meta">
            <strong>${photo.caption}</strong>
          </div>
        </div>
      </div>`;

    grid.appendChild(a);
  });

  lgInstance = lightGallery(grid, {
    selector:             'a.grid-item',
    plugins:              [lgZoom, lgThumbnail, lgFullscreen],
    speed:                400,
    download:             false,
    counter:              true,
    escKey:               true,
    keyPress:             true,
    hideScrollbar:        true,
    zoom:                 true,
    actualSize:           false,
    showZoomInOutIcons:   true,
    thumbnail:            true,
    animateThumb:         true,
    currentPagerPosition: 'middle',
    thumbWidth:           80,
    thumbHeight:          '60px',
    toggleThumb:          true,
  });
}

function renderError(msg) {
  document.getElementById('grid').innerHTML = `<div class="state-message">
    <div class="icon">⊗</div>
    <h2>Could not load photos</h2>
    <p>${msg}</p>
    <p>Check that <code>photos.php</code> is uploaded alongside <code>index.html</code>.</p>
  </div>`;
}

// ── Boot ─────────────────────────────────────────────────
document.getElementById('yr').textContent = new Date().getFullYear();

(async () => {
  try {
    photos = shuffle(await fetchPhotos());
    document.getElementById('photo-count').textContent =
      `${photos.length} photo${photos.length !== 1 ? 's' : ''}`;
    buildFilters(photos);
    renderPhotos(photos);
  } catch (err) {
    console.error('Gallery error:', err);
    renderError(err.message);
  }
})();
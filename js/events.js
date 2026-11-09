const EVENTS_ENDPOINT = './events.php';
const galleryOptions = {
  plugins: [lgZoom, lgThumbnail, lgFullscreen],
  speed: 400,
  download: false,
  counter: true,
  escKey: true,
  keyPress: true,
  hideScrollbar: true,
  zoom: true,
  actualSize: false,
  showZoomInOutIcons: true,
  thumbnail: true,
  animateThumb: true,
  currentPagerPosition: 'middle',
  thumbWidth: 80,
  thumbHeight: '60px',
  toggleThumb: true,
};

function escapeHtml(value) {
  return value.replace(/[&<>"']/g, character => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[character]);
}

function createGalleryItem(photo, hidden = false) {
  const link = document.createElement('a');
  link.className = `event-gallery-item${hidden ? ' event-gallery-hidden' : ''}`;
  link.href = photo.full;
  link.dataset.src = photo.full;
  link.dataset.thumb = photo.thumb;
  link.dataset.subHtml = `<p>${escapeHtml(photo.caption)}</p>`;
  return link;
}

function renderEvents(events) {
  const grid = document.getElementById('event-grid');
  grid.replaceChildren();

  if (!events.length) {
    const message = document.createElement('div');
    message.className = 'event-message';

    const heading = document.createElement('h2');
    heading.textContent = 'No events yet';
    const description = document.createElement('p');
    description.textContent = 'Add a folder for each event inside the events folder, then place its photos there.';

    message.append(heading, description);
    grid.appendChild(message);
    return;
  }

  events.forEach(event => {
    const gallery = document.createElement('div');
    gallery.className = 'event-card-gallery';

    const cover = event.photos[0];
    const tile = createGalleryItem(cover);
    tile.classList.add('event-tile');

    const image = document.createElement('img');
    image.src = cover.thumb;
    image.alt = `Photos from ${event.name}`;
    image.loading = 'lazy';

    const caption = document.createElement('span');
    caption.className = 'event-tile-caption';

    const title = document.createElement('strong');
    title.textContent = event.name;
    const count = document.createElement('span');
    count.textContent = `${event.photos.length} photo${event.photos.length === 1 ? '' : 's'} · view gallery`;

    caption.append(title, count);
    tile.append(image, caption);
    gallery.appendChild(tile);

    event.photos.slice(1).forEach(photo => {
      gallery.appendChild(createGalleryItem(photo, true));
    });

    lightGallery(gallery, {
      ...galleryOptions,
      selector: 'a.event-gallery-item',
    });
    grid.appendChild(gallery);
  });
}

document.getElementById('yr').textContent = new Date().getFullYear();

(async () => {
  try {
    const response = await fetch(EVENTS_ENDPOINT);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const result = await response.json();
    if (!result.success) {
      throw new Error(result.error || 'Could not load events.');
    }

    const events = result.data?.list;
    if (!Array.isArray(events)) {
      throw new Error('The events response is invalid.');
    }

    document.getElementById('event-count').textContent =
      `${events.length} event${events.length === 1 ? '' : 's'}`;
    renderEvents(events);
  } catch (error) {
    console.error('Events error:', error);
    const grid = document.getElementById('event-grid');
    grid.replaceChildren();

    const message = document.createElement('div');
    message.className = 'event-message';
    const heading = document.createElement('h2');
    heading.textContent = 'Could not load events';
    const description = document.createElement('p');
    description.textContent = error.message;
    message.append(heading, description);
    grid.appendChild(message);
  }
})();

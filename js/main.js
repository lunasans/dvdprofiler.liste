function executeInlineScripts(container) {
  container.querySelectorAll('script').forEach(script => {
    const newScript = document.createElement('script');
    if (script.src) {
      newScript.src = script.src;
    } else {
      newScript.textContent = script.textContent;
    }
    document.head.appendChild(newScript);
    document.head.removeChild(newScript);
  });
}

// Chart.js bei Bedarf nachladen
function ensureChartJsLoaded(callback) {
  if (window.Chart) return callback();

  const script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
  script.onload = () => {
    console.log('📊 Chart.js geladen');
    callback();
  };
  document.head.appendChild(script);
}

// Routing bei Seitenwechsel
function loadFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const container = document.getElementById('detail-container');
  if (!container) return;

  if (params.has('id')) {
    fetch('film-fragment.php?id=' + params.get('id'))
      .then(res => res.text())
      .then(html => {
        container.innerHTML = html;

        // Fancybox neu binden
        if (typeof Fancybox !== 'undefined') {
          Fancybox.bind("[data-fancybox]", {});
        }
      });

  } else if (params.has('page')) {
    const page = params.get('page');
    fetch('partials/' + page + '.php')
      .then(res => res.text())
      .then(html => {
        container.innerHTML = html;
        bindToggleLinks();
        bindRoutingLinks();
        bindBoxsetToggles();

        if (page === 'stats') {
          ensureChartJsLoaded(() => {
            executeInlineScripts(container);
            if (typeof renderStatsCharts === 'function') renderStatsCharts();
          });
        } else {
          executeInlineScripts(container);
        }
      });

  } else if (params.has('seite')) {
    fetch('10-latest-fragment.php?seite=' + params.get('seite'))
      .then(res => res.text())
      .then(html => {
        container.innerHTML = html;
        bindToggleLinks();
        bindRoutingLinks();
        bindBoxsetToggles();
      });

  } else {
    fetch('10-latest-fragment.php')
      .then(res => res.text())
      .then(html => {
        container.innerHTML = html;
        bindToggleLinks();
        bindRoutingLinks();
        bindBoxsetToggles();
      });
  }
}


// ⛓ Routing-Links abfangen
function bindRoutingLinks() {
  document.querySelectorAll('a.route-link').forEach(link => {
    link.addEventListener('click', e => {
      const href = link.getAttribute('href');
      if (href.startsWith('?')) {
        e.preventDefault();
        history.pushState({}, '', href);
        loadFromUrl();
        bindToggleLinks();

      }
    });
  });
}

// Detailansicht laden
function bindToggleLinks() {
  document.querySelectorAll('.toggle-detail').forEach(link => {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      const id = this.dataset.id;
      fetch(`film-fragment.php?id=${id}`)
        .then(res => res.text())
        .then(html => {
          const container = document.getElementById('detail-container');
          if (container) {
            container.innerHTML = html;
            history.replaceState(null, '', '?id=' + id);
          }
        });
    });
  });
}

//  Klappfunktion für BoxSets
function bindBoxsetToggles() {
  document.querySelectorAll('.boxset-toggle').forEach(btn => {
    btn.addEventListener('click', function () {
      const dvdCard = btn.closest('.dvd');
      const nextSibling = dvdCard.nextElementSibling;

      if (nextSibling && nextSibling.classList.contains('boxset-children')) {
        const isOpen = nextSibling.classList.toggle('open');
        btn.textContent = isOpen ? '▼ Box-Inhalte ausblenden' : '► Box-Inhalte anzeigen';
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  bindBoxsetToggles();
});

//  Detail schließen (per Button)
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('close-detail-button')) {
    const container = document.getElementById('detail-container');
    if (container) {
      fetch('10-latest-fragment.php')
        .then(res => res.text())
        .then(html => {
          container.innerHTML = html;
          bindToggleLinks();
          bindRoutingLinks();
          bindBoxsetToggles();
        });
      history.replaceState(null, '', 'index.php');
    }
  }
});

//  Esc-Taste schließt Detail
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    const container = document.getElementById('detail-container');
    if (container) {
      fetch('10-latest-fragment.php')
        .then(res => res.text())
        .then(html => {
          container.innerHTML = html;
          bindToggleLinks();
          bindRoutingLinks();
          bindBoxsetToggles();
        });
      history.replaceState(null, '', 'index.php');
    }
  }
});

//  YouTube-Trailer
document.addEventListener('click', function (e) {
  const placeholder = e.target.closest('.trailer-placeholder');
  if (placeholder) {
    const ytUrl = placeholder.dataset.yt;
    const iframe = document.createElement('iframe');
    iframe.src = ytUrl + '?autoplay=1';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    iframe.allowFullscreen = true;
    iframe.style.width = '100%';
    iframe.style.height = '100%';
    iframe.style.border = 'none';
    iframe.style.borderRadius = '6px';
    placeholder.replaceWith(iframe);
  }
});

function setViewMode(mode) {
  const list = document.querySelector('.film-list');
  if (!list) return;

  list.classList.remove('grid-mode', 'list-mode');
  list.classList.add(mode + '-mode');

  localStorage.setItem('viewMode', mode);
}

//  Initialisierung
window.addEventListener('popstate', loadFromUrl);
window.addEventListener('DOMContentLoaded', () => {
  loadFromUrl();
  bindToggleLinks();
  bindRoutingLinks();
  bindBoxsetToggles();

  const savedMode = localStorage.getItem('viewMode') || 'grid';
  setViewMode(savedMode);
});

document.addEventListener('DOMContentLoaded', () => {
  const links = document.querySelectorAll('.main-nav a');
  const current = window.location.search;

  links.forEach(link => {
    if (link.getAttribute('href') === current) {
      link.classList.add('active');
    }
  });
});
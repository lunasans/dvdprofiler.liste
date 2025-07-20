class DVDApp {
    constructor() {
        this.container = document.getElementById('detail-container');
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadFromUrl();
        this.restoreViewMode();
        this.updateNavigation();
    }

    // Event Handlers mit Event Delegation
    setupEventListeners() {
        // Event Delegation für Film-Details
        document.addEventListener('click', this.handleDocumentClick.bind(this));
        
        // Keyboard Events
        document.addEventListener('keydown', this.handleKeydown.bind(this));
        
        // Browser Navigation
        window.addEventListener('popstate', this.loadFromUrl.bind(this));
    }

    handleDocumentClick(e) {
        // Film Detail Toggle
        const toggleElement = e.target.closest('.toggle-detail');
        if (toggleElement) {
            e.preventDefault();
            const filmId = toggleElement.dataset.id;
            if (filmId) {
                this.loadFilmDetail(filmId);
            }
            return;
        }

        // Close Detail Button
        if (e.target.classList.contains('close-detail-button')) {
            e.preventDefault();
            this.closeDetail();
            return;
        }

        // Boxset Toggle
        if (e.target.classList.contains('boxset-toggle')) {
            e.preventDefault();
            this.handleBoxsetToggle(e.target);
            return;
        }

        // Route Links
        const routeLink = e.target.closest('.route-link');
        if (routeLink) {
            const href = routeLink.getAttribute('href');
            if (href && href.startsWith('?')) {
                e.preventDefault();
                history.pushState({}, '', href);
                this.loadFromUrl();
            }
            return;
        }

        // YouTube Trailer
        const placeholder = e.target.closest('.trailer-placeholder');
        if (placeholder) {
            this.loadTrailer(placeholder);
            return;
        }
    }

    handleKeydown(e) {
        if (e.key === 'Escape') {
            this.closeDetail();
        }
    }

    // Film Detail laden
    async loadFilmDetail(filmId) {
        try {
            console.log('Film-ID wird geladen:', filmId); // DEBUG
            
            const response = await fetch(`film-fragment.php?id=${filmId}`);
            const html = await response.text();
            
            console.log('Antwort erhalten, erste 100 Zeichen:', html.substring(0, 100)); // DEBUG
            
            if (this.container) {
                this.container.innerHTML = html;
                history.replaceState(null, '', '?id=' + filmId);
                
                // Fancybox für neue Inhalte binden
                this.bindFancybox();
            }
        } catch (error) {
            console.error('Fehler beim Laden des Films:', error);
            if (this.container) {
                this.container.innerHTML = '<div style="color: red;">Fehler beim Laden des Films.</div>';
            }
        }
    }

    // Detail schließen
    async closeDetail() {
        try {
            const response = await fetch('10-latest-fragment.php');
            const html = await response.text();
            
            if (this.container) {
                this.container.innerHTML = html;
            }
            history.replaceState(null, '', 'index.php');
        } catch (error) {
            console.error('Fehler beim Schließen:', error);
        }
    }

    // Boxset Toggle
    handleBoxsetToggle(button) {
        const dvdCard = button.closest('.dvd');
        const nextSibling = dvdCard?.nextElementSibling;

        if (nextSibling && nextSibling.classList.contains('boxset-children')) {
            const isOpen = nextSibling.classList.toggle('open');
            button.textContent = isOpen ? 
                '▼ Box-Inhalte ausblenden' : 
                '► Box-Inhalte anzeigen';
        }
    }

    // YouTube Trailer laden
    loadTrailer(placeholder) {
        const ytUrl = placeholder.dataset.yt;
        const iframe = document.createElement('iframe');
        iframe.src = ytUrl + '?autoplay=1';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        iframe.allowFullscreen = true;
        iframe.style.cssText = `
            width: 100%; 
            height: 100%; 
            border: none; 
            border-radius: 6px;
        `;
        placeholder.replaceWith(iframe);
    }

    // Routing bei Seitenwechsel
    async loadFromUrl() {
        const params = new URLSearchParams(window.location.search);
        
        if (!this.container) return;

        try {
            if (params.has('id')) {
                await this.loadFilmDetail(params.get('id'));
                
            } else if (params.has('page')) {
                await this.loadPage(params.get('page'));
                
            } else if (params.has('seite')) {
                await this.loadLatestWithPagination(params.get('seite'));
                
            } else {
                await this.loadLatest();
            }
        } catch (error) {
            console.error('Fehler beim Laden der Seite:', error);
        }
    }

    async loadPage(page) {
        const response = await fetch(`partials/${page}.php`);
        const html = await response.text();
        
        this.container.innerHTML = html;
        
        if (page === 'stats') {
            await this.ensureChartJsLoaded();
            this.executeInlineScripts(this.container);
            if (typeof renderStatsCharts === 'function') {
                renderStatsCharts();
            }
        } else {
            this.executeInlineScripts(this.container);
        }
    }

    async loadLatestWithPagination(seite) {
        const response = await fetch(`10-latest-fragment.php?seite=${seite}`);
        const html = await response.text();
        this.container.innerHTML = html;
    }

    async loadLatest() {
        const response = await fetch('10-latest-fragment.php');
        const html = await response.text();
        this.container.innerHTML = html;
    }

    // Fancybox binden
    bindFancybox() {
        if (typeof Fancybox !== 'undefined') {
            Fancybox.bind("[data-fancybox]", {});
        }
    }

    // Chart.js laden
    async ensureChartJsLoaded() {
        if (window.Chart) return;

        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = () => {
                console.log('📊 Chart.js geladen');
                resolve();
            };
            document.head.appendChild(script);
        });
    }

    // Inline Scripts ausführen
    executeInlineScripts(container) {
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

    // View Mode
    setViewMode(mode) {
        const list = document.querySelector('.film-list');
        if (!list) return;

        list.classList.remove('grid-mode', 'list-mode');
        list.classList.add(mode + '-mode');

        localStorage.setItem('viewMode', mode);
    }

    restoreViewMode() {
        const savedMode = localStorage.getItem('viewMode') || 'grid';
        this.setViewMode(savedMode);
    }

    // Navigation Update
    updateNavigation() {
        const links = document.querySelectorAll('.main-nav a');
        const current = window.location.search;

        links.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === current) {
                link.classList.add('active');
            }
        });
    }
}

// App initialisieren
document.addEventListener('DOMContentLoaded', () => {
    window.dvdApp = new DVDApp();
});

// Für externe Nutzung verfügbar machen
window.setViewMode = (mode) => {
    if (window.dvdApp) {
        window.dvdApp.setViewMode(mode);
    }
};
/**
 * DVD Verwaltung - Haupt JavaScript Datei
 * Moderne ES6+ Version mit verbesserter Fehlerbehandlung
 */

class DVDManager {
    constructor() {
        this.container = null;
        this.currentFilmId = null;
        this.loadingStates = new Set();
        this.retryAttempts = new Map();
        this.maxRetries = 3;
        this.retryDelay = 1000;
        
        this.init();
    }

    init() {
        this.container = document.getElementById('detail-container');
        this.bindEvents();
        this.loadFromUrl();
        this.initViewMode();
        // Service Worker entfernt - war optional
    }

    // Event Binding
    bindEvents() {
        // DOM Content Loaded
        document.addEventListener('DOMContentLoaded', () => {
            this.bindToggleLinks();
            this.bindRoutingLinks();
            this.bindBoxsetToggles();
            this.bindViewModeButtons();
            this.bindSearchForm();
            this.setActiveNavLink();
        });

        // Browser Navigation
        window.addEventListener('popstate', () => this.loadFromUrl());
        
        // Keyboard Shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        
        // Global Clicks
        document.addEventListener('click', (e) => this.handleGlobalClicks(e));
        
        // Online/Offline Status
        window.addEventListener('online', () => this.handleOnlineStatus(true));
        window.addEventListener('offline', () => this.handleOnlineStatus(false));
    }

    // URL-basiertes Routing mit Fehlerbehandlung
    async loadFromUrl() {
        if (!this.container) return;

        const params = new URLSearchParams(window.location.search);
        
        try {
            this.showLoading();

            if (params.has('id')) {
                await this.loadFilmDetail(params.get('id'));
            } else if (params.has('page')) {
                await this.loadPage(params.get('page'));
            } else if (params.has('seite')) {
                await this.loadLatestPage(params.get('seite'));
            } else {
                await this.loadLatestPage();
            }
        } catch (error) {
            this.handleError(error, 'Fehler beim Laden der Seite');
        } finally {
            this.hideLoading();
        }
    }

    // Film-Details laden mit verbesserter Fehlerbehandlung
    async loadFilmDetail(filmId) {
        const cacheKey = `film-${filmId}`;
        
        try {
            // Cache prüfen
            const cachedData = this.getFromCache(cacheKey);
            if (cachedData && this.isCacheValid(cachedData.timestamp)) {
                this.container.innerHTML = cachedData.content;
                this.executeInlineScripts(this.container);
                this.bindFancybox();
                return;
            }

            const response = await this.fetchWithRetry(`film-fragment.php?id=${filmId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const html = await response.text();
            
            // Validierung des HTML-Inhalts
            if (this.isValidHTML(html)) {
                this.container.innerHTML = html;
                this.currentFilmId = filmId;
                
                // Cache speichern
                this.saveToCache(cacheKey, html);
                
                // Scripts und Bindings
                this.executeInlineScripts(this.container);
                this.bindFancybox();
                this.bindInteractiveElements();
                
                // Analytics
                this.trackFilmView(filmId);
                
                // URL State
                this.updateUrlState('?id=' + filmId);
            } else {
                throw new Error('Ungültiger HTML-Inhalt erhalten');
            }

        } catch (error) {
            console.error('Film Detail Fehler:', error);
            this.showFilmDetailError(filmId, error);
        }
    }

    // Seiten laden
    async loadPage(pageName) {
        try {
            const response = await this.fetchWithRetry(`partials/${pageName}.php`);
            
            if (!response.ok) {
                throw new Error(`Seite nicht gefunden: ${pageName}`);
            }

            const html = await response.text();
            this.container.innerHTML = html;
            
            this.bindAllLinks();
            
            // Spezielle Behandlung für Stats-Seite
            if (pageName === 'stats') {
                await this.loadChartJS();
                this.executeInlineScripts(this.container);
                if (typeof renderStatsCharts === 'function') {
                    renderStatsCharts();
                }
            } else {
                this.executeInlineScripts(this.container);
            }

        } catch (error) {
            this.handleError(error, `Fehler beim Laden der Seite: ${pageName}`);
        }
    }

    // Neueste Filme laden - mit Debug
    async loadLatestPage(page = null) {
        try {
            const url = page ? `10-latest-fragment.php?seite=${page}` : '10-latest-fragment.php';
            console.log('DEBUG: Lade URL:', url);
            
            const response = await this.fetchWithRetry(url);
            
            console.log('DEBUG: Response Status:', response.status);
            console.log('DEBUG: Response OK:', response.ok);
            
            if (!response.ok) {
                throw new Error('Neueste Filme konnten nicht geladen werden');
            }

            const html = await response.text();
            console.log('DEBUG: Response Text (erste 500 Zeichen):', html.substring(0, 500));
            
            this.container.innerHTML = html;
            
            this.bindAllLinks();

        } catch (error) {
            console.error('DEBUG: AJAX Fehler:', error);
            this.handleError(error, 'Fehler beim Laden der neuesten Filme');
        }
    }

    // Fetch mit Retry-Logik
    async fetchWithRetry(url, options = {}) {
        const retryKey = url;
        const attempts = this.retryAttempts.get(retryKey) || 0;

        try {
            // Loading State
            this.loadingStates.add(url);
            
            // Request mit Timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s Timeout
            
            const response = await fetch(url, {
                ...options,
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...options.headers
                }
            });
            
            clearTimeout(timeoutId);
            this.loadingStates.delete(url);
            this.retryAttempts.delete(retryKey);
            
            return response;

        } catch (error) {
            this.loadingStates.delete(url);
            
            // Retry-Logik
            if (attempts < this.maxRetries && this.shouldRetry(error)) {
                this.retryAttempts.set(retryKey, attempts + 1);
                
                console.warn(`Retry ${attempts + 1}/${this.maxRetries} für ${url}:`, error.message);
                
                // Exponential Backoff
                const delay = this.retryDelay * Math.pow(2, attempts);
                await this.sleep(delay);
                
                return this.fetchWithRetry(url, options);
            }
            
            this.retryAttempts.delete(retryKey);
            throw error;
        }
    }

    // Fehlerbehandlung für Film-Details
    showFilmDetailError(filmId, error) {
        const isNetworkError = !navigator.onLine || error.name === 'AbortError';
        const is404Error = error.message.includes('404');
        const is500Error = error.message.includes('500');

        let errorHTML = `
            <div class="error-message film-detail-error">
                <i class="bi bi-exclamation-triangle"></i>
                <h3>Film konnte nicht geladen werden</h3>
        `;

        if (isNetworkError) {
            errorHTML += `
                <p>Keine Internetverbindung verfügbar.</p>
                <div class="error-actions">
                    <button onclick="dvdManager.retryLoadFilm(${filmId})" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise"></i> Erneut versuchen
                    </button>
                </div>
            `;
        } else if (is404Error) {
            errorHTML += `
                <p>Der Film mit ID <strong>${filmId}</strong> wurde nicht gefunden.</p>
                <div class="error-actions">
                    <button onclick="dvdManager.goHome()" class="btn btn-primary">
                        <i class="bi bi-house"></i> Zur Startseite
                    </button>
                </div>
            `;
        } else if (is500Error) {
            errorHTML += `
                <p>Serverfehler beim Laden der Film-Details.</p>
                <details class="error-details">
                    <summary>Technische Details</summary>
                    <p>Film-ID: ${filmId}</p>
                    <p>Fehler: ${error.message}</p>
                    <p>Zeit: ${new Date().toLocaleString()}</p>
                </details>
                <div class="error-actions">
                    <button onclick="dvdManager.retryLoadFilm(${filmId})" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise"></i> Erneut versuchen
                    </button>
                    <button onclick="dvdManager.reportError('${filmId}', '${error.message}')" class="btn btn-secondary">
                        <i class="bi bi-flag"></i> Fehler melden
                    </button>
                </div>
            `;
        } else {
            errorHTML += `
                <p>Ein unerwarteter Fehler ist aufgetreten.</p>
                <details class="error-details">
                    <summary>Fehlerdetails</summary>
                    <p>${error.message}</p>
                </details>
                <div class="error-actions">
                    <button onclick="dvdManager.retryLoadFilm(${filmId})" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise"></i> Erneut versuchen
                    </button>
                </div>
            `;
        }

        errorHTML += `</div>`;
        this.container.innerHTML = errorHTML;
    }

    // Event Handlers
    bindToggleLinks() {
        document.querySelectorAll('.toggle-detail').forEach(link => {
            link.addEventListener('click', async (e) => {
                e.preventDefault();
                const filmId = link.dataset.id;
                
                if (filmId) {
                    await this.loadFilmDetail(filmId);
                }
            });
        });
    }

    bindRoutingLinks() {
        document.querySelectorAll('a.route-link').forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                if (href && href.startsWith('?')) {
                    e.preventDefault();
                    history.pushState({}, '', href);
                    this.loadFromUrl();
                }
            });
        });
    }

    bindBoxsetToggles() {
        document.querySelectorAll('.boxset-toggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const dvdCard = btn.closest('.dvd');
                const nextSibling = dvdCard?.nextElementSibling;

                if (nextSibling?.classList.contains('boxset-children')) {
                    const isOpen = nextSibling.classList.toggle('open');
                    btn.innerHTML = isOpen 
                        ? '<i class="bi bi-chevron-down"></i> Box-Inhalte ausblenden'
                        : '<i class="bi bi-chevron-right"></i> Box-Inhalte anzeigen';
                    
                    // Animation
                    if (isOpen) {
                        nextSibling.style.maxHeight = nextSibling.scrollHeight + 'px';
                    } else {
                        nextSibling.style.maxHeight = '0';
                    }
                }
            });
        });
    }

    bindViewModeButtons() {
        document.querySelectorAll('.view-toggle button').forEach(btn => {
            btn.addEventListener('click', () => {
                const mode = btn.dataset.mode;
                if (mode) {
                    this.setViewMode(mode);
                }
            });
        });
    }

    bindSearchForm() {
        const searchForm = document.querySelector('.search-form');
        const searchInput = searchForm?.querySelector('input[type="search"]');
        
        if (searchInput) {
            // Debounced Search
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value);
                }, 300);
            });

            // Submit Handler
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.performSearch(searchInput.value);
            });
        }
    }

    // Globale Click-Handler
    handleGlobalClicks(e) {
        // Close Detail Button
        if (e.target.classList.contains('close-detail-button')) {
            this.closeDetail();
        }

        // Trailer Placeholder
        const trailerPlaceholder = e.target.closest('.trailer-placeholder');
        if (trailerPlaceholder) {
            this.loadTrailer(trailerPlaceholder);
        }

        // Trailer Box
        const trailerBox = e.target.closest('.trailer-box');
        if (trailerBox) {
            this.openTrailer(trailerBox.dataset.src);
        }
    }

    // Keyboard Handler
    handleKeyboard(e) {
        switch (e.key) {
            case 'Escape':
                this.closeDetail();
                break;
            case 'ArrowLeft':
                if (e.ctrlKey) {
                    e.preventDefault();
                    this.navigateHistory(-1);
                }
                break;
            case 'ArrowRight':
                if (e.ctrlKey) {
                    e.preventDefault();
                    this.navigateHistory(1);
                }
                break;
            case 'f':
                if (e.ctrlKey) {
                    e.preventDefault();
                    this.focusSearch();
                }
                break;
        }
    }

    // Utility Functions
    async closeDetail() {
        try {
            await this.loadLatestPage();
            this.updateUrlState('index.php');
        } catch (error) {
            this.handleError(error, 'Fehler beim Schließen der Detail-Ansicht');
        }
    }

    setViewMode(mode) {
        const list = document.querySelector('.film-list');
        if (!list) return;

        // CSS-Klassen aktualisieren
        list.classList.remove('grid-mode', 'list-mode');
        list.classList.add(mode + '-mode');

        // Button-States aktualisieren
        document.querySelectorAll('.view-toggle button').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mode === mode);
        });

        // Preference speichern
        localStorage.setItem('viewMode', mode);
        
        // Event für Analytics
        this.trackEvent('view_mode_changed', { mode });
    }

    initViewMode() {
        const savedMode = localStorage.getItem('viewMode') || 'grid';
        this.setViewMode(savedMode);
    }

    async loadChartJS() {
        if (window.Chart) return;

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = () => {
                console.log('📊 Chart.js geladen');
                resolve();
            };
            script.onerror = () => reject(new Error('Chart.js konnte nicht geladen werden'));
            document.head.appendChild(script);
        });
    }

    executeInlineScripts(container) {
        container.querySelectorAll('script').forEach(script => {
            try {
                const newScript = document.createElement('script');
                if (script.src) {
                    newScript.src = script.src;
                } else {
                    newScript.textContent = script.textContent;
                }
                document.head.appendChild(newScript);
                document.head.removeChild(newScript);
            } catch (error) {
                console.warn('Script execution failed:', error);
            }
        });
    }

    bindFancybox() {
        if (typeof Fancybox !== 'undefined') {
            Fancybox.bind("[data-fancybox]", {
                Toolbar: {
                    display: ["zoom", "slideshow", "thumbs", "close"]
                },
                Thumbs: {
                    autoStart: true
                }
            });
        }
    }

    bindInteractiveElements() {
        // Rating System
        document.querySelectorAll('.rating-star').forEach(star => {
            star.addEventListener('click', (e) => {
                this.handleRating(e.target.dataset.rating);
            });
        });

        // Wishlist Buttons
        document.querySelectorAll('.add-to-wishlist').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.toggleWishlist(e.target.dataset.filmId);
            });
        });
    }

    bindAllLinks() {
        this.bindToggleLinks();
        this.bindRoutingLinks();
        this.bindBoxsetToggles();
    }

    // Cache Management
    getFromCache(key) {
        try {
            const cached = localStorage.getItem(`cache_${key}`);
            return cached ? JSON.parse(cached) : null;
        } catch (error) {
            console.warn('Cache read error:', error);
            return null;
        }
    }

    saveToCache(key, content) {
        try {
            const cacheItem = {
                content,
                timestamp: Date.now()
            };
            localStorage.setItem(`cache_${key}`, JSON.stringify(cacheItem));
        } catch (error) {
            console.warn('Cache write error:', error);
        }
    }

    isCacheValid(timestamp, maxAge = 5 * 60 * 1000) { // 5 Minuten
        return Date.now() - timestamp < maxAge;
    }

    // Loading States
    showLoading() {
        const loadingIndicator = document.getElementById('loading-indicator');
        if (loadingIndicator) {
            loadingIndicator.style.display = 'flex';
        }
    }

    hideLoading() {
        const loadingIndicator = document.getElementById('loading-indicator');
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }
    }

    // Error Handling
    handleError(error, message) {
        console.error(message, error);
        this.showNotification(message, 'error');
    }

    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="bi bi-${this.getNotificationIcon(type)}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="bi bi-x"></i>
            </button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);
    }

    getNotificationIcon(type) {
        const icons = {
            'success': 'check-circle',
            'error': 'exclamation-triangle',
            'warning': 'exclamation-circle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    // Utility Methods
    async retryLoadFilm(filmId) {
        this.retryAttempts.delete(`film-fragment.php?id=${filmId}`);
        await this.loadFilmDetail(filmId);
    }

    goHome() {
        history.pushState({}, '', 'index.php');
        this.loadFromUrl();
    }

    reportError(filmId, errorMessage) {
        // Implement error reporting
        console.log('Error reported:', { filmId, errorMessage });
        this.showNotification('Fehler wurde gemeldet. Vielen Dank!', 'success');
    }

    updateUrlState(url) {
        if (history.replaceState) {
            history.replaceState(null, '', url);
        }
    }

    setActiveNavLink() {
        const links = document.querySelectorAll('.main-nav a');
        const current = window.location.search;

        links.forEach(link => {
            link.classList.toggle('active', link.getAttribute('href') === current);
        });
    }

    handleOnlineStatus(isOnline) {
        if (isOnline) {
            this.showNotification('Internetverbindung wiederhergestellt', 'success');
        } else {
            this.showNotification('Keine Internetverbindung', 'warning');
        }
    }

    // Service Worker - Nur wenn Datei existiert
    async initServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                // Prüfen ob Service Worker Datei existiert
                const response = await fetch('/sw.js', { method: 'HEAD' });
                if (response.ok) {
                    await navigator.serviceWorker.register('/sw.js');
                    console.log('Service Worker registered');
                } else {
                    console.log('Service Worker file not found - skipping registration');
                }
            } catch (error) {
                console.log('Service Worker registration failed:', error.message);
            }
        }
    }

    // Helper Methods
    shouldRetry(error) {
        return !error.message.includes('404') && 
               !error.message.includes('400') &&
               error.name !== 'AbortError';
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    isValidHTML(html) {
        return html && html.trim().length > 0 && !html.includes('Fatal error');
    }

    trackFilmView(filmId) {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'film_view', {
                'film_id': filmId
            });
        }
    }

    trackEvent(eventName, parameters = {}) {
        if (typeof gtag !== 'undefined') {
            gtag('event', eventName, parameters);
        }
    }

    // Search
    async performSearch(query) {
        if (!query.trim()) return;
        
        try {
            const response = await this.fetchWithRetry(`ajax/search.php?q=${encodeURIComponent(query)}`);
            const results = await response.json();
            this.displaySearchResults(results);
        } catch (error) {
            this.handleError(error, 'Fehler bei der Suche');
        }
    }

    focusSearch() {
        const searchInput = document.querySelector('.search-form input');
        if (searchInput) {
            searchInput.focus();
        }
    }

    navigateHistory(direction) {
        if (direction === -1) {
            history.back();
        } else if (direction === 1) {
            history.forward();
        }
    }
}

// Global Instance
const dvdManager = new DVDManager();

// Legacy Functions für Rückwärtskompatibilität
function setViewMode(mode) {
    dvdManager.setViewMode(mode);
}

function closeDetail() {
    dvdManager.closeDetail();
}

// Export für Module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DVDManager;
}
// main.js - Erweitert mit Film-Rating Funktionalität

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

    // Film Detail laden mit Rating-Integration
    async loadFilmDetail(filmId) {
        try {
            console.log('🎬 Film-ID wird geladen:', filmId);
            
            const response = await fetch(`film-fragment.php?id=${filmId}`);
            const html = await response.text();
            
            console.log('📄 Antwort erhalten, erste 100 Zeichen:', html.substring(0, 100));
            
            if (this.container) {
                this.container.innerHTML = html;
                history.replaceState(null, '', '?id=' + filmId);
                
                // Fancybox für neue Inhalte binden
                this.bindFancybox();
                
                // 🌟 FILM-RATING INITIALISIEREN
                this.initFilmRating();
            }
        } catch (error) {
            console.error('❌ Fehler beim Laden des Films:', error);
            if (this.container) {
                this.container.innerHTML = '<div style="color: red;">Fehler beim Laden des Films.</div>';
            }
        }
    }

    // 🌟 NEUE METHODE: Film-Rating System initialisieren
    initFilmRating() {
        console.log('🌟 Film Rating wird initialisiert...');
        
        // Rating-System
        const ratingStars = document.querySelectorAll('.rating-star');
        const saveRatingBtn = document.querySelector('.save-rating');
        const ratingDisplay = document.querySelector('.rating-display');
        const ratingInput = document.querySelector('.star-rating-input');
        
        if (!ratingStars.length) {
            console.log('ℹ️ Keine Rating-Sterne gefunden (User nicht eingeloggt oder keine Rating-Sektion)');
            this.initOtherFilmFeatures(); // Andere Features trotzdem initialisieren
            return;
        }
        
        const currentRating = parseFloat(ratingInput?.dataset.currentRating || 0);
        let selectedRating = currentRating;
        
        console.log('⭐ Rating System gefunden:', {
            ratingStars: ratingStars.length,
            saveRatingBtn: !!saveRatingBtn,
            ratingDisplay: !!ratingDisplay,
            currentRating: currentRating
        });
        
        // Event-Listener für Sterne
        ratingStars.forEach((star, index) => {
            star.style.cursor = 'pointer';
            
            star.addEventListener('mouseenter', () => {
                const rating = parseInt(star.dataset.rating);
                this.highlightStars(ratingStars, rating);
            });
            
            star.addEventListener('mouseleave', () => {
                this.highlightStars(ratingStars, selectedRating);
            });
            
            star.addEventListener('click', () => {
                selectedRating = parseInt(star.dataset.rating);
                console.log('⭐ Stern geklickt, gewählte Bewertung:', selectedRating);
                
                this.highlightStars(ratingStars, selectedRating);
                
                if (saveRatingBtn) {
                    saveRatingBtn.style.display = 'inline-block';
                }
                if (ratingDisplay) {
                    ratingDisplay.textContent = selectedRating + '/5';
                }
            });
        });
        
        // Save-Button Event
        if (saveRatingBtn) {
            saveRatingBtn.addEventListener('click', () => {
                const filmId = ratingInput?.dataset.filmId;
                console.log('💾 Speichere Rating:', {filmId, selectedRating});
                this.saveUserRating(filmId, selectedRating);
            });
        }
        
        // Andere Film-Features initialisieren
        this.initOtherFilmFeatures();
    }
    
    // Sterne hervorheben
    highlightStars(stars, rating) {
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.remove('bi-star');
                star.classList.add('bi-star-fill');
            } else {
                star.classList.remove('bi-star-fill');
                star.classList.add('bi-star');
            }
        });
    }
    
    // Andere Film-Features (Wishlist, Watched, Share, Trailer)
    initOtherFilmFeatures() {
        console.log('🎭 Andere Film-Features werden initialisiert...');
        
        // Wishlist-Button
        const wishlistBtn = document.querySelector('.add-to-wishlist');
        if (wishlistBtn) {
            wishlistBtn.addEventListener('click', () => {
                const filmId = wishlistBtn.dataset.filmId;
                this.toggleWishlist(filmId, wishlistBtn);
            });
        }
        
        // Watched-Button
        const watchedBtn = document.querySelector('.mark-as-watched');
        if (watchedBtn) {
            watchedBtn.addEventListener('click', () => {
                const filmId = watchedBtn.dataset.filmId;
                this.toggleWatched(filmId, watchedBtn);
            });
        }
        
        // Share-Button
        const shareBtn = document.querySelector('.share-film');
        if (shareBtn) {
            shareBtn.addEventListener('click', () => {
                const filmId = shareBtn.dataset.filmId;
                const filmTitle = shareBtn.dataset.filmTitle;
                this.shareFilm(filmId, filmTitle);
            });
        }
        
        // Trailer-Button
        const trailerBox = document.querySelector('.trailer-box');
        if (trailerBox) {
            trailerBox.addEventListener('click', () => {
                const trailerUrl = trailerBox.dataset.src;
                if (trailerUrl) {
                    window.open(trailerUrl, 'trailer', 'width=800,height=600');
                }
            });
        }
    }
    
    // AJAX: User-Rating speichern
    async saveUserRating(filmId, rating) {
        console.log('📡 AJAX: saveUserRating aufgerufen', {filmId, rating});
        
        try {
            const response = await fetch('api/save-rating.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ film_id: filmId, rating: rating })
            });
            
            console.log('📡 Response status:', response.status);
            
            if (response.ok) {
                this.showNotification('Bewertung gespeichert!', 'success');
                const saveBtn = document.querySelector('.save-rating');
                if (saveBtn) saveBtn.style.display = 'none';
                
                // Seite nach kurzer Zeit neu laden um Community-Rating zu aktualisieren
                setTimeout(() => {
                    this.loadFilmDetail(filmId); // Reload der Film-Details
                }, 1500);
            } else {
                const errorText = await response.text();
                console.error('❌ Response error:', errorText);
                this.showNotification('Fehler beim Speichern: ' + response.status, 'error');
            }
        } catch (error) {
            console.error('❌ AJAX Error:', error);
            this.showNotification('Fehler beim Speichern der Bewertung', 'error');
        }
    }
    
    // AJAX: Wishlist Toggle
    async toggleWishlist(filmId, button) {
        try {
            const response = await fetch('api/toggle-wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ film_id: filmId })
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.added) {
                    button.innerHTML = '<i class="bi bi-heart-fill"></i> Auf Wunschliste';
                    button.classList.add('active');
                    this.showNotification('Zur Wunschliste hinzugefügt!', 'success');
                } else {
                    button.innerHTML = '<i class="bi bi-heart"></i> Zur Wunschliste';
                    button.classList.remove('active');
                    this.showNotification('Von Wunschliste entfernt!', 'info');
                }
            }
        } catch (error) {
            this.showNotification('Fehler bei Wunschliste', 'error');
        }
    }
    
    // AJAX: Watched Toggle
    async toggleWatched(filmId, button) {
        try {
            const response = await fetch('api/toggle-watched.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ film_id: filmId })
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.watched) {
                    button.innerHTML = '<i class="bi bi-check-circle-fill"></i> Gesehen';
                    button.classList.add('active');
                    this.showNotification('Als gesehen markiert!', 'success');
                } else {
                    button.innerHTML = '<i class="bi bi-check-circle"></i> Als gesehen markieren';
                    button.classList.remove('active');
                    this.showNotification('Markierung entfernt!', 'info');
                }
            }
        } catch (error) {
            this.showNotification('Fehler beim Markieren', 'error');
        }
    }
    
    // Share-Funktion
    shareFilm(filmId, filmTitle) {
        const url = window.location.origin + window.location.pathname + '?id=' + filmId;
        
        if (navigator.share) {
            navigator.share({
                title: filmTitle,
                text: 'Schau dir diesen Film an: ' + filmTitle,
                url: url
            });
        } else {
            navigator.clipboard.writeText(url).then(() => {
                this.showNotification('Link kopiert!', 'success');
            }).catch(() => {
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                this.showNotification('Link kopiert!', 'success');
            });
        }
    }
    
    // Notification anzeigen
    showNotification(message, type = 'info') {
        console.log(`🔔 Notification: ${message} (${type})`);
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            background: rgba(0,0,0,0.9);
            border: 1px solid rgba(255,255,255,0.1);
            border-left: 4px solid ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
            border-radius: 8px;
            color: white;
            z-index: 10000;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            font-weight: 500;
            transform: translateX(100%);
            transition: transform 0.3s ease-out;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
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

    // Restliche Methoden (updateNavigation, restoreViewMode, executeInlineScripts) bleiben unverändert...
    updateNavigation() {
        // Bestehende Navigation-Logik
    }
    
    restoreViewMode() {
        // Bestehende ViewMode-Logik  
    }
    
    executeInlineScripts(container) {
        // Bestehende Script-Ausführung
    }
}

// App initialisieren
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 DVD App wird initialisiert...');
    window.dvdApp = new DVDApp();
});

// Global verfügbare Funktion für closeDetail (für Backwards-Kompatibilität)
function closeDetail() {
    if (window.dvdApp) {
        window.dvdApp.closeDetail();
    }
}
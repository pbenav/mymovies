/**
 * VideoTeca - JavaScript principal
 * 
 * Funcionalidades:
 * - Carga dinámica de películas por categoría
 * - Búsqueda en tiempo real
 * - Swiper para "Más Vistas"
 * - Paginación infinita (cargar más)
 * - Video.js player
 */

document.addEventListener('DOMContentLoaded', function() {

    // ============================================================
    // API base URL
    // ============================================================
    const API = 'api/endpoint.php';

    // ============================================================
    // Cargar películas de una categoría
    // ============================================================
    function cargarCategorias() {
        const grids = document.querySelectorAll('.grid[data-categoria]');
        grids.forEach(grid => {
            const catId = grid.dataset.categoria;
            fetch(`${API}?action=listado&categoria=${catId}&pagina=1`)
                .then(r => r.json())
                .then(data => {
                    renderizarPeliculas(grid, data.peliculas);
                })
                .catch(err => console.error('Error cargando categoría:', err));
        });
    }

    // ============================================================
    // Cargar más películas de una categoría
    // ============================================================
    function cargarMas(btn, catId) {
        const grid = btn.closest('.section').querySelector('.grid');
        const currentCount = grid.children.length;
        const pagina = Math.floor(currentCount / 24) + 1;

        btn.textContent = 'Cargando...';
        btn.disabled = true;

        fetch(`${API}?action=listado&categoria=${catId}&pagina=${pagina}`)
            .then(r => r.json())
            .then(data => {
                renderizarPeliculas(grid, data.peliculas, true);
                if (data.peliculas.length === 0) {
                    btn.closest('.load-more').style.display = 'none';
                }
            })
            .catch(err => {
                console.error('Error cargando más:', err);
                btn.textContent = 'Cargar más';
                btn.disabled = false;
            });
    }

    // ============================================================
    // Renderizar películas en una grid
    // ============================================================
    function renderizarPeliculas(container, peliculas, append = false) {
        if (!append) container.innerHTML = '';

        peliculas.forEach(peli => {
            const card = document.createElement('div');
            card.className = 'card';
            
            const poster = peli.poster 
                ? `<img src="${escapeHtml(peli.poster)}" alt="${escapeHtml(peli.titulo)}" loading="lazy"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                   <div class="card-poster-placeholder" style="display:none">🎬</div>`
                : `<div class="card-poster-placeholder">🎬</div>`;

            const rating = peli.rating ? `<span class="card-rating">${peli.rating}</span>` : '';
            const year = peli.año ? ` · ${peli.año}` : '';
            const views = peli.vistas > 0 ? ` · ${peli.vistas} vistas` : '';

            card.innerHTML = `
                <a href="pel.php?id=${peli.id}">
                    <div class="card-poster">
                        ${poster}
                        ${rating}
                    </div>
                </a>
                <div class="card-info">
                    <a href="pel.php?id=${peli.id}">
                        <h3>${escapeHtml(peli.titulo)}</h3>
                    </a>
                    <span class="card-meta">${peli.categoria_nombre || ''}${year}${views}</span>
                </div>
            `;
            container.appendChild(card);
        });
    }

    // ============================================================
    // Top vistas - Swiper
    // ============================================================
    function cargarTopVistas() {
        const container = document.getElementById('top-vistas-container');
        if (!container) return;

        fetch(`${API}?action=top_vistas&limit=15`)
            .then(r => r.json())
            .then(data => {
                container.innerHTML = '';
                data.peliculas.forEach(peli => {
                    const slide = document.createElement('div');
                    slide.className = 'swiper-slide';
                    
                    const poster = peli.poster 
                        ? `<img src="${escapeHtml(peli.poster)}" alt="${escapeHtml(peli.titulo)}"
                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                           <div class="card-poster-placeholder" style="display:none">🎬</div>`
                        : `<div class="card-poster-placeholder">🎬</div>`;

                    const rating = peli.rating ? `<span class="card-rating">${peli.rating}</span>` : '';

                    slide.innerHTML = `
                        <div class="card">
                            <a href="pel.php?id=${peli.id}">
                                <div class="card-poster">
                                    ${poster}
                                    ${rating}
                                </div>
                            </a>
                            <div class="card-info">
                                <a href="pel.php?id=${peli.id}">
                                    <h3>${escapeHtml(peli.titulo)}</h3>
                                </a>
                                <span class="card-meta">${peli.año || ''}${peli.vistas > 0 ? ' · ' + peli.vistas + ' vistas' : ''}</span>
                            </div>
                        </div>
                    `;
                    container.appendChild(slide);
                });

                // Inicializar Swiper
                new Swiper('.top-vistas-swiper', {
                    slidesPerView: 2,
                    spaceBetween: 15,
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true,
                    },
                    breakpoints: {
                        480: { slidesPerView: 3 },
                        640: { slidesPerView: 4 },
                        768: { slidesPerView: 5 },
                        1024: { slidesPerView: 6 },
                    },
                });
            })
            .catch(err => console.error('Error cargando top vistas:', err));
    }

    // ============================================================
    // Búsqueda en tiempo real
    // ============================================================
    function initBusqueda() {
        const input = document.querySelector('.search-form input[name="buscar"]');
        if (!input) return;

        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            const termino = this.value.trim();
            
            if (termino.length < 2) return;

            timeout = setTimeout(() => {
                fetch(`${API}?action=buscar&termino=${encodeURIComponent(termino)}`)
                    .then(r => r.json())
                    .then(data => {
                        mostrarResultadosBusqueda(data.peliculas);
                    })
                    .catch(err => console.error('Error en búsqueda:', err));
            }, 400);
        });

        // Form submit -> ir a index.php?buscar=...
        const form = input.closest('.search-form');
        form.addEventListener('submit', function(e) {
            // Comportamiento normal del form
        });
    }

    function mostrarResultadosBusqueda(peliculas) {
        // En una versión más avanzada, mostrar dropdown con resultados
        // Por ahora, redirigir a la página de búsqueda
    }

    // ============================================================
    // Inicializar Video.js
    // ============================================================
    function initVideoPlayers() {
        videojs('*', {
            controls: true,
            autoplay: false,
            preload: 'auto',
            fluid: true,
            responsive: true,
            controlsList: 'nodownload',
        });
    }

    // ============================================================
    // Utilidades
    // ============================================================
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ============================================================
    // Inicialización
    // ============================================================
    cargarCategorias();
    cargarTopVistas();
    initBusqueda();
    initVideoPlayers();

    // Event delegation para "Cargar más"
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-load');
        if (btn) {
            const catId = btn.closest('.load-more').dataset.categoria;
            cargarMas(btn, catId);
        }
    });

});

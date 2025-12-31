<?php
/**
 * Shortcode Assets
 * 
 * Contains CSS and JavaScript for the vessels shortcode display.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<style>
    .yatco-vessels-container {
        margin: 20px 0;
    }
    .yatco-filters {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .yatco-filters-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 15px;
    }
    .yatco-filters-row-2 {
        margin-bottom: 0;
    }
    .yatco-filter-group {
        flex: 1;
        min-width: 150px;
    }
    .yatco-filter-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        font-size: 14px;
        color: #333;
    }
    .yatco-filter-input,
    .yatco-filter-select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    .yatco-input-small {
        width: 80px;
    }
    .yatco-filter-range {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .yatco-filter-toggle {
        display: flex;
        margin-top: 8px;
        gap: 0;
    }
    .yatco-toggle-btn {
        padding: 6px 16px;
        border: 1px solid #0073aa;
        background: #fff;
        color: #0073aa;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
    }
    .yatco-toggle-btn:first-child {
        border-top-left-radius: 4px;
        border-bottom-left-radius: 4px;
    }
    .yatco-toggle-btn:last-child {
        border-top-right-radius: 4px;
        border-bottom-right-radius: 4px;
        border-left: none;
    }
    .yatco-toggle-btn.active {
        background: #0073aa;
        color: #fff;
    }
    .yatco-filter-actions {
        display: flex;
        align-items: flex-end;
        gap: 10px;
    }
    .yatco-search-btn,
    .yatco-reset-btn {
        padding: 10px 24px;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .yatco-search-btn {
        background: #0073aa;
        color: #fff;
    }
    .yatco-search-btn:hover {
        background: #005a87;
    }
    .yatco-reset-btn {
        background: #ddd;
        color: #333;
    }
    .yatco-reset-btn:hover {
        background: #ccc;
    }
    .yatco-results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 10px 0;
    }
    .yatco-results-count {
        font-weight: 600;
        color: #333;
    }
    .yatco-sort-view {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .yatco-sort-select {
        padding: 6px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .yatco-pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin: 30px 0;
        padding: 20px 0;
        flex-wrap: wrap;
    }
    .yatco-pagination-btn {
        padding: 10px 20px;
        border: 1px solid #0073aa;
        background: #fff;
        color: #0073aa;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
    }
    .yatco-pagination-btn:hover {
        background: #0073aa;
        color: #fff;
    }
    .yatco-pagination-btn.active {
        background: #0073aa;
        color: #fff;
        font-weight: 700;
    }
    .yatco-page-info {
        font-weight: 600;
        color: #333;
        margin-left: 15px;
    }
    .yatco-page-dots {
        padding: 10px 5px;
        color: #666;
    }
    .yatco-pagination-btn.yatco-page-num {
        min-width: 40px;
    }
    .yatco-loading-note {
        font-size: 12px;
        color: #666;
        font-style: italic;
        margin-top: 5px;
    }
    .yatco-vessels-grid {
        display: grid;
        gap: 20px;
        margin: 20px 0;
    }
    .yatco-vessels-grid.yatco-col-1 { grid-template-columns: 1fr; }
    .yatco-vessels-grid.yatco-col-2 { grid-template-columns: repeat(2, 1fr); }
    .yatco-vessels-grid.yatco-col-3 { grid-template-columns: repeat(3, 1fr); }
    .yatco-vessels-grid.yatco-col-4 { grid-template-columns: repeat(4, 1fr); }
    .yatco-vessel-card {
        display: block;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
        transition: box-shadow 0.3s;
        text-decoration: none;
        color: inherit;
    }
    .yatco-vessel-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        text-decoration: none;
    }
    .yatco-vessel-card:visited {
        color: inherit;
    }
    .yatco-vessel-image {
        width: 100%;
        padding-top: 75%;
        position: relative;
        overflow: hidden;
        background: #f5f5f5;
    }
    .yatco-vessel-image img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .yatco-vessel-info {
        padding: 15px;
    }
    .yatco-vessel-name {
        margin: 0 0 10px 0;
        font-size: 18px;
        font-weight: 600;
    }
    .yatco-vessel-details {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 14px;
        color: #666;
    }
    .yatco-vessel-price {
        font-weight: 600;
        color: #0073aa;
    }
    @media (max-width: 768px) {
        .yatco-vessels-grid.yatco-col-2,
        .yatco-vessels-grid.yatco-col-3,
        .yatco-vessels-grid.yatco-col-4 {
            grid-template-columns: 1fr;
        }
        .yatco-filters-row {
            flex-direction: column;
        }
        .yatco-filter-group {
            min-width: 100%;
        }
        .yatco-results-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
    }
</style>
<script>
(function() {
    console.log('[YATCO] Script starting...');
    
    const container = document.querySelector('.yatco-vessels-container');
    if (!container) {
        console.warn('[YATCO] Container not found, exiting');
        return;
    }
    console.log('[YATCO] Container found');
    
    const currency = container.dataset.currency || 'USD';
    const lengthUnit = container.dataset.lengthUnit || 'FT';
    console.log('[YATCO] Currency:', currency, ', LengthUnit:', lengthUnit);
    
    let allVessels = Array.from(document.querySelectorAll('.yatco-vessel-card')); // Changed to let so it can be updated
    console.log('[YATCO] Initial vessel count from DOM:', allVessels.length);
    
    const grid = document.getElementById('yatco-vessels-grid');
    if (!grid) {
        console.warn('[YATCO] Grid element not found!');
    } else {
        console.log('[YATCO] Grid element found');
    }
    
    // Flag to track if we're waiting for vessels to load (for URL parameter filtering)
    window.yatcoWaitingForVessels = false;
    
    // Listen for event when new vessels are loaded via AJAX (set up early)
    document.addEventListener('yatco:vessels-loaded', function(event) {
        console.log('[YATCO] yatco:vessels-loaded event fired', event.detail ? '(count: ' + event.detail.count + ')' : '');
        // Invalidate cache when new vessels are added
        invalidateVesselsCache();
        
        // Update allVessels array when new vessels are added to DOM
        const newVesselCount = document.querySelectorAll('.yatco-vessel-card').length;
        console.log('[YATCO] yatco:vessels-loaded: Found', newVesselCount, 'total vessels in DOM');
        allVessels = Array.from(document.querySelectorAll('.yatco-vessel-card'));
        
        // If we were waiting for vessels to load (due to URL parameters), filter now
        if (window.yatcoWaitingForVessels) {
            console.log('[YATCO] yatco:vessels-loaded: Was waiting for vessels, filtering now');
            // Small delay to ensure DOM is fully updated
            setTimeout(function() {
                filterAndDisplay();
                window.yatcoWaitingForVessels = false;
            }, 100);
        } else {
            console.log('[YATCO] yatco:vessels-loaded: Not waiting for vessels, cache invalidated');
        }
    });
    const resultsCount = document.querySelector('.yatco-results-count');
    const totalCount = document.getElementById('yatco-total-count');
    
    // Filter elements
    const keywords = document.getElementById('yatco-keywords');
    const builder = document.getElementById('yatco-builder');
    const yearMin = document.getElementById('yatco-year-min');
    const yearMax = document.getElementById('yatco-year-max');
    const loaMin = document.getElementById('yatco-loa-min');
    const loaMax = document.getElementById('yatco-loa-max');
    const priceMin = document.getElementById('yatco-price-min');
    const priceMax = document.getElementById('yatco-price-max');
    const condition = document.getElementById('yatco-condition');
    const type = document.getElementById('yatco-type');
    const category = document.getElementById('yatco-category');
    const cabins = document.getElementById('yatco-cabins');
    const sort = document.getElementById('yatco-sort');
    const searchBtn = document.getElementById('yatco-search-btn');
    const resetBtn = document.getElementById('yatco-reset-btn');
    
    // Toggle buttons
    const lengthBtns = document.querySelectorAll('.yatco-toggle-btn[data-unit]');
    const currencyBtns = document.querySelectorAll('.yatco-toggle-btn[data-currency]');
    
    let currentCurrency = currency;
    let currentLengthUnit = lengthUnit;
    
    // URL parameter support - parse and apply filters from URL
    function getUrlParameter(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }
    
    function updateUrlParameters() {
        const params = new URLSearchParams();
        
        if (keywords && keywords.value) params.set('keywords', keywords.value);
        if (builder && builder.value) params.set('builder', encodeURIComponent(builder.value));
        if (category && category.value) params.set('category', encodeURIComponent(category.value));
        if (type && type.value) params.set('type', encodeURIComponent(type.value));
        if (condition && condition.value) params.set('condition', encodeURIComponent(condition.value));
        if (yearMin && yearMin.value) params.set('year_min', yearMin.value);
        if (yearMax && yearMax.value) params.set('year_max', yearMax.value);
        if (loaMin && loaMin.value) params.set('loa_min', loaMin.value);
        if (loaMax && loaMax.value) params.set('loa_max', loaMax.value);
        if (priceMin && priceMin.value) params.set('price_min', priceMin.value);
        if (priceMax && priceMax.value) params.set('price_max', priceMax.value);
        if (cabins && cabins.value) params.set('cabins', cabins.value);
        if (sort && sort.value) params.set('sort', sort.value);
        if (currentCurrency !== currency) params.set('currency', currentCurrency);
        if (currentLengthUnit !== lengthUnit) params.set('length_unit', currentLengthUnit);
        if (currentPage > 1) params.set('page', currentPage);
        
        const newUrl = params.toString() ? window.location.pathname + '?' + params.toString() : window.location.pathname;
        window.history.pushState({}, '', newUrl);
    }
    
    function applyUrlParameters() {
        // Read URL parameters and apply to filters
        const urlKeywords = getUrlParameter('keywords');
        const urlBuilder = getUrlParameter('builder');
        const urlCategory = getUrlParameter('category');
        const urlType = getUrlParameter('type');
        const urlCondition = getUrlParameter('condition');
        const urlYearMin = getUrlParameter('year_min');
        const urlYearMax = getUrlParameter('year_max');
        const urlLoaMin = getUrlParameter('loa_min');
        const urlLoaMax = getUrlParameter('loa_max');
        const urlPriceMin = getUrlParameter('price_min');
        const urlPriceMax = getUrlParameter('price_max');
        const urlCabins = getUrlParameter('cabins');
        const urlSort = getUrlParameter('sort');
        const urlCurrency = getUrlParameter('currency');
        const urlLengthUnit = getUrlParameter('length_unit');
        const urlPage = getUrlParameter('page');
        
        // Apply filter values
        if (urlKeywords && keywords) keywords.value = urlKeywords;
        if (urlBuilder && builder) builder.value = decodeURIComponent(urlBuilder);
        if (urlCategory && category) category.value = decodeURIComponent(urlCategory);
        if (urlType && type) type.value = decodeURIComponent(urlType);
        if (urlCondition && condition) condition.value = decodeURIComponent(urlCondition);
        if (urlYearMin && yearMin) yearMin.value = urlYearMin;
        if (urlYearMax && yearMax) yearMax.value = urlYearMax;
        if (urlLoaMin && loaMin) loaMin.value = urlLoaMin;
        if (urlLoaMax && loaMax) loaMax.value = urlLoaMax;
        if (urlPriceMin && priceMin) priceMin.value = urlPriceMin;
        if (urlPriceMax && priceMax) priceMax.value = urlPriceMax;
        if (urlCabins && cabins) cabins.value = urlCabins;
        if (urlSort && sort) sort.value = urlSort;
        if (urlCurrency && (urlCurrency === 'USD' || urlCurrency === 'EUR')) {
            currentCurrency = urlCurrency;
        }
        if (urlLengthUnit && (urlLengthUnit === 'FT' || urlLengthUnit === 'M')) {
            currentLengthUnit = urlLengthUnit;
        }
        if (urlPage) {
            const pageNum = parseInt(urlPage);
            if (pageNum > 0) currentPage = pageNum;
        }
        
        // Update toggle buttons if currency or length unit changed
        updateToggleButtons();
    }
    
    function updateToggleButtons() {
        lengthBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.unit === currentLengthUnit);
        });
        currencyBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.currency === currentCurrency);
        });
    }
    
    lengthBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            currentLengthUnit = this.dataset.unit;
            updateToggleButtons();
            filterAndDisplay();
        });
    });
    
    currencyBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            currentCurrency = this.dataset.currency;
            updateToggleButtons();
            filterAndDisplay();
        });
    });
    
    function filterVessels(vesselsToFilter) {
        // Use provided vessels array, or fall back to allVessels if not provided (for backward compatibility)
        const vesselsArray = vesselsToFilter || allVessels;
        
        const keywordVal = keywords ? keywords.value.toLowerCase() : '';
        const builderVal = builder ? builder.value : '';
        const yearMinVal = yearMin ? parseInt(yearMin.value) : null;
        const yearMaxVal = yearMax ? parseInt(yearMax.value) : null;
        const loaMinVal = loaMin ? parseFloat(loaMin.value) : null;
        const loaMaxVal = loaMax ? parseFloat(loaMax.value) : null;
        const priceMinVal = priceMin ? parseFloat(priceMin.value) : null;
        const priceMaxVal = priceMax ? parseFloat(priceMax.value) : null;
        const conditionVal = condition ? condition.value : '';
        const typeVal = type ? type.value : '';
        const categoryVal = category ? category.value : '';
        const cabinsVal = cabins ? parseInt(cabins.value) : null;
        
        return vesselsArray.filter(vessel => {
            const name = vessel.dataset.name || '';
            const location = vessel.dataset.location || '';
            const vesselBuilder = vessel.dataset.builder || '';
            const vesselCategory = vessel.dataset.category || '';
            const vesselType = vessel.dataset.type || '';
            const vesselCondition = vessel.dataset.condition || '';
            const year = parseInt(vessel.dataset.year) || 0;
            const loaFeet = parseFloat(vessel.dataset.loaFeet) || 0;
            const loaMeters = parseFloat(vessel.dataset.loaMeters) || 0;
            const priceUsd = parseFloat(vessel.dataset.priceUsd) || 0;
            const priceEur = parseFloat(vessel.dataset.priceEur) || 0;
            const stateRooms = parseInt(vessel.dataset.stateRooms) || 0;
            
            // Keywords
            if (keywordVal && !name.includes(keywordVal) && !location.includes(keywordVal)) {
                return false;
            }
            
            // Builder
            if (builderVal && vesselBuilder !== builderVal) {
                return false;
            }
            
            // Year
            if (yearMinVal && (year === 0 || year < yearMinVal)) {
                return false;
            }
            if (yearMaxVal && (year === 0 || year > yearMaxVal)) {
                return false;
            }
            
            // Length
            const loa = currentLengthUnit === 'M' ? loaMeters : loaFeet;
            if (loaMinVal && (loa === 0 || loa < loaMinVal)) {
                return false;
            }
            if (loaMaxVal && (loa === 0 || loa > loaMaxVal)) {
                return false;
            }

            // Price
            const price = currentCurrency === 'EUR' ? priceEur : priceUsd;
            if (priceMinVal && (price === 0 || price < priceMinVal)) {
                return false;
            }
            if (priceMaxVal && (price === 0 || price > priceMaxVal)) {
                return false;
            }
            
            // Condition
            if (conditionVal && vesselCondition !== conditionVal) {
                return false;
            }
            
            // Type
            if (typeVal && vesselType !== typeVal) {
                return false;
            }
            
            // Category - exact match (case-sensitive)
            if (categoryVal && vesselCategory !== categoryVal) {
                return false;
            }
            
            // Cabins
            if (cabinsVal && stateRooms < cabinsVal) {
                return false;
            }
            
            return true;
        });
    }
    
    function sortVessels(vessels) {
        const sortVal = sort ? sort.value : '';
        if (!sortVal) return vessels;
        
        return [...vessels].sort((a, b) => {
            switch(sortVal) {
                case 'price_asc':
                    const priceA = currentCurrency === 'EUR' ? parseFloat(a.dataset.priceEur || 0) : parseFloat(a.dataset.priceUsd || 0);
                    const priceB = currentCurrency === 'EUR' ? parseFloat(b.dataset.priceEur || 0) : parseFloat(b.dataset.priceUsd || 0);
                    return priceA - priceB;
                case 'price_desc':
                    const priceA2 = currentCurrency === 'EUR' ? parseFloat(a.dataset.priceEur || 0) : parseFloat(a.dataset.priceUsd || 0);
                    const priceB2 = currentCurrency === 'EUR' ? parseFloat(b.dataset.priceEur || 0) : parseFloat(b.dataset.priceUsd || 0);
                    return priceB2 - priceA2;
                case 'year_desc':
                    return (parseInt(b.dataset.year) || 0) - (parseInt(a.dataset.year) || 0);
                case 'year_asc':
                    return (parseInt(a.dataset.year) || 0) - (parseInt(b.dataset.year) || 0);
                case 'length_desc':
                    const loaA = currentLengthUnit === 'M' ? parseFloat(a.dataset.loaMeters || 0) : parseFloat(a.dataset.loaFeet || 0);
                    const loaB = currentLengthUnit === 'M' ? parseFloat(b.dataset.loaMeters || 0) : parseFloat(b.dataset.loaFeet || 0);
                    return loaB - loaA;
                case 'length_asc':
                    const loaA2 = currentLengthUnit === 'M' ? parseFloat(a.dataset.loaMeters || 0) : parseFloat(a.dataset.loaFeet || 0);
                    const loaB2 = currentLengthUnit === 'M' ? parseFloat(b.dataset.loaMeters || 0) : parseFloat(b.dataset.loaFeet || 0);
                    return loaA2 - loaB2;
                case 'name_asc':
                    return (a.dataset.name || '').localeCompare(b.dataset.name || '');
                default:
                    return 0;
            }
        });
    }
    
    // Pagination - shows 12 vessels per page
    let currentPage = 1;
    const vesselsPerPage = 12;
    
    // Get pagination range with ellipsis for large page counts (e.g., 1 ... 5 6 7 ... 140)
    function getPaginationRange(current, total) {
        const delta = 2;
        const range = [];
        const rangeWithDots = [];
        
        if (total <= 7) {
            // Show all pages if 7 or fewer
            for (let i = 1; i <= total; i++) {
                rangeWithDots.push(i);
            }
            return rangeWithDots;
        }
        
        for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
            range.push(i);
        }
        
        if (current - delta > 2) {
            rangeWithDots.push(1, '...');
        } else {
            rangeWithDots.push(1);
        }
        
        rangeWithDots.push(...range);
        
        if (current + delta < total - 1) {
            rangeWithDots.push('...', total);
        } else {
            if (total > 1) {
                rangeWithDots.push(total);
            }
        }
        
        return rangeWithDots;
    }
    
    function paginateVessels(vessels) {
        const start = (currentPage - 1) * vesselsPerPage;
        const end = start + vesselsPerPage;
        return vessels.slice(start, end);
    }
    
    function updatePaginationControls(totalVessels) {
        console.log('[YATCO] updatePaginationControls: Called with', totalVessels, 'vessels, currentPage:', currentPage);
        const totalPages = Math.ceil(totalVessels / vesselsPerPage);
        console.log('[YATCO] updatePaginationControls: Total pages calculated:', totalPages);
        
        if (totalPages <= 1) {
            // Hide pagination if only one page
            console.log('[YATCO] updatePaginationControls: Only 1 page or less, hiding pagination');
            const paginationContainer = document.querySelector('.yatco-pagination');
            if (paginationContainer) {
                paginationContainer.style.display = 'none';
            } else {
                console.log('[YATCO] updatePaginationControls: Pagination container not found (may not exist yet)');
            }
            return;
        }
        
        console.log('[YATCO] updatePaginationControls: Creating pagination for', totalPages, 'pages');
        const pageRange = getPaginationRange(currentPage, totalPages);
        console.log('[YATCO] updatePaginationControls: Page range:', pageRange);
        
        let paginationHtml = '<div class="yatco-pagination">';
        
        // Previous button
        if (currentPage > 1) {
            paginationHtml += `<button class="yatco-pagination-btn yatco-prev-btn" onclick="window.yatcoGoToPage(${currentPage - 1})">‹ Previous</button>`;
        }
        
        // Page numbers
        pageRange.forEach((page, index) => {
            if (page === '...') {
                paginationHtml += `<span class="yatco-page-dots">...</span>`;
            } else {
                const isActive = page === currentPage;
                paginationHtml += `<button class="yatco-pagination-btn yatco-page-num ${isActive ? 'active' : ''}" onclick="window.yatcoGoToPage(${page})">${page}</button>`;
            }
        });
        
        // Next button
        if (currentPage < totalPages) {
            paginationHtml += `<button class="yatco-pagination-btn yatco-next-btn" onclick="window.yatcoGoToPage(${currentPage + 1})">Next ›</button>`;
        }
        
        paginationHtml += `<span class="yatco-page-info">Page ${currentPage} of ${totalPages}</span>`;
        paginationHtml += '</div>';
        
        let paginationContainer = document.querySelector('.yatco-pagination');
        if (!paginationContainer) {
            console.log('[YATCO] updatePaginationControls: Pagination container not found, creating new one');
            paginationContainer = document.createElement('div');
            paginationContainer.className = 'yatco-pagination';
            if (grid && grid.parentNode) {
                grid.parentNode.insertBefore(paginationContainer, grid.nextSibling);
                console.log('[YATCO] updatePaginationControls: Created and inserted pagination container');
            } else {
                console.error('[YATCO] updatePaginationControls: grid or grid.parentNode not found, cannot insert pagination!');
            }
        } else {
            console.log('[YATCO] updatePaginationControls: Using existing pagination container');
        }
        
        console.log('[YATCO] updatePaginationControls: Setting pagination HTML (length:', paginationHtml.length, 'chars)');
        paginationContainer.innerHTML = paginationHtml;
        paginationContainer.style.display = 'flex';
        console.log('[YATCO] updatePaginationControls: Pagination displayed, container display:', paginationContainer.style.display);
    }
    
    window.yatcoGoToPage = function(page) {
        currentPage = page;
        filterAndDisplay();
    };
    
    // Cache for vessels array to avoid expensive DOM queries on every filter call
    let vesselsCache = null;
    let vesselsCacheTime = 0;
    const VESSELS_CACHE_DURATION = 500; // Cache for 500ms (reduces DOM queries while staying fresh)
    
    function getVesselsFromDOM() {
        // Use cached vessels if available and recent, otherwise query DOM
        const now = Date.now();
        if (vesselsCache && (now - vesselsCacheTime) < VESSELS_CACHE_DURATION) {
            console.log('[YATCO] getVesselsFromDOM: Using cached vessels, count:', vesselsCache.length);
            return vesselsCache;
        }
        console.log('[YATCO] getVesselsFromDOM: Querying DOM for vessels...');
        const startTime = Date.now();
        vesselsCache = Array.from(document.querySelectorAll('.yatco-vessel-card'));
        const queryTime = Date.now() - startTime;
        vesselsCacheTime = now;
        allVessels = vesselsCache; // Keep allVessels in sync
        console.log('[YATCO] getVesselsFromDOM: Found', vesselsCache.length, 'vessels in', queryTime, 'ms');
        return vesselsCache;
    }
    
    function invalidateVesselsCache() {
        console.log('[YATCO] invalidateVesselsCache: Clearing cache');
        vesselsCache = null;
        vesselsCacheTime = 0;
    }
    
    function filterAndDisplay() {
        console.log('[YATCO] filterAndDisplay: Starting, currentPage:', currentPage);
        const funcStartTime = Date.now();
        
        try {
            // Get vessels (use cache if available, otherwise query DOM)
            const currentVessels = getVesselsFromDOM();
            console.log('[YATCO] filterAndDisplay: Got', currentVessels.length, 'vessels from DOM');
            
            // Early return if no vessels (prevents errors)
            if (!currentVessels || currentVessels.length === 0) {
                console.log('[YATCO] filterAndDisplay: No vessels found, returning early');
                if (resultsCount) {
                    resultsCount.innerHTML = '0 of <span id="yatco-total-count">0</span> YACHTS FOUND';
                }
                const paginationContainer = document.querySelector('.yatco-pagination');
                if (paginationContainer) {
                    paginationContainer.style.display = 'none';
                }
                return;
            }
            
            // Use the vessels for filtering
            const filterStartTime = Date.now();
            const filtered = filterVessels(currentVessels);
            console.log('[YATCO] filterAndDisplay: Filtered to', filtered.length, 'vessels in', Date.now() - filterStartTime, 'ms');
            
            const sortStartTime = Date.now();
            const sorted = sortVessels(filtered);
            console.log('[YATCO] filterAndDisplay: Sorted', sorted.length, 'vessels in', Date.now() - sortStartTime, 'ms');
            
            const paginateStartTime = Date.now();
            const paginated = paginateVessels(sorted);
            console.log('[YATCO] filterAndDisplay: Paginated to', paginated.length, 'vessels (page', currentPage, ') in', Date.now() - paginateStartTime, 'ms');
            
            // Get total filtered count for display
            const totalFiltered = sorted.length;
            const totalPages = Math.ceil(totalFiltered / vesselsPerPage);
            console.log('[YATCO] filterAndDisplay: Total filtered:', totalFiltered, ', Total pages:', totalPages, ', Showing page:', currentPage);
            
            // Hide all vessels first - use requestAnimationFrame to avoid blocking UI
            console.log('[YATCO] filterAndDisplay: Scheduling DOM updates via requestAnimationFrame');
            requestAnimationFrame(function() {
                const domUpdateStartTime = Date.now();
                console.log('[YATCO] filterAndDisplay: requestAnimationFrame callback executing');
                
                // Batch hide operations for better performance
                console.log('[YATCO] filterAndDisplay: Hiding', currentVessels.length, 'vessels');
                for (let i = 0; i < currentVessels.length; i++) {
                    currentVessels[i].style.display = 'none';
                }
                
                // Show paginated vessels
                console.log('[YATCO] filterAndDisplay: Showing', paginated.length, 'paginated vessels');
                for (let i = 0; i < paginated.length; i++) {
                    paginated[i].style.display = '';
                }
                console.log('[YATCO] filterAndDisplay: DOM visibility updated in', Date.now() - domUpdateStartTime, 'ms');
                
                // Update count
                if (resultsCount) {
                    const shownStart = totalFiltered > 0 ? (currentPage - 1) * vesselsPerPage + 1 : 0;
                    const shownEnd = Math.min(currentPage * vesselsPerPage, totalFiltered);
                    const countHtml = shownStart + ' - ' + shownEnd + ' of <span id="yatco-total-count">' + totalFiltered + '</span> YACHTS FOUND';
                    console.log('[YATCO] filterAndDisplay: Updating count HTML:', countHtml);
                    resultsCount.innerHTML = countHtml;
                } else {
                    console.warn('[YATCO] filterAndDisplay: resultsCount element not found!');
                }
                
                // Update pagination controls (MUST show pagination if more than 1 page)
                console.log('[YATCO] filterAndDisplay: Calling updatePaginationControls with', totalFiltered, 'vessels');
                updatePaginationControls(totalFiltered);
                
                // Update URL parameters (defer to avoid blocking)
                setTimeout(function() {
                    updateUrlParameters();
                }, 0);
                
                console.log('[YATCO] filterAndDisplay: Completed in', Date.now() - funcStartTime, 'ms total');
            });
        } catch (error) {
            console.error('[YATCO] filterAndDisplay: ERROR:', error);
            console.error('[YATCO] filterAndDisplay: Error stack:', error.stack);
        }
    }
    
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (keywords) keywords.value = '';
            if (builder) builder.value = '';
            if (yearMin) yearMin.value = '';
            if (yearMax) yearMax.value = '';
            if (loaMin) loaMin.value = '';
            if (loaMax) loaMax.value = '';
            if (priceMin) priceMin.value = '';
            if (priceMax) priceMax.value = '';
            if (condition) condition.value = '';
            if (type) type.value = '';
            if (category) category.value = '';
            if (cabins) cabins.value = '';
            if (sort) sort.value = '';
            currentCurrency = currency;
            currentLengthUnit = lengthUnit;
            currentPage = 1;
            updateToggleButtons();
            // Clear URL parameters on reset
            window.history.pushState({}, '', window.location.pathname);
            filterAndDisplay();
        });
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            currentPage = 1;
            filterAndDisplay();
        });
    }
    
    if (sort) {
        sort.addEventListener('change', function() {
            currentPage = 1;
            filterAndDisplay();
        });
    }
    
    // Initialize - defer everything to prevent lockup
    console.log('[YATCO] Script loaded, starting initialization...');
    
    // Apply URL parameters first (if present in URL) - but do it asynchronously
    setTimeout(function() {
        console.log('[YATCO] Initialization timeout fired, document.readyState:', document.readyState);
        
        try {
            applyUrlParameters();
            console.log('[YATCO] URL parameters applied');
            updateToggleButtons();
            console.log('[YATCO] Toggle buttons updated');
            
            // Wait for DOM to be fully interactive before filtering
            // This ensures the page doesn't lock up during initial render
            if (document.readyState === 'complete') {
                // Page already loaded, wait a bit more for any AJAX-loaded content
                console.log('[YATCO] Document already complete, waiting 300ms before filtering');
                setTimeout(function() {
                    console.log('[YATCO] Calling filterAndDisplay (document complete path)');
                    filterAndDisplay();
                }, 300);
            } else {
                // Wait for page to fully load
                console.log('[YATCO] Waiting for window.load event');
                window.addEventListener('load', function() {
                    console.log('[YATCO] Window.load fired, waiting 300ms before filtering');
                    setTimeout(function() {
                        console.log('[YATCO] Calling filterAndDisplay (window.load path)');
                        filterAndDisplay();
                    }, 300);
                });
            }
        } catch (error) {
            console.error('[YATCO] Error during initialization:', error);
        }
    }, 0);
})();
</script>
<?php


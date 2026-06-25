// Детекция Android для применения специальных шрифтов
if (/Android/i.test(navigator.userAgent)) {
    document.documentElement.classList.add('is-android');
}

function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
}

const savedTheme = localStorage.getItem('theme') || 'light';
document.documentElement.setAttribute('data-theme', savedTheme);

let currentZoom = 1;
let currentImageSrc = '';
let isDragging = false;
let startX, startY, translateX = 0, translateY = 0;

document.addEventListener('DOMContentLoaded', function() {
    const contentImages = document.querySelectorAll('.content img');
    contentImages.forEach(function(img) {
        img.addEventListener('click', function(e) {
            e.stopPropagation();
            openImageModal(this.src);
        });
    });
    
    // Подгрузка глобального фона и шрифтов
    applyGlobalSettings();
});

function openImageModal(src) {
    currentImageSrc = src;
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    const container = document.getElementById('imageContainer');
    
    modalImg.src = src;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    currentZoom = 1;
    translateX = 0;
    translateY = 0;
    updateImageTransform();
    
    modalImg.onload = function() {
        centerImage();
    };
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';
    currentZoom = 1;
    translateX = 0;
    translateY = 0;
}

function centerImage() {
    const modalImg = document.getElementById('modalImage');
    const container = document.getElementById('imageContainer');
    const containerRect = container.getBoundingClientRect();
    const imgWidth = modalImg.naturalWidth * currentZoom;
    const imgHeight = modalImg.naturalHeight * currentZoom;
    
    translateX = (containerRect.width - imgWidth) / 2;
    translateY = (containerRect.height - imgHeight) / 2;
    updateImageTransform();
}

function updateImageTransform() {
    const modalImg = document.getElementById('modalImage');
    const zoomLevel = document.getElementById('zoomLevel');
    modalImg.style.transform = 'translate(' + translateX + 'px, ' + translateY + 'px) scale(' + currentZoom + ')';
    zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
}

function zoomIn() {
    if (currentZoom < 5) {
        currentZoom += 0.25;
        updateImageTransform();
    }
}

function zoomOut() {
    if (currentZoom > 0.25) {
        currentZoom -= 0.25;
        updateImageTransform();
    }
}

function resetZoom() {
    currentZoom = 1;
    centerImage();
}

function downloadImage() {
    const link = document.createElement('a');
    link.href = currentImageSrc;
    link.download = currentImageSrc.split('/').pop();
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Загрузка глобальных настроек (фон, шрифты)
async function applyGlobalSettings() {
    try {
        // Загружаем глобальные настройки
        const globalRes = await fetch('../global-settings.json?t=' + Date.now());
        const globalSettings = globalRes.ok ? await globalRes.json() : {};
        
        // Скрываем или показываем "Powered by NPBlog"
        const poweredBy = document.querySelector('.powered-by');
        if (poweredBy) {
            if (globalSettings.hidePoweredBy) {
                poweredBy.style.display = 'none';
            } else {
                poweredBy.style.display = '';
            }
        }
        
        // Получаем ID статьи
        const metaTag = document.querySelector('meta[name="post-id"]');
        const postId = metaTag ? metaTag.getAttribute('content') : null;
        
        // Загружаем настройки фонов для статей
        let postBackgrounds = {};
        try {
            const bgRes = await fetch('../post_backgrounds.json?t=' + Date.now());
            if (bgRes.ok) {
                postBackgrounds = await bgRes.json();
            }
        } catch (e) {
            console.warn('Could not load post backgrounds', e);
        }
        
        // Применяем настройки
        let appliedBg = false;
        if (postId && postBackgrounds[postId]) {
            const bgSettings = postBackgrounds[postId];
            if (bgSettings.background) {
                applyBackground(bgSettings);
                appliedBg = true;
            }
            if (bgSettings.overlayEnabled) {
                applyOverlay(bgSettings);
            }
        }
        
        // Если для статьи нет своего фона, применяем глобальный
        if (!appliedBg && globalSettings.background) {
            applyBackground(globalSettings);
        }
    } catch (error) {
        console.error('Ошибка загрузки настроек:', error);
    }
}

function applyBackground(settings) {
    const bgFile = settings.background;
    const bgMode = settings.backgroundMode || 'cover';
    const bgScope = settings.backgroundScope || 'content';
    
    let backgroundStyle = '';
    if (bgMode === 'repeat') {
        backgroundStyle = `url('../backgrounds/${bgFile}') repeat auto`;
    } else if (bgMode === 'contain') {
        backgroundStyle = `url('../backgrounds/${bgFile}') no-repeat center/contain`;
    } else { // cover
        backgroundStyle = `url('../backgrounds/${bgFile}') no-repeat center/cover`;
    }
    
    if (bgScope === 'fullpage') {
        document.body.style.background = backgroundStyle;
        document.body.style.backgroundAttachment = 'fixed';
    } else {
        const contentWrapper = document.createElement('div');
        contentWrapper.className = 'content-wrapper';
        contentWrapper.style.background = backgroundStyle;
        contentWrapper.style.minHeight = '100vh';
        
        // Move children
        const h1 = document.querySelector('h1');
        if (!h1) return;
        
        // wrap from h1 to back-link
        const backLink = document.querySelector('.back-link');
        
        let node = h1;
        const nodesToMove = [];
        while (node) {
            nodesToMove.push(node);
            if (node === backLink) break;
            node = node.nextSibling;
        }
        
        h1.parentNode.insertBefore(contentWrapper, h1);
        nodesToMove.forEach(n => contentWrapper.appendChild(n));
    }
}

function applyOverlay(settings) {
    const overlayColor = settings.overlayColor;
    const overlayOpacity = settings.overlayOpacity;
    
    const hex = overlayColor.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    const alpha = overlayOpacity / 100;
    
    const overlayWrapper = document.createElement('div');
    overlayWrapper.className = 'overlay-wrapper';
    overlayWrapper.style.background = `rgba(${r}, ${g}, ${b}, ${alpha})`;
    
    const h1 = document.querySelector('h1');
    if (!h1) return;
    
    const backLink = document.querySelector('.back-link');
    
    let node = h1;
    const nodesToMove = [];
    while (node) {
        nodesToMove.push(node);
        if (node === backLink) break;
        node = node.nextSibling;
    }
    
    h1.parentNode.insertBefore(overlayWrapper, h1);
    nodesToMove.forEach(n => overlayWrapper.appendChild(n));
}

// Слушатели событий
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('imageContainer');
    const modalImg = document.getElementById('modalImage');
    
    if (modalImg) {
        modalImg.addEventListener('dragstart', function(e) {
            e.preventDefault();
        });
    }
    
    if (container) {
        container.addEventListener('mousedown', function(e) {
            if (e.target === modalImg) {
                e.preventDefault();
                isDragging = true;
                startX = e.clientX - translateX;
                startY = e.clientY - translateY;
                container.classList.add('dragging');
            }
        });
    }
});

document.addEventListener('mousemove', function(e) {
    if (isDragging) {
        e.preventDefault();
        translateX = e.clientX - startX;
        translateY = e.clientY - startY;
        updateImageTransform();
    }
});

document.addEventListener('mouseup', function() {
    isDragging = false;
    const container = document.getElementById('imageContainer');
    if (container) container.classList.remove('dragging');
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });
    }
});

// ——— Система уведомлений ———
    function showNotification(message, type = 'info', title = '') {
        const container = document.getElementById('notificationContainer');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        const titles = {
            success: title || 'Успешно',
            error: title || 'Ошибка',
            warning: title || 'Внимание',
            info: title || 'Информация'
        };
        
        notification.innerHTML = `
            <div class="notification-icon">${icons[type] || icons.info}</div>
            <div class="notification-content">
                <div class="notification-title">${titles[type]}</div>
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close" onclick="closeNotification(this)">×</button>
        `;
        
        container.appendChild(notification);
        
        // Анимация появления
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Автоматическое скрытие через 5 секунд
        setTimeout(() => {
            closeNotification(notification.querySelector('.notification-close'));
        }, 5000);
    }
    
    function closeNotification(btn) {
        const notification = btn.closest('.notification');
        if (!notification) return;
        
        notification.classList.remove('show');
        notification.classList.add('hide');
        
        setTimeout(() => {
            notification.remove();
        }, 400);
    }

    let currentEditId = null;
    let editorMode = 'visual'; // 'visual' | 'code'
    let savedRange = null;
    
    // Система истории изменений
    let historyStack = [];
    let historyIndex = -1;
    let isRestoringHistory = false;
    let historySaveTimeout = null;
    
    // Загружаем пользовательские шрифты при инициализации редактора
    function loadEditorCustomFonts() {
        fetch('get_custom_fonts.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.fonts.length > 0) {
                    let styleTag = document.getElementById('editorCustomFontsStyles');
                    if (!styleTag) {
                        styleTag = document.createElement('style');
                        styleTag.id = 'editorCustomFontsStyles';
                        document.head.appendChild(styleTag);
                    }
                    
                    let fontFaceRules = '';
                    
                    data.fonts.forEach(font => {
                        let format = 'truetype';
                        if (font.format === 'woff') format = 'woff';
                        else if (font.format === 'woff2') format = 'woff2';
                        else if (font.format === 'otf') format = 'opentype';
                        
                        fontFaceRules += `
                            @font-face {
                                font-family: '${font.name}';
                                src: url('${font.path}') format('${format}');
                            }
                        `;
                    });
                    
                    styleTag.textContent = fontFaceRules;
                }
            })
            .catch(error => {
                console.error('Ошибка загрузки шрифтов:', error);
            });
    }
    
    // Загружаем шрифты при загрузке страницы
    loadEditorCustomFonts();
    
    // Очищаем историю при загрузке страницы
    clearHistory();
    
    // Инициализируем историю с пустым состоянием
    setTimeout(() => {
        saveToHistory();
        updateUndoRedoButtons();
    }, 100);
    
    let linkInsertStart = 0;
    let linkInsertEnd = 0;
    let colorInsertStart = 0;
    let colorInsertEnd = 0;

    function saveSelection() {
        const ve = document.getElementById('contentVisual');
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0);
        if (ve && ve.contains(range.commonAncestorContainer)) {
            savedRange = range.cloneRange();
        }
    }

    // Стабильная логика тулбара: не даём кнопкам забирать фокус у редактора.
    // Это сохраняет каретку/выделение и делает execCommand предсказуемым.
    (function initToolbarFocusGuard() {
        var bar = document.getElementById('formatBarRow');
        if (!bar) return;
        bar.addEventListener('mousedown', function(e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            // Не ломаем клики внутри поповеров/диалогов
            if (e.target.closest('.font-size-popover, .font-family-popover, .color-palette-popover')) return;
            e.preventDefault();
            if (editorMode === 'visual') {
                var ve = document.getElementById('contentVisual');
                if (ve) ve.focus();
                saveSelection();
            } else {
                var ta = document.getElementById('content');
                if (ta) ta.focus();
            }
        }, true);
    })();

    // Надёжно обновляем savedRange при наборе/кликах внутри редактора (пробел/Enter/мышь и т.п.)
    (function initVisualSelectionTracking() {
        var ve = document.getElementById('contentVisual');
        if (!ve) return;
        ['mouseup','keyup','input','click','focus','touchend','compositionend'].forEach(function(evt) {
            ve.addEventListener(evt, function() {
                if (editorMode === 'visual') saveSelection();
            }, true);
        });
    })();

    function insertHtmlAtCursor(html) {
        var ve = document.getElementById('contentVisual');
        if (ve) ve.focus();
        
        // Restore selection if we have one
        if (savedRange) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
        
        let sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
            let range = sel.getRangeAt(0);
            range.deleteContents();
            
            let el = document.createElement("div");
            el.innerHTML = html;
            let frag = document.createDocumentFragment(), node, lastNode;
            while ( (node = el.firstChild) ) {
                lastNode = frag.appendChild(node);
            }
            range.insertNode(frag);
            
            if (lastNode) {
                range = range.cloneRange();
                range.setStartAfter(lastNode);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
                saveSelection();
            }
            saveToHistory();
        }
    }

    function formatHTML(html) {
        if (!html) return '';
        
        // 1. Выделяем блоки <pre>, чтобы сохранить их внутреннее форматирование/пробелы
        let preBlocks = [];
        let formatted = html.replace(/<pre[^>]*>[\s\S]*?<\/pre>/gi, function(match) {
            preBlocks.push(match);
            return '___PRE_PLACEHOLDER_' + (preBlocks.length - 1) + '___';
        });
        
        const blockTags = [
            'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 
            'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 
            'blockquote', 'section', 'article', 'header', 'footer', 'hr'
        ];
        
        // Очищаем от старых переносов строк
        formatted = formatted.replace(/\r/g, '');
        
        // Удаляем лишние пробелы вокруг блочных тегов, чтобы сбросить структуру
        blockTags.forEach(tag => {
            const closeRegex = new RegExp('\\s*</' + tag + '>\\s*', 'gi');
            formatted = formatted.replace(closeRegex, '</' + tag + '>');
            
            const openRegex = new RegExp('\\s*<(' + tag + ')((\\s+[^>]*?>)|>)\\s*', 'gi');
            formatted = formatted.replace(openRegex, '<$1$2');
        });
        
        // Вставляем переносы строк перед и после блочных тегов
        blockTags.forEach(tag => {
            const openRegex = new RegExp('<(' + tag + ')((\\s+[^>]*?>)|>)', 'gi');
            formatted = formatted.replace(openRegex, '\n<$1$2');
            
            const closeRegex = new RegExp('</(' + tag + ')>', 'gi');
            formatted = formatted.replace(closeRegex, '</$1>\n');
        });
        
        // Форматируем одиночные теги (hr, br)
        formatted = formatted.replace(/<hr(\s+[^>]*?>| >|>)/gi, '\n<hr$1\n');
        formatted = formatted.replace(/<br(\s*\/)?>/gi, '<br$1>\n');
        
        // Разбиваем на строки и вычисляем вложенность
        let lines = formatted.split('\n');
        let pad = 0;
        let result = [];
        
        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();
            if (!line) continue;
            
            let startsWithClosing = false;
            for (let j = 0; j < blockTags.length; j++) {
                if (line.toLowerCase().startsWith('</' + blockTags[j])) {
                    startsWithClosing = true;
                    break;
                }
            }
            
            let startsWithOpening = false;
            if (!startsWithClosing) {
                for (let j = 0; j < blockTags.length; j++) {
                    let tag = blockTags[j];
                    let reg = new RegExp('^<' + tag + '(\\s+|>)', 'i');
                    if (reg.test(line)) {
                        let hasClose = new RegExp('</' + tag + '>$', 'i').test(line);
                        if (!hasClose && tag !== 'hr') {
                            startsWithOpening = true;
                        }
                        break;
                    }
                }
            }
            
            if (startsWithClosing) {
                pad = Math.max(0, pad - 1);
            }
            
            result.push('    '.repeat(pad) + line);
            
            if (startsWithOpening) {
                pad++;
            }
        }
        
        let finalHtml = result.join('\n');
        
        // Восстанавливаем сохраненные блоки <pre>
        for (let i = 0; i < preBlocks.length; i++) {
            finalHtml = finalHtml.replace('___PRE_PLACEHOLDER_' + i + '___', preBlocks[i]);
        }
        
        return finalHtml.trim();
    }

    function cleanContentForSave(html) {
        // Создаем временный контейнер для очистки HTML
        var temp = document.createElement('div');
        temp.innerHTML = html;
        
        // Удаляем все элементы интерфейса редактора
        var elementsToRemove = temp.querySelectorAll(
            '.image-toolbar, ' +
            '.image-align-dropdown, ' +
            '.image-size-indicator, ' +
            '.image-resize-handle, ' +
            '.blog-image-overlay, ' +
            '.column-resizer'
        );
        elementsToRemove.forEach(function(el) {
            el.parentNode.removeChild(el);
        });
        
        // Удаляем атрибуты data-image-id, data-media-id, data-media-type
        var wraps = temp.querySelectorAll('[data-image-id], [data-media-id], [data-media-type]');
        wraps.forEach(function(el) {
            el.removeAttribute('data-image-id');
            el.removeAttribute('data-media-id');
            el.removeAttribute('data-media-type');
        });
        
        // Очистка таблиц: удаляем атрибуты редактирования и состояния ресайзера
        var tables = temp.querySelectorAll('table[data-resizers-added]');
        tables.forEach(function(table) {
            table.removeAttribute('data-resizers-added');
        });
        
        var editableCells = temp.querySelectorAll('[contenteditable]');
        editableCells.forEach(function(el) {
            el.removeAttribute('contenteditable');
        });
        
        // Удаляем классы selected
        var selected = temp.querySelectorAll('.selected');
        selected.forEach(function(el) {
            el.classList.remove('selected');
        });
        
        // Убираем служебные ZWS (\u200B) и форматируем HTML
        const cleanedHtml = temp.innerHTML.replace(/\u200B/g, '');
        return formatHTML(cleanedHtml);
    }

    function setMode(mode) {
        editorMode = mode;
        const ta = document.getElementById('content');
        const ve = document.getElementById('contentVisual');
        const visualBtn = document.getElementById('modeVisualBtn');
        const codeBtn = document.getElementById('modeCodeBtn');
        
        if (mode === 'visual') {
            // sync from code -> visual
            if (ta.style.display !== 'none') {
                ve.innerHTML = ta.value;
                wrapExistingEditorImages();
                addColumnResizers(); // Добавляем ручки изменения размера столбцов
            }
            ve.style.display = '';
            ta.style.display = 'none';
            visualBtn.classList.add('active');
            codeBtn.classList.remove('active');
        } else {
            hideGlobalMediaOverlay();
            // sync from visual -> code - очищаем от элементов интерфейса
            if (ve.style.display !== 'none') {
                ta.value = cleanContentForSave(ve.innerHTML);
            }
            ta.style.display = '';
            ve.style.display = 'none';
            codeBtn.classList.add('active');
            visualBtn.classList.remove('active');
        }
    }

    const toggleState = { b: false, i: false, u: false, s: false };

    function setBtnActive(id, active) {
        const btn = document.getElementById(id);
        if (!btn) return;
        if (active) btn.classList.add('active'); else btn.classList.remove('active');
    }

    function updateActiveButtons() {
        if (editorMode !== 'visual') return;
        const ve = document.getElementById('contentVisual');
        const sel = window.getSelection();
        // Не подсвечиваем кнопки, если выделение/каретка не в поле статьи
        if (!ve || !sel || sel.rangeCount === 0) {
            ['btn-bold','btn-italic','btn-underline','btn-strike','btn-sup','btn-sub','btn-h2'].forEach(function(id){ setBtnActive(id, false); });
            return;
        }
        const r = sel.getRangeAt(0);
        if (!ve.contains(r.commonAncestorContainer)) {
            ['btn-bold','btn-italic','btn-underline','btn-strike','btn-sup','btn-sub','btn-h2'].forEach(function(id){ setBtnActive(id, false); });
            return;
        }
        
        const node = r.startContainer;
        
        const isBold = !!isFormatApplied(node, 'B') || !!isFormatApplied(node, 'STRONG');
        const isItalic = !!isFormatApplied(node, 'I') || !!isFormatApplied(node, 'EM');
        const isUnderline = !!isFormatApplied(node, 'U');
        const isStrike = !!isFormatApplied(node, 'S') || !!isFormatApplied(node, 'STRIKE') || !!isFormatApplied(node, 'DEL');
        const isSup = !!isFormatApplied(node, 'SUP');
        const isSub = !!isFormatApplied(node, 'SUB');
        const isH2 = !!isFormatApplied(node, 'H2');

        // верхняя панель
        setBtnActive('btn-bold', isBold);
        setBtnActive('btn-italic', isItalic);
        setBtnActive('btn-underline', isUnderline);
        setBtnActive('btn-strike', isStrike);
        setBtnActive('btn-sup', isSup);
        setBtnActive('btn-sub', isSub);
        setBtnActive('btn-h2', isH2);

        // Находим текущий примененный шрифт и размер
        let fontName = '';
        let fontSize = '';
        
        let checkNode = node;
        while (checkNode && checkNode !== ve) {
            if (checkNode.nodeType === Node.ELEMENT_NODE) {
                if (!fontName && checkNode.style.fontFamily) {
                    fontName = checkNode.style.fontFamily.split(',')[0].replace(/['"]/g, '').trim();
                }
                if (!fontSize && checkNode.style.fontSize) {
                    fontSize = checkNode.style.fontSize;
                }
            }
            checkNode = checkNode.parentNode;
        }
        
        if (!fontName) fontName = 'Arial';
        if (!fontSize) fontSize = '14px';
        
        const fontBtn = document.getElementById('fontFamilyBtn');
        if (fontBtn) {
            fontBtn.textContent = fontName;
            fontBtn.style.fontFamily = fontName;
        }
        
        const sizeBtn = document.getElementById('fontSizeBtn');
        if (sizeBtn) {
            sizeBtn.textContent = fontSize;
        }
    }

    // Теги форматирования, которые нужно «покидать» при выключении режима
    var FORMAT_TAGS = {
        bold: ['B','STRONG'],
        italic: ['I','EM'],
        underline: ['U'],
        strikeThrough: ['S','STRIKE','DEL'],
        superscript: ['SUP'],
        subscript: ['SUB']
    };

    /**
     * При выключении inline-формата на collapsed каретке:
     *  - Если форматирующий тег пуст / содержит только <br> (новая строка после Enter)
     *    → полностью убираем обёртку (unwrap), каретка остаётся на той же строке.
     *  - Иначе (текст + пробел) → вставляем ZWS после тега и ставим туда каретку.
     */
    function escapeFormatNode(cmd, ve) {
        var tags = FORMAT_TAGS[cmd];
        if (!tags) return;
        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        var range = sel.getRangeAt(0);
        if (!range.collapsed) return;

        // Ищем ближайший форматирующий предок
        var node = range.startContainer;
        if (node.nodeType === Node.TEXT_NODE) node = node.parentNode;
        var formatEl = null;
        while (node && node !== ve) {
            if (node.nodeType === Node.ELEMENT_NODE && tags.indexOf(node.tagName) !== -1) {
                formatEl = node;
                break;
            }
            node = node.parentNode;
        }
        if (!formatEl) return;

        // Проверяем, пустой ли тег (только пробелы/ZWS и/или <br>)
        var text = formatEl.textContent.replace(/[\u200B\s]/g, '');
        var isEmpty = text.length === 0;

        if (isEmpty) {
            // Unwrap: заменяем <b><br></b> на просто <br>
            var parent = formatEl.parentNode;
            var br = formatEl.querySelector('br');
            if (!br) br = document.createElement('br');
            parent.insertBefore(br, formatEl);
            parent.removeChild(formatEl);
            // Ставим каретку перед <br> (на эту строку)
            var newRange = document.createRange();
            newRange.setStartBefore(br);
            newRange.collapse(true);
            sel.removeAllRanges();
            sel.addRange(newRange);
        } else {
            // Вставляем ZWS после тега и ставим туда каретку
            var zws = document.createTextNode('\u200B');
            formatEl.parentNode.insertBefore(zws, formatEl.nextSibling);
            var newRange = document.createRange();
            newRange.setStart(zws, 1);
            newRange.collapse(true);
            sel.removeAllRanges();
            sel.addRange(newRange);
        }
    }

    function isFormatApplied(node, tag) {
        const tagName = tag.toUpperCase();
        let current = node;
        while (current && current.id !== 'contentVisual') {
            if (current.nodeType === Node.ELEMENT_NODE && current.tagName.toUpperCase() === tagName) {
                return current;
            }
            current = current.parentNode;
        }
        return null;
    }

    function toggleInlineFormat(tag) {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        
        let range = sel.getRangeAt(0);
        const existingNode = isFormatApplied(range.commonAncestorContainer, tag);
        
        if (existingNode) {
            const parent = existingNode.parentNode;
            while (existingNode.firstChild) {
                parent.insertBefore(existingNode.firstChild, existingNode);
            }
            parent.removeChild(existingNode);
        } else {
            const el = document.createElement(tag);
            if (range.collapsed) {
                el.innerHTML = '\u200B';
                range.insertNode(el);
                range.selectNodeContents(el);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
            } else {
                try {
                    const contents = range.extractContents();
                    el.appendChild(contents);
                    range.insertNode(el);
                    sel.removeAllRanges();
                    const newRange = document.createRange();
                    newRange.selectNodeContents(el);
                    sel.addRange(newRange);
                } catch(e) {
                    console.error("Selection crosses block boundaries", e);
                }
            }
        }
    }

    function toggleBlockFormat(tag) {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0);
        
        let node = range.startContainer;
        let blockNode = null;
        const blockTags = ['P', 'H1', 'H2', 'H3', 'DIV'];
        while (node && node.id !== 'contentVisual') {
            if (node.nodeType === 1 && blockTags.includes(node.tagName.toUpperCase())) {
                blockNode = node;
                break;
            }
            node = node.parentNode;
        }
        
        if (blockNode) {
            const targetTag = tag.toUpperCase();
            if (blockNode.tagName.toUpperCase() === targetTag) {
                const p = document.createElement('p');
                p.innerHTML = blockNode.innerHTML;
                blockNode.parentNode.replaceChild(p, blockNode);
                
                // Перемещаем каретку внутрь нового абзаца
                const newRange = document.createRange();
                newRange.selectNodeContents(p);
                newRange.collapse(false);
                sel.removeAllRanges();
                sel.addRange(newRange);
            } else {
                const h = document.createElement(tag);
                h.innerHTML = blockNode.innerHTML;
                blockNode.parentNode.replaceChild(h, blockNode);
                
                // Перемещаем каретку внутрь нового заголовка
                const newRange = document.createRange();
                newRange.selectNodeContents(h);
                newRange.collapse(false);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        } else {
            const block = document.createElement(tag);
            if (!range.collapsed) {
                try {
                    const contents = range.extractContents();
                    block.appendChild(contents);
                    range.insertNode(block);
                    
                    // Выделяем содержимое нового блока
                    const newRange = document.createRange();
                    newRange.selectNodeContents(block);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                } catch(e) {
                    console.error("Extract contents failed", e);
                }
            } else {
                block.innerHTML = '<br>';
                range.insertNode(block);
                
                // Ставим каретку внутрь созданного блока перед <br>
                const newRange = document.createRange();
                newRange.setStart(block, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        }
    }

    function formatText(tag) {
        const ta = document.getElementById('content');
        const ve = document.getElementById('contentVisual');
        if (editorMode === 'code') {
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const selectedText = ta.value.substring(start, end);
            const beforeText = ta.value.substring(0, start);
            const afterText = ta.value.substring(end);
            const formattedText = tag === 'h2' ? `<${tag}>${selectedText}</${tag}>\n` : `<${tag}>${selectedText}</${tag}>`;
            ta.value = beforeText + formattedText + afterText;
            ta.setSelectionRange(start + tag.length + 2, start + tag.length + 2 + selectedText.length);
            saveToHistory();
        } else {
            if (ve) ve.focus();
            if (tag === 'h2') {
                toggleBlockFormat('h2');
            } else {
                toggleInlineFormat(tag);
            }
            saveSelection();
            updateActiveButtons();
            saveToHistory();
        }
    }

    function alignText(side) {
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const selectedText = ta.value.substring(start, end);
            const before = ta.value.substring(0, start);
            const after = ta.value.substring(end);
            const html = `<div style="text-align: ${side};">${selectedText || '&nbsp;'}</div>`;
            ta.value = before + html + after;
        } else {
            const ve = document.getElementById('contentVisual');
            if (ve) ve.focus();
            
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;
            const range = sel.getRangeAt(0);
            
            let node = range.startContainer;
            let blockNode = null;
            const blockTags = ['P', 'H1', 'H2', 'H3', 'DIV'];
            while (node && node.id !== 'contentVisual') {
                if (node.nodeType === 1 && blockTags.includes(node.tagName.toUpperCase())) {
                    blockNode = node;
                    break;
                }
                node = node.parentNode;
            }
            
            if (blockNode) {
                blockNode.style.textAlign = side;
            } else {
                const div = document.createElement('div');
                div.style.textAlign = side;
                if (!range.collapsed) {
                    try {
                        const contents = range.extractContents();
                        div.appendChild(contents);
                        range.insertNode(div);
                    } catch(e) {
                        console.error("Extract failed", e);
                    }
                } else {
                    div.innerHTML = '<br>';
                    range.insertNode(div);
                }
            }
            
            saveSelection();
            saveToHistory();
        }
    }

    // Кастомный обработчик Enter для стабильной структуры абзацев (разделение на блоки <p>)
    document.getElementById('contentVisual').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.defaultPrevented) {
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;
            const node = sel.anchorNode;
            
            // Если мы внутри списка или преформатированного текста, пусть браузер обрабатывает сам
            let inListOrPre = false;
            let curr = node;
            while(curr && curr.id !== 'contentVisual') {
                if(curr.tagName === 'LI' || curr.tagName === 'PRE') { 
                    inListOrPre = true; 
                    break; 
                }
                curr = curr.parentNode;
            }
            if (inListOrPre) return; 

            e.preventDefault();
            
            const range = sel.getRangeAt(0);
            
            // Находим ближайший блочный элемент (P, H1-H6, DIV, etc.)
            let blockNode = range.startContainer;
            const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'DIV', 'BLOCKQUOTE'];
            while (blockNode && blockNode.id !== 'contentVisual') {
                if (blockNode.nodeType === 1 && blockTags.includes(blockNode.tagName.toUpperCase())) {
                    break;
                }
                blockNode = blockNode.parentNode;
            }
            
            // Если мы не нашли блочный элемент, обернем текущее содержимое в <p>
            if (!blockNode || blockNode.id === 'contentVisual') {
                document.execCommand('formatBlock', false, 'p');
                
                // Переполучим blockNode
                blockNode = sel.anchorNode;
                while (blockNode && blockNode.id !== 'contentVisual') {
                    if (blockNode.nodeType === 1 && blockTags.includes(blockNode.tagName.toUpperCase())) {
                        break;
                    }
                    blockNode = blockNode.parentNode;
                }
            }
            
            if (blockNode && blockNode.id !== 'contentVisual') {
                range.deleteContents();
                
                // Разделяем блок
                const afterRange = document.createRange();
                afterRange.setStart(range.endContainer, range.endOffset);
                afterRange.setEndAfter(blockNode.lastChild || blockNode);
                
                let afterFragment;
                try {
                    afterFragment = afterRange.extractContents();
                } catch(err) {
                    afterFragment = document.createDocumentFragment();
                }
                
                // Создаем новый абзац <p>
                const newP = document.createElement('p');
                
                // Наполняем его
                if (afterFragment.childNodes.length === 0 || (afterFragment.childNodes.length === 1 && afterFragment.textContent === '')) {
                    newP.innerHTML = '<br>';
                } else {
                    newP.appendChild(afterFragment);
                }
                
                // Вставляем новый абзац после текущего блока
                blockNode.parentNode.insertBefore(newP, blockNode.nextSibling);
                
                // Ставим каретку в начало нового абзаца
                const newRange = document.createRange();
                newRange.setStart(newP, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
                
                // Очищаем старый блок, если он пуст
                if (blockNode.textContent.trim() === '' && !blockNode.querySelector('img, video, audio, iframe')) {
                    blockNode.innerHTML = '<br>';
                }
            } else {
                // В крайнем случае вставляем <p><br></p> в позицию каретки
                const newP = document.createElement('p');
                newP.innerHTML = '<br>';
                range.insertNode(newP);
                const newRange = document.createRange();
                newRange.setStart(newP, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
            
            saveSelection();
            saveToHistory();
        }
    });

    // Функции для работы с историей изменений
    function saveToHistory() {
        if (isRestoringHistory) return;
        
        const ve = document.getElementById('contentVisual');
        const ta = document.getElementById('content');
        
        const currentState = {
            visual: ve.innerHTML,
            code: ta.value,
            mode: editorMode
        };
        
        // Удаляем все состояния после текущего индекса
        historyStack = historyStack.slice(0, historyIndex + 1);
        
        // Добавляем новое состояние
        historyStack.push(currentState);
        historyIndex++;
        
        updateUndoRedoButtons();
        
        // Сохраняем в файл с задержкой
        clearTimeout(historySaveTimeout);
        historySaveTimeout = setTimeout(() => {
            saveHistoryToFile();
        }, 1000);
    }

    function saveHistoryToFile() {
        fetch('save_history.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                history: historyStack,
                index: historyIndex
            })
        }).catch(error => {
            console.error('Ошибка сохранения истории:', error);
        });
    }

    function loadHistoryFromFile() {
        fetch('get_history.php?t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    historyStack = data.history || [];
                    historyIndex = data.index ?? -1;
                    updateUndoRedoButtons();
                }
            })
            .catch(error => {
                console.error('Ошибка загрузки истории:', error);
            });
    }

    function undoEdit() {
        if (historyIndex <= 0) return;
        
        historyIndex--;
        restoreHistoryState(historyStack[historyIndex]);
        updateUndoRedoButtons();
        saveHistoryToFile();
    }

    function redoEdit() {
        if (historyIndex >= historyStack.length - 1) return;
        
        historyIndex++;
        restoreHistoryState(historyStack[historyIndex]);
        updateUndoRedoButtons();
        saveHistoryToFile();
    }

    function restoreHistoryState(state) {
        isRestoringHistory = true;
        
        const ve = document.getElementById('contentVisual');
        const ta = document.getElementById('content');
        
        ve.innerHTML = state.visual;
        ta.value = state.code;
        
        // Восстанавливаем обработчики для изображений и других элементов
        addColumnResizers();
        
        isRestoringHistory = false;
    }

    function updateUndoRedoButtons() {
        const undoBtn = document.getElementById('undoBtn');
        const redoBtn = document.getElementById('redoBtn');
        
        if (undoBtn) {
            undoBtn.disabled = historyIndex <= 0;
            undoBtn.style.opacity = historyIndex <= 0 ? '0.4' : '1';
            undoBtn.style.cursor = historyIndex <= 0 ? 'not-allowed' : 'pointer';
        }
        
        if (redoBtn) {
            redoBtn.disabled = historyIndex >= historyStack.length - 1;
            redoBtn.style.opacity = historyIndex >= historyStack.length - 1 ? '0.4' : '1';
            redoBtn.style.cursor = historyIndex >= historyStack.length - 1 ? 'not-allowed' : 'pointer';
        }
    }

    function clearHistory() {
        historyStack = [];
        historyIndex = -1;
        updateUndoRedoButtons();
        
        // Очищаем файл истории
        fetch('clear_history.php', {
            method: 'POST'
        }).catch(error => {
            console.error('Ошибка очистки истории:', error);
        });
    }

    function insertHtmlAtCaret(html) {
        const ve = document.getElementById('contentVisual');
        ve.focus();
        const sel = window.getSelection();
        let range = null;
        if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
            range = savedRange;
        } else if (sel && sel.rangeCount > 0) {
            range = sel.getRangeAt(0);
        }
        if (!range) {
            ve.insertAdjacentHTML('beforeend', html);
            return;
        }
        range.deleteContents();
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const frag = document.createDocumentFragment();
        let node, lastNode;
        while ((node = temp.firstChild)) {
            lastNode = frag.appendChild(node);
        }
        range.insertNode(frag);
        if (lastNode) {
            range.setStartAfter(lastNode);
            range.collapse(true);
            const s = window.getSelection();
            if (s) {
                s.removeAllRanges();
                s.addRange(range);
            }
            savedRange = range.cloneRange();
        }
    }

    /** Вставка блока с изображением(ями) и пустой строки после; курсор ставится в пустой блок, чтобы текст не привязывался к картинке */
    function insertImageBlockAtCaret(html) {
        const ve = document.getElementById('contentVisual');
        ve.focus();
        const sel = window.getSelection();
        let range = null;
        if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
            range = savedRange;
        } else if (sel && sel.rangeCount > 0) {
            range = sel.getRangeAt(0);
        }
        var emptyDiv = document.createElement('div');
        emptyDiv.innerHTML = '<br>';
        if (!range) {
            ve.insertAdjacentHTML('beforeend', html);
            ve.appendChild(emptyDiv);
            range = document.createRange();
            range.setStart(emptyDiv, 0);
            range.collapse(true);
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
            savedRange = range.cloneRange();
            return;
        }
        range.deleteContents();
        var temp = document.createElement('div');
        temp.innerHTML = html;
        var frag = document.createDocumentFragment();
        var node, lastNode;
        while ((node = temp.firstChild)) {
            lastNode = frag.appendChild(node);
        }
        range.insertNode(frag);
        if (lastNode) {
            var parent = lastNode.parentNode;
            parent.insertBefore(emptyDiv, lastNode.nextSibling);
            range.setStart(emptyDiv, 0);
            range.collapse(true);
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
            savedRange = range.cloneRange();
        }
    }

    function insertList() {
        const listTemplate = "\n<ul>\n  <li>Пункт 1</li>\n  <li>Пункт 2</li>\n  <li>Пункт 3</li>\n</ul>\n";
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + listTemplate + ta.value.substring(cursorPos);
            ta.focus();
        } else {
            insertHtmlAtCaret(listTemplate);
        }
        saveToHistory();
    }

    function openTableDialog() {
        saveSelection();
        document.getElementById('tableDialog').style.display = 'block';
        document.getElementById('tableRows').focus();
    }

    function closeTableDialog() {
        document.getElementById('tableDialog').style.display = 'none';
        document.getElementById('tableRows').value = '3';
        document.getElementById('tableCols').value = '3';
    }

    function addTableRow() {
        if (!window.contextMenuTableRow) return;
        
        const row = window.contextMenuTableRow;
        const table = row.closest('table');
        if (!table) return;
        
        const colCount = row.querySelectorAll('td, th').length;
        const newRow = document.createElement('tr');
        
        for (let i = 0; i < colCount; i++) {
            const cell = document.createElement('td');
            cell.innerHTML = '<br>';
            cell.contentEditable = 'true';
            newRow.appendChild(cell);
        }
        
        // Вставляем новую строку после текущей
        if (row.parentNode.tagName === 'THEAD') {
            // Если это строка заголовка, добавляем в tbody
            const tbody = table.querySelector('tbody');
            if (tbody && tbody.firstChild) {
                tbody.insertBefore(newRow, tbody.firstChild);
            } else if (tbody) {
                tbody.appendChild(newRow);
            }
        } else {
            row.parentNode.insertBefore(newRow, row.nextSibling);
        }
        
        saveToHistory();
        showNotification('Строка добавлена', 'success');
    }

    function deleteTableRow() {
        if (!window.contextMenuTableRow) return;
        
        const row = window.contextMenuTableRow;
        const table = row.closest('table');
        if (!table) return;
        
        // Проверяем, не является ли это единственной строкой в tbody
        const tbody = table.querySelector('tbody');
        if (tbody && tbody.querySelectorAll('tr').length === 1 && row.parentNode === tbody) {
            showNotification('Нельзя удалить последнюю строку таблицы', 'warning');
            return;
        }
        
        // Не даем удалить строку заголовка, если она единственная в thead
        if (row.parentNode.tagName === 'THEAD') {
            showNotification('Нельзя удалить строку заголовка', 'warning');
            return;
        }
        
        row.parentNode.removeChild(row);
        saveToHistory();
        showNotification('Строка удалена', 'success');
    }

    function addTableColumn() {
        if (!window.contextMenuTableCell) return;
        
        const cell = window.contextMenuTableCell;
        const table = cell.closest('table');
        if (!table) return;
        
        // Определяем индекс текущего столбца
        const row = cell.closest('tr');
        const cells = Array.from(row.querySelectorAll('td, th'));
        const colIndex = cells.indexOf(cell);
        
        // Добавляем ячейку в заголовок
        const thead = table.querySelector('thead');
        if (thead) {
            const headerRow = thead.querySelector('tr');
            if (headerRow) {
                const headerCells = headerRow.querySelectorAll('th');
                const newHeader = document.createElement('th');
                newHeader.innerHTML = '<br>';
                newHeader.contentEditable = 'true';
                
                if (colIndex + 1 < headerCells.length) {
                    headerRow.insertBefore(newHeader, headerCells[colIndex + 1]);
                } else {
                    headerRow.appendChild(newHeader);
                }
            }
        }
        
        // Добавляем ячейки во все строки tbody
        const tbody = table.querySelector('tbody');
        if (tbody) {
            const rows = tbody.querySelectorAll('tr');
            rows.forEach(function(bodyRow) {
                const bodyCells = bodyRow.querySelectorAll('td');
                const newCell = document.createElement('td');
                newCell.innerHTML = '<br>';
                newCell.contentEditable = 'true';
                
                if (colIndex + 1 < bodyCells.length) {
                    bodyRow.insertBefore(newCell, bodyCells[colIndex + 1]);
                } else {
                    bodyRow.appendChild(newCell);
                }
            });
        }
        
        // Обновляем ресайзеры
        addColumnResizers();
        saveToHistory();
        showNotification('Столбец добавлен', 'success');
    }

    function deleteTableColumn() {
        if (!window.contextMenuTableCell) return;
        
        const cell = window.contextMenuTableCell;
        const table = cell.closest('table');
        if (!table) return;
        
        // Определяем индекс текущего столбца
        const row = cell.closest('tr');
        const cells = Array.from(row.querySelectorAll('td, th'));
        const colIndex = cells.indexOf(cell);
        
        // Проверяем, не единственный ли это столбец
        if (cells.length === 1) {
            showNotification('Нельзя удалить единственный столбец', 'warning');
            return;
        }
        
        // Удаляем ячейку из заголовка
        const thead = table.querySelector('thead');
        if (thead) {
            const headerRow = thead.querySelector('tr');
            if (headerRow) {
                const headerCells = headerRow.querySelectorAll('th');
                if (headerCells[colIndex]) {
                    headerCells[colIndex].parentNode.removeChild(headerCells[colIndex]);
                }
            }
        }
        
        // Удаляем ячейки из всех строк tbody
        const tbody = table.querySelector('tbody');
        if (tbody) {
            const rows = tbody.querySelectorAll('tr');
            rows.forEach(function(bodyRow) {
                const bodyCells = bodyRow.querySelectorAll('td');
                if (bodyCells[colIndex]) {
                    bodyCells[colIndex].parentNode.removeChild(bodyCells[colIndex]);
                }
            });
        }
        
        // Обновляем ресайзеры
        addColumnResizers();
        saveToHistory();
        showNotification('Столбец удален', 'success');
    }

    function deleteTable() {
        if (!window.contextMenuTableCell && !window.contextMenuTableRow) return;
        
        const cell = window.contextMenuTableCell || window.contextMenuTableRow.querySelector('td, th');
        if (!cell) return;
        
        const table = cell.closest('table');
        if (!table) return;
        
        // Удаляем таблицу
        table.parentNode.removeChild(table);
        saveToHistory();
        showNotification('Таблица удалена', 'success');
    }

    function openCellColorDialog() {
        if (!window.contextMenuTableCell) return;
        document.getElementById('cellColorDialog').style.display = 'block';
    }

    function closeCellColorDialog() {
        document.getElementById('cellColorDialog').style.display = 'none';
    }

    function setCellColor(color) {
        if (!window.contextMenuTableCell) return;
        
        const cell = window.contextMenuTableCell;
        
        if (color) {
            cell.style.backgroundColor = color;
            cell.style.color = '#000000'; // Устанавливаем черный цвет текста
        } else {
            cell.style.backgroundColor = '';
            cell.style.color = ''; // Сбрасываем цвет текста
        }
        
        saveToHistory();
        closeCellColorDialog();
        showNotification('Цвет ячейки изменен', 'success');
    }

    function insertTable() {
        const rows = parseInt(document.getElementById('tableRows').value);
        const cols = parseInt(document.getElementById('tableCols').value);
        
        if (!rows || rows < 1 || rows > 20) {
            showNotification('Введите количество строк от 1 до 20', 'warning');
            return;
        }
        
        if (!cols || cols < 1 || cols > 7) {
            showNotification('Введите количество столбцов от 1 до 7', 'warning');
            return;
        }
        
        let tableHtml = '<table><thead><tr>';
        
        // Создаем заголовки
        for (let i = 0; i < cols; i++) {
            tableHtml += `<th>Заголовок ${i + 1}</th>`;
        }
        tableHtml += '</tr></thead><tbody>';
        
        // Создаем строки с пустыми ячейками
        for (let i = 0; i < rows; i++) {
            tableHtml += '<tr>';
            for (let j = 0; j < cols; j++) {
                tableHtml += '<td><br></td>';
            }
            tableHtml += '</tr>';
        }
        
        tableHtml += '</tbody></table>';
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + tableHtml + '\n' + ta.value.substring(cursorPos);
            ta.focus();
        } else {
            insertTableAtCaret(tableHtml);
        }
        
        saveToHistory();
        closeTableDialog();
        showNotification('Таблица добавлена', 'success');
    }

    // Функция для вставки таблицы в визуальном редакторе
    function insertTableAtCaret(tableHtml) {
        const ve = document.getElementById('contentVisual');
        ve.focus();
        const sel = window.getSelection();
        let range = null;
        
        if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
            range = savedRange;
        } else if (sel && sel.rangeCount > 0) {
            range = sel.getRangeAt(0);
        }
        
        // Создаем пустой блок для курсора после таблицы
        const emptyDiv = document.createElement('div');
        emptyDiv.innerHTML = '<br>';
        
        if (!range) {
            ve.insertAdjacentHTML('beforeend', tableHtml);
            ve.appendChild(emptyDiv);
            range = document.createRange();
            range.setStart(emptyDiv, 0);
            range.collapse(true);
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
            savedRange = range.cloneRange();
        } else {
            range.deleteContents();
            
            // Создаем временный контейнер для парсинга HTML
            const temp = document.createElement('div');
            temp.innerHTML = tableHtml;
            
            const frag = document.createDocumentFragment();
            let node, lastNode;
            while ((node = temp.firstChild)) {
                lastNode = frag.appendChild(node);
            }
            
            range.insertNode(frag);
            
            if (lastNode) {
                const parent = lastNode.parentNode;
                parent.insertBefore(emptyDiv, lastNode.nextSibling);
                range.setStart(emptyDiv, 0);
                range.collapse(true);
                if (sel) {
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
                savedRange = range.cloneRange();
            }
        }
        
        // Добавляем ручки изменения размера после небольшой задержки
        setTimeout(() => {
            addColumnResizers();
        }, 100);
    }

    // Функция для добавления ручек изменения размера столбцов
    function addColumnResizers() {
        const ve = document.getElementById('contentVisual');
        if (!ve) return;
        
        const tables = ve.querySelectorAll('table');
        tables.forEach(table => {
            // Проверяем, не добавлены ли уже ручки
            if (table.dataset.resizersAdded) return;
            table.dataset.resizersAdded = 'true';
            
            const headerCells = table.querySelectorAll('thead th');
            
            // Устанавливаем начальную ширину в процентах
            const colWidth = 100 / headerCells.length;
            headerCells.forEach(th => {
                th.style.width = colWidth + '%';
            });
            
            headerCells.forEach((th, index) => {
                // Не добавляем ручку к последнему столбцу
                if (index === headerCells.length - 1) return;
                
                const resizer = document.createElement('div');
                resizer.className = 'column-resizer';
                resizer.contentEditable = 'false';
                th.appendChild(resizer);
                
                let startX, startWidthPercent, nextStartWidthPercent, tableWidth;
                
                resizer.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    resizer.classList.add('resizing');
                    startX = e.pageX;
                    tableWidth = table.offsetWidth;
                    
                    // Получаем текущую ширину в процентах
                    startWidthPercent = (th.offsetWidth / tableWidth) * 100;
                    
                    const nextTh = headerCells[index + 1];
                    nextStartWidthPercent = nextTh ? (nextTh.offsetWidth / tableWidth) * 100 : 0;
                    
                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                });
                
                function onMouseMove(e) {
                    const diff = e.pageX - startX;
                    const diffPercent = (diff / tableWidth) * 100;
                    
                    const newWidthPercent = startWidthPercent + diffPercent;
                    const newNextWidthPercent = nextStartWidthPercent - diffPercent;
                    
                    // Минимальная ширина 5%
                    if (newWidthPercent > 5 && newNextWidthPercent > 5) {
                        th.style.width = newWidthPercent + '%';
                        const nextTh = headerCells[index + 1];
                        if (nextTh) {
                            nextTh.style.width = newNextWidthPercent + '%';
                        }
                        
                        // Применяем ширину ко всем ячейкам в столбце
                        const rows = table.querySelectorAll('tbody tr');
                        rows.forEach(row => {
                            const cells = row.querySelectorAll('td');
                            if (cells[index]) {
                                cells[index].style.width = newWidthPercent + '%';
                            }
                            if (cells[index + 1]) {
                                cells[index + 1].style.width = newNextWidthPercent + '%';
                            }
                        });
                    }
                }
                
                function onMouseUp() {
                    resizer.classList.remove('resizing');
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                }
            });
        });
    }

    // Вызываем функцию при загрузке контента в визуальный редактор
    function initTableResizers() {
        const ve = document.getElementById('contentVisual');
        if (!ve) return;
        
        // Добавляем ручки к существующим таблицам
        addColumnResizers();
        
        // Наблюдаем за изменениями в редакторе
        const observer = new MutationObserver(() => {
            addColumnResizers();
        });
        
        observer.observe(ve, {
            childList: true,
            subtree: true
        });
    }

    // Инициализируем при загрузке страницы
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTableResizers);
    } else {
        initTableResizers();
    }

    function addLink() {
        saveSelection();
        var urlInput = document.getElementById('linkUrl');
        var textInput = document.getElementById('linkText');
        urlInput.value = 'https://';
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            linkInsertStart = ta.selectionStart;
            linkInsertEnd = ta.selectionEnd;
            textInput.value = ta.value.substring(linkInsertStart, linkInsertEnd).trim();
        } else {
            textInput.value = document.getSelection().toString().trim();
        }
        document.getElementById('linkDialog').style.display = 'block';
        urlInput.focus();
        if (navigator.clipboard && navigator.clipboard.readText) {
            navigator.clipboard.readText().then(function(text) {
                if (text && (text = text.trim())) {
                    if (!/^https?:\/\//i.test(text)) text = 'https://' + text.replace(/^\/+/, '');
                    urlInput.value = text;
                }
            }).catch(function() {});
        }
    }

    function closeLinkDialog() {
        document.getElementById('linkDialog').style.display = 'none';
        document.getElementById('linkUrl').value = '';
        document.getElementById('linkText').value = '';
    }

    function insertLinkFromDialog() {
        var url = document.getElementById('linkUrl').value.trim();
        if (!url) {
            showNotification('Введите URL ссылки', 'warning');
            return;
        }
        var linkText = document.getElementById('linkText').value.trim();
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = linkInsertStart;
            var end = linkInsertEnd;
            var selectedText = ta.value.substring(start, end);
            var text = linkText || selectedText || 'ссылка';
            var link = '<a href="' + url + '">' + text + '</a>';
            ta.value = ta.value.substring(0, start) + link + ta.value.substring(end);
            ta.focus();
        } else {
            var text = linkText || (savedRange ? savedRange.toString() : '') || 'ссылка';
            var html = '<a href="' + url + '">' + text + '</a>';
            insertHtmlAtCaret(html);
        }
        saveToHistory();
        closeLinkDialog();
    }

    // Функции для работы с изображениями
    function showImageUpload() {
    saveSelection();
    document.getElementById('imageUploadDialog').style.display = 'block';
}

let gridTileFiles = {};

document.addEventListener('DOMContentLoaded', function() {
    const gridLayoutSelect = document.getElementById('gridLayout');
    if (gridLayoutSelect) {
        gridLayoutSelect.addEventListener('change', renderGridPreview);
    }
});

function renderGridPreview() {
    const gridLayout = document.getElementById('gridLayout').value;
    const previewContainer = document.getElementById('imageGridPreviewContainer');
    const fileUploadContainer = document.getElementById('fileUploadContainer');
    const imageSource = document.querySelector('input[name="imageSource"]:checked').value;
    
    // Clear old files
    gridTileFiles = {};
    
    if (!gridLayout || imageSource !== 'file') {
        previewContainer.style.display = 'none';
        previewContainer.innerHTML = '';
        if (imageSource === 'file') {
            fileUploadContainer.style.display = 'block';
        }
        return;
    }
    
    // Hide standard file upload input
    fileUploadContainer.style.display = 'none';
    previewContainer.style.display = 'grid';
    
    const [cols, rows] = gridLayout.split('x').map(Number);
    previewContainer.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
    
    let html = '';
    const totalTiles = cols * rows;
    for (let i = 0; i < totalTiles; i++) {
        html += `
            <div class="grid-preview-tile" onclick="triggerTileUpload(${i})">
                <div class="grid-preview-tile-badge">${i + 1}</div>
                <div class="grid-preview-tile-content" id="tile-content-${i}">
                    <span class="grid-preview-tile-icon">➕</span>
                    <span>Плитка ${i + 1}</span>
                </div>
                <img id="tile-img-${i}" class="grid-preview-tile-img">
                <div id="tile-delete-${i}" class="grid-preview-tile-delete" onclick="clearTileImage(event, ${i})">×</div>
                <input type="file" id="tile-file-input-${i}" accept="image/*" style="display: none;" onchange="handleTileFileChange(event, ${i})">
            </div>
        `;
    }
    previewContainer.innerHTML = html;
}

window.triggerTileUpload = function(index) {
    const input = document.getElementById(`tile-file-input-${index}`);
    if (input) input.click();
};

window.clearTileImage = function(e, index) {
    e.stopPropagation();
    delete gridTileFiles[index];
    
    const img = document.getElementById(`tile-img-${index}`);
    const content = document.getElementById(`tile-content-${index}`);
    const delBtn = document.getElementById(`tile-delete-${index}`);
    const input = document.getElementById(`tile-file-input-${index}`);
    
    if (img) img.style.display = 'none';
    if (content) content.style.display = 'flex';
    if (delBtn) delBtn.style.display = 'none';
    if (input) input.value = '';
};

window.handleTileFileChange = function(e, index) {
    const file = e.target.files[0];
    if (!file) return;
    
    gridTileFiles[index] = file;
    
    const reader = new FileReader();
    reader.onload = function(evt) {
        const img = document.getElementById(`tile-img-${index}`);
        const content = document.getElementById(`tile-content-${index}`);
        const delBtn = document.getElementById(`tile-delete-${index}`);
        
        if (img) {
            img.src = evt.target.result;
            img.style.display = 'block';
        }
        if (content) {
            content.style.display = 'none';
        }
        if (delBtn) {
            delBtn.style.display = 'flex';
        }
    };
    reader.readAsDataURL(file);
};

document.querySelectorAll('input[name="imageSource"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const isFile = this.value === 'file';
        const gridLayout = document.getElementById('gridLayout').value;
        
        if (isFile && gridLayout) {
            document.getElementById('fileUploadContainer').style.display = 'none';
            document.getElementById('imageGridPreviewContainer').style.display = 'grid';
        } else {
            document.getElementById('fileUploadContainer').style.display = isFile ? 'block' : 'none';
            document.getElementById('imageGridPreviewContainer').style.display = 'none';
        }
        
        document.getElementById('urlContainer').style.display = 
            this.value === 'url' ? 'block' : 'none';
    });
});

function processImage() {
    const imageSource = document.querySelector('input[name="imageSource"]:checked').value;
    const gridLayout = document.getElementById('gridLayout').value;
    const sizeSelect = document.getElementById('imageSize');
    const sizeValue = sizeSelect.value;

    let width, widthUnit = 'px';
    if (sizeValue === 'custom') {
        width = document.getElementById('customWidth').value;
        widthUnit = document.getElementById('widthUnit').value;
    } else {
        const sizes = {
            small: { width: 300 },
            medium: { width: 500 },
            large: { width: 800 }
        };
        width = sizes[sizeValue].width;
    }

    const caption = document.getElementById('imageCaption').value.trim();

    if (imageSource === 'url') {
        const urlInput = document.getElementById('imageUrl').value.trim();
        if (!urlInput) {
            showNotification('Введите URL изображения (можно несколько — каждое с новой строки или через запятую)', 'warning');
            return;
        }
        const urls = urlInput.split(/[\n,]+/).map(function(s) { return s.trim(); }).filter(Boolean);
        if (urls.length === 1) {
            insertImage(urls[0], width, '', widthUnit, '', caption);
        } else {
            insertImagesInGrid(urls, gridLayout, caption);
            closeImageDialog();
        }
        return;
    }

    let hasFiles = false;
    const formData = new FormData();

    if (gridLayout) {
        // Использование интерактивных плиток визуальной сетки!
        const [cols, rows] = gridLayout.split('x').map(Number);
        const totalTiles = cols * rows;
        for (let i = 0; i < totalTiles; i++) {
            if (gridTileFiles[i]) {
                formData.append('image[]', gridTileFiles[i]);
                hasFiles = true;
            }
        }
    } else {
        // Стандартная одиночная или множественная загрузка файлов
        const files = document.getElementById('imageFile').files;
        if (files.length) {
            Array.from(files).forEach(file => {
                formData.append('image[]', file);
            });
            hasFiles = true;
        }
    }

    if (!hasFiles) {
        showNotification('Выберите хотя бы одно изображение для загрузки', 'warning');
        return;
    }

    formData.append('width', width);
    formData.append('widthUnit', widthUnit);
    formData.append('gridLayout', gridLayout);

    fetch('upload_images_grid.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.urls) {
            if (data.urls.length === 1 && !data.gridLayout) {
                insertImage(data.urls[0], width, '', widthUnit, '', caption);
            } else {
                insertImagesInGrid(data.urls, data.gridLayout, caption);
            }
        } else {
            showNotification('Ошибка при загрузке изображений: ' + data.error, 'error');
        }
    })
    .catch(() => {
        showNotification('Ошибка сети при загрузке изображений', 'error');
    });

    closeImageDialog();
}

function insertImagesInGrid(urls, layout, caption = '') {
    let html = '';
    if (layout) {
        const [cols] = layout.split('x').map(Number);
        const className = `grid-container grid-${layout}`;
        
        html += `<div class="${className}" style="display: grid; grid-template-columns: repeat(${cols}, 1fr); gap: 10px;">`;
        urls.forEach(url => {
            html += wrapImageWithHint(`<img src="${url}" style="width: 100%; height: auto;" class="blog-image">`);
        });
        html += `</div>`;
        if (caption) {
            html += `<div style="text-align: center; margin-top: 8px;"><span class="caption" style="display: block; font-style: italic; font-size: 13px; opacity: 0.7;">${caption}</span></div>`;
        }
    } else {
        urls.forEach(url => {
            html += wrapImageWithHint(`<img src="${url}" class="blog-image">`, caption);
        });
    }

    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + html + '\n' + ta.value.substring(cursorPos);
    } else {
        insertImageBlockAtCaret(html);
    }

    closeImageDialog();
}

function uploadImage(file, width, height, widthUnit, heightUnit, caption) {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('width', width);
    formData.append('height', height || '');
    formData.append('widthUnit', widthUnit);
    formData.append('heightUnit', heightUnit || '');
    formData.append('caption', caption || '');

    fetch('upload_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            insertImage(data.url, width, height, widthUnit, heightUnit, caption);
        } else {
            showNotification('Ошибка при загрузке изображения: ' + data.error, 'error');
        }
    })
    .catch(error => {
        showNotification('Ошибка при загрузке изображения', 'error');
    });
}

function wrapImageWithHint(imgHtml, caption = '') {
    const uniqueId = 'img-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    const captionHtml = caption ? `<span class="caption">${caption}</span>` : '';
    return '<div class="blog-image-align-wrap" style="text-align:left" data-image-id="' + uniqueId + '">' +
        '<div class="blog-image-wrap">' + imgHtml + captionHtml + '</div></div>';
}

function wrapMediaWithControls(mediaHtml, type = 'video') {
    const uniqueId = type + '-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    return '<div class="blog-image-align-wrap" style="text-align:left" data-image-id="' + uniqueId + '">' +
        '<div class="blog-image-wrap" data-media-type="' + type + '">' + mediaHtml + '</div></div>';
}

function wrapExistingEditorImages() {
    var ve = document.getElementById('contentVisual');
    if (!ve || ve.style.display === 'none') return;
    
    // Сначала удаляем все старые элементы управления, если они вдруг есть
    var legacyElements = ve.querySelectorAll('.image-toolbar, .image-align-dropdown, .image-size-indicator, .image-resize-handle, .blog-image-overlay');
    legacyElements.forEach(function(el) {
        el.parentNode.removeChild(el);
    });
    
    var imgs = ve.querySelectorAll('img.blog-image, img[src], video, audio, iframe');
    for (var i = 0; i < imgs.length; i++) {
        var img = imgs[i];
        
        // Пропускаем, если элемент является частью каких-то других управляющих структур
        if (img.closest('.image-toolbar') || img.closest('.image-align-dropdown') || img.closest('.editor-context-menu')) {
            continue;
        }
        
        var isImg = img.tagName.toLowerCase() === 'img';
        var type = img.tagName.toLowerCase();
        
        var wrap = img.closest && img.closest('.blog-image-wrap');
        var alignWrap = img.closest && img.closest('.blog-image-align-wrap');
        
        // 1. Если нет wrap, создаем его
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'blog-image-wrap';
            if (type !== 'img') {
                wrap.setAttribute('data-media-type', type);
            }
            img.parentNode.insertBefore(wrap, img);
            wrap.appendChild(img);
        } else {
            // Если wrap есть, но нет типа медиа
            if (type !== 'img' && !wrap.hasAttribute('data-media-type')) {
                wrap.setAttribute('data-media-type', type);
            }
        }
        
        // 2. Если нет alignWrap, создаем его
        if (!alignWrap) {
            alignWrap = document.createElement('div');
            alignWrap.className = 'blog-image-align-wrap';
            alignWrap.style.textAlign = 'left';
            alignWrap.setAttribute('data-image-id', type + '-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9));
            wrap.parentNode.insertBefore(alignWrap, wrap);
            alignWrap.appendChild(wrap);
        }
    }
}

var activeTarget = null; // Текущий активный медиа-блок .blog-image-wrap
var isResizingMedia = false;
var startX, startY, startWidth, startHeight;
var currentHandle = null;

function showGlobalMediaOverlay(mediaWrap) {
    if (editorMode !== 'visual') return;
    
    activeTarget = mediaWrap;
    
    var overlay = document.getElementById('editorGlobalMediaOverlay');
    if (!overlay) {
        initGlobalMediaOverlayDOM();
        overlay = document.getElementById('editorGlobalMediaOverlay');
    }
    
    overlay.style.display = 'block';
    updateOverlayPosition();
    
    var innerMedia = mediaWrap.querySelector('img, video, audio, iframe, .blog-file-button');
    var isImg = innerMedia && innerMedia.tagName.toLowerCase() === 'img';
    var isFile = innerMedia && innerMedia.classList.contains('blog-file-button');
    
    var editBtn = overlay.querySelector('.image-toolbar-btn[data-action="edit"]');
    var resizeBtn = overlay.querySelector('.image-toolbar-btn[data-action="resize"]');
    var sizeIndicator = overlay.querySelector('.image-size-indicator');
    var resizeHandles = overlay.querySelectorAll('.image-resize-handle');
    
    if (editBtn) editBtn.style.display = isImg ? 'flex' : 'none';
    if (resizeBtn) resizeBtn.style.display = isFile ? 'none' : 'flex';
    if (sizeIndicator) sizeIndicator.style.display = isFile ? 'none' : 'block';
    resizeHandles.forEach(h => h.style.display = isFile ? 'none' : 'block');
    
    var alignWrap = mediaWrap.closest('.blog-image-align-wrap');
    var align = alignWrap ? (alignWrap.style.textAlign || 'left') : 'left';
    overlay.querySelectorAll('.image-align-option').forEach(function(opt) {
        if (opt.getAttribute('data-align') === align) {
            opt.classList.add('active');
        } else {
            opt.classList.remove('active');
        }
    });
    
    var dropdown = overlay.querySelector('.image-align-dropdown');
    if (dropdown) dropdown.style.display = 'none';
    var alignBtn = overlay.querySelector('.image-toolbar-btn[data-action="align"]');
    if (alignBtn) alignBtn.classList.remove('active');
}

function hideGlobalMediaOverlay() {
    var overlay = document.getElementById('editorGlobalMediaOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
    activeTarget = null;
}

function updateOverlayPosition() {
    var overlay = document.getElementById('editorGlobalMediaOverlay');
    if (!overlay || overlay.style.display === 'none' || !activeTarget) return;
    
    var rect = activeTarget.getBoundingClientRect();
    
    if (rect.width === 0 || rect.height === 0 || !document.body.contains(activeTarget)) {
        hideGlobalMediaOverlay();
        return;
    }
    
    overlay.style.left = (rect.left + window.scrollX) + 'px';
    overlay.style.top = (rect.top + window.scrollY) + 'px';
    overlay.style.width = rect.width + 'px';
    overlay.style.height = rect.height + 'px';
    
    var innerMedia = activeTarget.querySelector('img, video, audio, iframe, .blog-file-button');
    var sizeIndicator = overlay.querySelector('.image-size-indicator');
    if (innerMedia && sizeIndicator) {
        var w = innerMedia.offsetWidth;
        var h = innerMedia.offsetHeight;
        if (innerMedia.classList.contains('blog-file-button')) {
            sizeIndicator.style.display = 'none';
        } else if (w && h) {
            sizeIndicator.textContent = w + ' × ' + h + ' px';
            sizeIndicator.style.display = 'block';
        } else if (w) {
            sizeIndicator.textContent = w + ' px';
            sizeIndicator.style.display = 'block';
        } else {
            sizeIndicator.style.display = 'none';
        }
    }
}

function showImageResizeDialog(img) {
    var currentWidth = img.offsetWidth || (img.naturalWidth || img.videoWidth || 0);
    var isAudio = img.tagName.toLowerCase() === 'audio';
    var isVideo = img.tagName.toLowerCase() === 'video';
    var label = isAudio ? 'плеера аудио' : (isVideo ? 'плеера видео' : 'изображения');
    
    var newWidth = prompt('Введите новую ширину ' + label + ' (в пикселях):', currentWidth);
    if (newWidth && !isNaN(newWidth) && newWidth > 0) {
        img.style.width = newWidth + 'px';
        if (isAudio) {
            img.style.height = '';
        } else {
            img.style.height = 'auto';
        }
        updateOverlayPosition();
    }
}

function initGlobalMediaOverlayDOM() {
    if (document.getElementById('editorGlobalMediaOverlay')) return;
    
    var overlay = document.createElement('div');
    overlay.id = 'editorGlobalMediaOverlay';
    overlay.className = 'editor-global-media-overlay';
    overlay.style.cssText = 'display: none; position: absolute; pointer-events: none; z-index: 990;';
    
    overlay.innerHTML = 
        '<div class="media-overlay-outline" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; box-shadow: 0 0 0 2px var(--primary-color, #4CAF50); pointer-events: none; border-radius: 8px;"></div>' +
        '<div class="image-toolbar" style="position: absolute; top: 8px; right: 8px; display: flex; gap: 4px; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(8px); padding: 6px; border-radius: 10px; z-index: 10; pointer-events: auto;">' +
        '    <button type="button" class="image-toolbar-btn" data-action="align" title="Выравнивание">⚏</button>' +
        '    <button type="button" class="image-toolbar-btn" data-action="resize" title="Изменить размер">⇲</button>' +
        '    <button type="button" class="image-toolbar-btn" data-action="edit" title="Редактировать">✏️</button>' +
        '    <button type="button" class="image-toolbar-btn" data-action="delete" title="Удалить">🗑</button>' +
        '</div>' +
        '<div class="image-align-dropdown" style="position: absolute; top: 48px; right: 8px; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px); border-radius: 10px; padding: 6px; display: none; flex-direction: column; gap: 4px; min-width: 180px; box-shadow: 0 8px 24px rgba(0,0,0,0.3); z-index: 20; pointer-events: auto;">' +
        '    <button type="button" class="image-align-option" data-align="left"><span>◄</span> По левому краю</button>' +
        '    <button type="button" class="image-align-option" data-align="center"><span>≡</span> По центру</button>' +
        '    <button type="button" class="image-align-option" data-align="right"><span>►</span> По правому краю</button>' +
        '</div>' +
        '<div class="image-size-indicator" style="position: absolute; bottom: 8px; left: 8px; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(8px); color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-family: monospace; pointer-events: none;"></div>' +
        '<div class="image-resize-handle bottom-right" style="position: absolute; width: 12px; height: 12px; background: var(--primary-color, #4CAF50); border: 2px solid #fff; border-radius: 50%; cursor: nwse-resize; z-index: 11; bottom: -6px; right: -6px; pointer-events: auto;"></div>' +
        '<div class="image-resize-handle bottom-left" style="position: absolute; width: 12px; height: 12px; background: var(--primary-color, #4CAF50); border: 2px solid #fff; border-radius: 50%; cursor: nesw-resize; z-index: 11; bottom: -6px; left: -6px; pointer-events: auto;"></div>';
        
    document.body.appendChild(overlay);
    
    overlay.addEventListener('mousedown', function(e) {
        var handle = e.target.closest('.image-resize-handle');
        if (!handle) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        isResizingMedia = true;
        currentHandle = handle;
        
        var innerMedia = activeTarget ? activeTarget.querySelector('img, video, audio, iframe') : null;
        if (!innerMedia) return;
        
        startX = e.clientX;
        startY = e.clientY;
        startWidth = innerMedia.offsetWidth;
        startHeight = innerMedia.offsetHeight;
        
        overlay.classList.add('selected');
        document.body.style.cursor = handle.classList.contains('bottom-right') ? 'nwse-resize' : 'nesw-resize';
    });
    
    overlay.addEventListener('click', function(e) {
        var toolbarBtn = e.target.closest('.image-toolbar-btn');
        if (toolbarBtn) {
            e.preventDefault();
            e.stopPropagation();
            
            var action = toolbarBtn.getAttribute('data-action');
            var dropdown = overlay.querySelector('.image-align-dropdown');
            
            if (action === 'align') {
                if (dropdown) {
                    var isOpen = dropdown.style.display === 'flex';
                    dropdown.style.display = isOpen ? 'none' : 'flex';
                    if (!isOpen) {
                        toolbarBtn.classList.add('active');
                    } else {
                        toolbarBtn.classList.remove('active');
                    }
                }
            } else if (action === 'resize') {
                var innerMedia = activeTarget ? activeTarget.querySelector('img, video, audio, iframe') : null;
                if (innerMedia) {
                    showImageResizeDialog(innerMedia);
                }
            } else if (action === 'edit') {
                var img = activeTarget ? activeTarget.querySelector('img') : null;
                if (img) {
                    openImageEditorModal(img);
                }
            } else if (action === 'delete') {
                var innerMedia = activeTarget ? activeTarget.querySelector('img, video, audio, iframe, .blog-file-button') : null;
                var isImg = innerMedia && innerMedia.tagName.toLowerCase() === 'img';
                var isVideo = innerMedia && innerMedia.tagName.toLowerCase() === 'video';
                var isIframe = innerMedia && innerMedia.tagName.toLowerCase() === 'iframe';
                var isFile = innerMedia && innerMedia.classList.contains('blog-file-button');
                var label = isImg ? 'изображение' : (isVideo || isIframe ? 'видео' : (isFile ? 'файл' : 'аудио'));
                
                var targetToDelete = activeTarget;
                
                showConfirm('Удалить это ' + label + '?').then(result => {
                    if (!result) return;
                    if (targetToDelete) {
                        var alignWrap = targetToDelete.closest('.blog-image-align-wrap');
                        if (alignWrap) {
                            alignWrap.parentNode.removeChild(alignWrap);
                        } else {
                            targetToDelete.parentNode.removeChild(targetToDelete);
                        }
                        hideGlobalMediaOverlay();
                    }
                });
            }
            return;
        }
        
        var alignOption = e.target.closest('.image-align-option');
        if (alignOption) {
            e.preventDefault();
            e.stopPropagation();
            
            var align = alignOption.getAttribute('data-align');
            var alignWrap = activeTarget ? activeTarget.closest('.blog-image-align-wrap') : null;
            if (alignWrap) {
                alignWrap.style.textAlign = align;
                
                overlay.querySelectorAll('.image-align-option').forEach(function(opt) {
                    opt.classList.remove('active');
                });
                alignOption.classList.add('active');
                
                var dropdown = overlay.querySelector('.image-align-dropdown');
                if (dropdown) dropdown.style.display = 'none';
                var alignBtn = overlay.querySelector('.image-toolbar-btn[data-action="align"]');
                if (alignBtn) alignBtn.classList.remove('active');
                
                updateOverlayPosition();
            }
        }
    });
}

function initImageAlignmentHandlers() {
    var ve = document.getElementById('contentVisual');
    if (!ve) return;
    
    initGlobalMediaOverlayDOM();
    
    ve.addEventListener('mouseover', function(e) {
        if (editorMode !== 'visual' || isResizingMedia) return;
        var mediaWrap = e.target.closest('.blog-image-wrap');
        if (!mediaWrap) {
            var fileBtn = e.target.closest('.blog-file-button');
            if (fileBtn) {
                // Если это старая структура файла без .blog-image-wrap,
                // используем родительский div или саму кнопку как цель
                mediaWrap = fileBtn.closest('div[style*="display: block"]') || fileBtn;
            }
        }
        if (mediaWrap) {
            showGlobalMediaOverlay(mediaWrap);
        }
    });
    
    ve.addEventListener('click', function(e) {
        if (editorMode !== 'visual') return;
        
        // Предотвращаем переход по ссылкам и скачивание файлов при редактировании
        const clickedLink = e.target.closest('a');
        if (clickedLink) {
            e.preventDefault();
        }
        
        var mediaWrap = e.target.closest('.blog-image-wrap');
        if (!mediaWrap) {
            var fileBtn = e.target.closest('.blog-file-button');
            if (fileBtn) {
                mediaWrap = fileBtn.closest('div[style*="display: block"]') || fileBtn;
            }
        }
        if (mediaWrap) {
            showGlobalMediaOverlay(mediaWrap);
        }
    });
}

document.addEventListener('mousemove', function(e) {
    if (isResizingMedia && activeTarget) {
        var innerMedia = activeTarget.querySelector('img, video, audio, iframe');
        if (!innerMedia) return;
        
        e.preventDefault();
        
        var deltaX = e.clientX - startX;
        var deltaY = e.clientY - startY;
        
        if (currentHandle.classList.contains('bottom-left')) {
            deltaX = -deltaX;
        }
        
        var isAudio = innerMedia.tagName.toLowerCase() === 'audio';
        var isIframe = innerMedia.tagName.toLowerCase() === 'iframe';
        var isVideo = innerMedia.tagName.toLowerCase() === 'video';
        var newWidth = startWidth + deltaX;
        
        if (newWidth > 50 && newWidth < 2000) {
            innerMedia.style.width = newWidth + 'px';
            if (isAudio) {
                innerMedia.style.height = '';
            } else if (isIframe || isVideo) {
                var aspectRatio = startHeight / startWidth;
                var newHeight = newWidth * aspectRatio;
                innerMedia.style.height = newHeight + 'px';
            } else {
                innerMedia.style.height = 'auto';
            }
            updateOverlayPosition();
        }
    } else {
        if (editorMode !== 'visual') return;
        
        var overlay = document.getElementById('editorGlobalMediaOverlay');
        if (!overlay || overlay.style.display === 'none') return;
        
        var target = e.target;
        var insideActive = activeTarget && activeTarget.contains(target);
        var insideOverlay = overlay.contains(target);
        
        if (!insideActive && !insideOverlay) {
            var newMediaWrap = target.closest('.blog-image-wrap');
            if (!newMediaWrap) {
                var fileBtn = target.closest('.blog-file-button');
                if (fileBtn) {
                    newMediaWrap = fileBtn.closest('div[style*="display: block"]') || fileBtn;
                }
            }
            if (newMediaWrap) {
                showGlobalMediaOverlay(newMediaWrap);
            } else {
                var dropdown = overlay.querySelector('.image-align-dropdown');
                if (dropdown && dropdown.style.display === 'flex') {
                    return;
                }
                hideGlobalMediaOverlay();
            }
        }
    }
});

document.addEventListener('mouseup', function(e) {
    if (isResizingMedia) {
        isResizingMedia = false;
        document.body.style.cursor = '';
        var overlay = document.getElementById('editorGlobalMediaOverlay');
        if (overlay) overlay.classList.remove('selected');
        currentHandle = null;
    }
});

document.addEventListener('click', function(e) {
    if (editorMode !== 'visual') return;
    
    var overlay = document.getElementById('editorGlobalMediaOverlay');
    if (!overlay || overlay.style.display === 'none') return;
    
    var target = e.target;
    var insideActive = activeTarget && activeTarget.contains(target);
    var insideOverlay = overlay.contains(target);
    
    if (!insideActive && !insideOverlay) {
        hideGlobalMediaOverlay();
    }
});

window.addEventListener('scroll', updateOverlayPosition, { capture: true, passive: true });
window.addEventListener('resize', updateOverlayPosition);

initImageAlignmentHandlers();

(function preventEnterInsideImageBlock() {
    var ve = document.getElementById('contentVisual');
    if (!ve) return;
    ve.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' || editorMode !== 'visual') return;
        var sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        var node = sel.anchorNode;
        if (!node || !ve.contains(node)) return;
        var alignWrap = node.nodeType === Node.ELEMENT_NODE ? node.closest('.blog-image-align-wrap') : (node.parentElement && node.parentElement.closest('.blog-image-align-wrap'));
        if (!alignWrap) return;
        e.preventDefault();
        var emptyDiv = document.createElement('div');
        emptyDiv.innerHTML = '<br>';
        var next = alignWrap.nextSibling;
        var parent = alignWrap.parentNode;
        if (next) parent.insertBefore(emptyDiv, next);
        else parent.appendChild(emptyDiv);
        var range = document.createRange();
        range.setStart(emptyDiv, 0);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
        if (typeof savedRange !== 'undefined') savedRange = range.cloneRange();
    });
})();

(function initEditorContextMenu() {
    var menu = document.getElementById('editorContextMenu');
    var contentVisual = document.getElementById('contentVisual');
    var contentTa = document.getElementById('content');
    var contextMenuImageTarget = null;
    if (!menu || !contentVisual) return;

    function hideMenu() {
        menu.classList.remove('is-open');
        contextMenuImageTarget = null;
    }
    function showMenu(x, y) {
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
        menu.classList.add('is-open');
        requestAnimationFrame(function() {
            var rect = menu.getBoundingClientRect();
            var w = window.innerWidth;
            var h = window.innerHeight;
            var left = parseFloat(menu.style.left);
            var top = parseFloat(menu.style.top);
            if (left + rect.width > w - 8) left = w - rect.width - 8;
            if (top + rect.height > h - 8) top = h - rect.height - 8;
            if (left < 8) left = 8;
            if (top < 8) top = 8;
            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
        });
    }

    function onContextMenu(e) {
        var inEditor = e.target === contentVisual || contentVisual.contains(e.target) ||
                       e.target === contentTa || contentTa.contains(e.target);
        if (!inEditor) return;
        e.preventDefault();
        e.stopPropagation();
        contextMenuImageTarget = null;
        
        // Скрываем кнопки таблицы по умолчанию
        var tableItems = menu.querySelectorAll('.table-context-item, .table-context-sep');
        tableItems.forEach(function(item) {
            item.style.display = 'none';
        });
        
        // Проверяем, находимся ли в таблице
        var tableRow = null;
        var tableCell = null;
        if (editorMode === 'visual' && contentVisual.contains(e.target)) {
            tableCell = e.target.closest('td, th');
            tableRow = e.target.closest('tr');
            if (tableRow && tableCell) {
                // Показываем кнопки таблицы
                tableItems.forEach(function(item) {
                    item.style.display = '';
                });
                // Сохраняем ссылку на строку и ячейку
                window.contextMenuTableRow = tableRow;
                window.contextMenuTableCell = tableCell;
            }
            
            var alignWrap = e.target.closest && e.target.closest('.blog-image-align-wrap');
            var imgWrap = e.target.closest && e.target.closest('.blog-image-wrap');
            var img = e.target.tagName === 'IMG' ? e.target : null;
            if (alignWrap) contextMenuImageTarget = alignWrap;
            else if (imgWrap) contextMenuImageTarget = imgWrap;
            else if (img && img.parentNode) contextMenuImageTarget = img.parentNode;
        }
        saveSelection();
        if (editorMode === 'code' && contentTa) {
            colorInsertStart = contentTa.selectionStart;
            colorInsertEnd = contentTa.selectionEnd;
        }
        showMenu(e.clientX, e.clientY);
    }

    contentVisual.addEventListener('contextmenu', onContextMenu);
    if (contentTa) contentTa.addEventListener('contextmenu', onContextMenu);

    // Обработчики для истории изменений
    let inputTimeout = null;
    contentVisual.addEventListener('input', function() {
        if (isRestoringHistory) return;
        clearTimeout(inputTimeout);
        inputTimeout = setTimeout(() => {
            saveToHistory();
        }, 500); // Сохраняем через 500мс после последнего ввода
    });
    
    if (contentTa) {
        contentTa.addEventListener('input', function() {
            if (isRestoringHistory) return;
            clearTimeout(inputTimeout);
            inputTimeout = setTimeout(() => {
                saveToHistory();
            }, 500);
        });
    }

    // Обработчик для обеспечения возможности редактирования после spoiler блоков
    contentVisual.addEventListener('click', function(e) {
        // Проверяем, кликнули ли мы на spoiler блок или рядом с ним
        const ve = document.getElementById('contentVisual');
        const spoilers = ve.querySelectorAll('.spoiler-block');
        
        spoilers.forEach(function(spoiler) {
            // Проверяем, есть ли после spoiler следующий элемент
            if (!spoiler.nextSibling || (spoiler.nextSibling.nodeType === Node.TEXT_NODE && spoiler.nextSibling.textContent.trim() === '')) {
                // Если нет следующего элемента или это пустой текстовый узел, создаем div
                const emptyDiv = document.createElement('div');
                emptyDiv.innerHTML = '<br>';
                if (spoiler.nextSibling) {
                    spoiler.parentNode.insertBefore(emptyDiv, spoiler.nextSibling);
                } else {
                    spoiler.parentNode.appendChild(emptyDiv);
                }
            }
        });
    });

    // Обработчик для клавиш - создаем пустой блок при нажатии Enter в конце spoiler
    contentVisual.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                let node = range.startContainer;
                
                // Ищем родительский spoiler-block
                while (node && node !== contentVisual) {
                    if (node.classList && node.classList.contains('spoiler-block')) {
                        // Проверяем, находимся ли мы в конце spoiler
                        const spoilerContent = node.querySelector('.spoiler-content');
                        if (spoilerContent && spoilerContent.contains(range.startContainer)) {
                            // Проверяем, есть ли после spoiler элемент
                            if (!node.nextSibling || (node.nextSibling.nodeType === Node.TEXT_NODE && node.nextSibling.textContent.trim() === '')) {
                                e.preventDefault();
                                const emptyDiv = document.createElement('div');
                                emptyDiv.innerHTML = '<br>';
                                node.parentNode.insertBefore(emptyDiv, node.nextSibling);
                                
                                // Устанавливаем курсор в новый блок
                                const newRange = document.createRange();
                                newRange.setStart(emptyDiv, 0);
                                newRange.collapse(true);
                                sel.removeAllRanges();
                                sel.addRange(newRange);
                                return;
                            }
                        }
                        break;
                    }
                    node = node.parentNode;
                }
            }
        }
    });

    menu.addEventListener('click', function(e) {
        var item = e.target.closest('.editor-context-item');
        if (!item || !item.dataset.cmd) return;
        e.preventDefault();
        e.stopPropagation();
        var cmd = item.dataset.cmd;
        if (cmd === 'paste' || cmd === 'copy' || cmd === 'cut' || cmd === 'delete') {
            if (cmd === 'delete' && contextMenuImageTarget && contextMenuImageTarget.parentNode) {
                contextMenuImageTarget.parentNode.removeChild(contextMenuImageTarget);
                contextMenuImageTarget = null;
            } else if (editorMode === 'visual') {
                contentVisual.focus();
                document.execCommand(cmd, false, null);
            } else {
                if (cmd === 'copy') document.execCommand('copy');
                if (cmd === 'cut') document.execCommand('cut');
                if (cmd === 'paste') document.execCommand('paste');
                if (cmd === 'delete' && contentTa) {
                    var start = colorInsertStart;
                    var end = colorInsertEnd;
                    contentTa.value = contentTa.value.substring(0, start) + contentTa.value.substring(end);
                    contentTa.focus();
                }
            }
        } else if (cmd === 'link') {
            addLink();
        } else if (cmd === 'image') {
            showImageUpload();
        } else if (cmd === 'list') {
            insertList();
        } else if (cmd === 'addRow') {
            addTableRow();
        } else if (cmd === 'deleteRow') {
            deleteTableRow();
        } else if (cmd === 'addColumn') {
            addTableColumn();
        } else if (cmd === 'deleteColumn') {
            deleteTableColumn();
        } else if (cmd === 'colorCell') {
            openCellColorDialog();
        } else if (cmd === 'deleteTable') {
            deleteTable();
        }
        hideMenu();
    });

    document.addEventListener('click', hideMenu);
    document.addEventListener('contextmenu', function(e) {
        if (!menu.contains(e.target)) hideMenu();
    });
})();

function insertImage(url, width, height, widthUnit, heightUnit, caption = '') {
    const imgStyle = `width: ${width}${widthUnit}; ` + 
                    (height ? `height: ${height}${heightUnit};` : '');
    const imgTag = wrapImageWithHint(`<img src="${url}" style="${imgStyle}" class="blog-image">`, caption);
    
    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + imgTag + '\n' + ta.value.substring(cursorPos);
    } else {
        insertImageBlockAtCaret(imgTag);
    }
    saveToHistory();
    closeImageDialog();
}

function closeImageDialog() {
    document.getElementById('imageUploadDialog').style.display = 'none';
    document.getElementById('imageFile').value = '';
    document.getElementById('imageUrl').value = '';
    document.getElementById('imageCaption').value = '';
    document.getElementById('customWidth').value = '';
    document.getElementById('customHeight').value = '';
    document.getElementById('gridLayout').value = '';
    document.querySelector('input[name="imageSource"][value="file"]').checked = true;
    document.getElementById('fileUploadContainer').style.display = 'block';
    document.getElementById('imageGridPreviewContainer').style.display = 'none';
    document.getElementById('imageGridPreviewContainer').innerHTML = '';
    document.getElementById('urlContainer').style.display = 'none';
    gridTileFiles = {};
}

    // Функции для работы с размером шрифта
    function setFontSize(size) {
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = colorInsertStart;
            var end = colorInsertEnd;
            var selectedText = ta.value.substring(start, end);
            if (selectedText) {
                var fontSpan = '<span style="font-size: ' + size + 'px;">' + selectedText + '</span>';
                ta.value = ta.value.substring(0, start) + fontSpan + ta.value.substring(end);
                ta.focus();
                saveToHistory();
            }
        } else {
            var text = (savedRange && savedRange.toString()) || document.getSelection().toString();
            if (text) {
                var html = '<span style="font-size: ' + size + 'px;">' + text + '</span>';
                insertHtmlAtCaret(html);
                saveToHistory();
            }
        }
    }

    function closeFontSizeDialog() {
        document.getElementById('fontSizeDialog').style.display = 'none';
        document.getElementById('customFontSize').value = '';
    }

    function setCustomFontSize() {
        const size = document.getElementById('customFontSize').value;
        if (size && size >= 8 && size <= 72) {
            setFontSize(size);
            closeFontSizeDialog();
        } else {
            showNotification('Пожалуйста, введите размер от 8 до 72 пикселей', 'warning');
        }
    }

    // Функции для работы с медиа
    function showMediaDialog() {
        saveSelection();
        document.getElementById('mediaDialog').style.display = 'block';
        
        // Добавляем обработчики переключения типа медиа
        const mediaTypeRadios = document.querySelectorAll('input[name="mediaType"]');
        mediaTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('videoUrlSection').style.display = 'none';
                document.getElementById('videoFileSection').style.display = 'none';
                document.getElementById('audioMediaSection').style.display = 'none';
                document.getElementById('audioStreamSection').style.display = 'none';
                
                if (this.value === 'video-url') {
                    document.getElementById('videoUrlSection').style.display = 'block';
                } else if (this.value === 'video-file') {
                    document.getElementById('videoFileSection').style.display = 'block';
                    loadVideoFilesList();
                } else if (this.value === 'audio') {
                    document.getElementById('audioMediaSection').style.display = 'block';
                    loadAudioFilesList();
                } else if (this.value === 'audio-stream') {
                    document.getElementById('audioStreamSection').style.display = 'block';
                }
            });
        });
    }

    function closeMediaDialog() {
        document.getElementById('mediaDialog').style.display = 'none';
        document.getElementById('mediaUrl').value = '';
        document.getElementById('videoFile').value = '';
        document.getElementById('audioFile').value = '';
        document.getElementById('audioStreamUrl').value = '';
        // Сбрасываем на видео URL
        document.querySelector('input[name="mediaType"][value="video-url"]').checked = true;
        document.getElementById('videoUrlSection').style.display = 'block';
        document.getElementById('videoFileSection').style.display = 'none';
        document.getElementById('audioMediaSection').style.display = 'none';
        document.getElementById('audioStreamSection').style.display = 'none';
    }

    function insertMedia() {
        const mediaType = document.querySelector('input[name="mediaType"]:checked').value;
        
        if (mediaType === 'video-url') {
            const url = document.getElementById('mediaUrl').value.trim();
            if (!url) {
                showNotification('Пожалуйста, введите URL видео', 'warning');
                return;
            }

            let embedCode = '';

            // Определяем тип медиа по URL
            if (url.includes('youtube.com') || url.includes('youtu.be')) {
                const youtubeId = extractYoutubeId(url);
                embedCode = `<iframe width="560" height="315" src="https://www.youtube.com/embed/${youtubeId}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
            } else if (url.includes('vimeo.com')) {
                const vimeoId = extractVimeoId(url);
                embedCode = `<iframe width="560" height="315" src="https://player.vimeo.com/video/${vimeoId}" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>`;
            } else {
                // Встраиваем как iframe
                embedCode = `<iframe width="560" height="315" src="${url}" frameborder="0" sandbox="allow-same-origin allow-scripts allow-popups" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
            }

            if (editorMode === 'code') {
                const ta = document.getElementById('content');
                const cursorPos = ta.selectionStart;
                ta.value = ta.value.substring(0, cursorPos) + embedCode + ta.value.substring(cursorPos);
            } else {
                insertHtmlAtCaret(wrapMediaWithControls(embedCode, 'iframe'));
            }
            
            saveToHistory();
            closeMediaDialog();
        } else if (mediaType === 'audio-stream') {
            const url = document.getElementById('audioStreamUrl').value.trim();
            if (!url) {
                showNotification('Пожалуйста, введите URL аудиопотока', 'warning');
                return;
            }

            const audioElement = `<audio controls style="width: 100%; max-width: 600px; margin: 10px 0;"><source src="${url}">Ваш браузер не поддерживает аудио элемент.</audio>`;

            if (editorMode === 'code') {
                const ta = document.getElementById('content');
                const cursorPos = ta.selectionStart;
                ta.value = ta.value.substring(0, cursorPos) + audioElement + '\n' + ta.value.substring(cursorPos);
            } else {
                insertHtmlAtCaret(wrapMediaWithControls(audioElement, 'audio'));
            }
            
            saveToHistory();
            closeMediaDialog();
        }
        // Для аудио вставка происходит при клике на файл в списке
    }

    function uploadAudioFile() {
        const fileInput = document.getElementById('audioFile');
        const file = fileInput.files[0];
        
        if (!file) {
            showNotification('Пожалуйста, выберите аудио файл', 'warning');
            return;
        }
        
        // Проверяем тип файла
        if (!file.type.startsWith('audio/')) {
            showNotification('Пожалуйста, выберите аудио файл', 'warning');
            return;
        }
        
        const formData = new FormData();
        formData.append('audio', file);
        
        fetch('upload_audio.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Аудио файл загружен', 'success');
                fileInput.value = '';
                loadAudioFilesList();
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showNotification('Ошибка загрузки файла', 'error');
        });
    }

    function loadAudioFilesList() {
        fetch('get_audio_files.php')
            .then(response => response.json())
            .then(data => {
                const list = document.getElementById('audioFilesList');
                
                if (data.success && data.files.length > 0) {
                    list.innerHTML = data.files.map(file => `
                        <div style="padding: 10px 12px; margin-bottom: 8px; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s;" 
                             onmouseover="this.style.background='rgba(128,128,128,0.1)'" onmouseout="this.style.background='transparent'"
                             onclick="insertAudioFile('${file.path}', '${file.name}')">
                            <div style="min-width: 0; flex: 1; padding-right: 10px;">
                                <div style="color: var(--text-color); font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">🎵 ${file.name}</div>
                                <div style="color: var(--text-color); opacity: 0.6; font-size: 12px; margin-top: 2px;">${formatFileSize(file.size)}</div>
                            </div>
                            <button onclick="event.stopPropagation(); deleteAudioFile('${file.name}')" 
                                    class="delete-confirm-btn cancel" style="padding: 6px 10px; font-size: 12px; border-radius: 6px; color: gray; border-color: gray;">
                                Удалить
                            </button>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = '<div style="color: var(--text-color); opacity: 0.6;">Нет загруженных аудио файлов</div>';
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                document.getElementById('audioFilesList').innerHTML = '<div style="color: #f44336;">Ошибка загрузки списка</div>';
            });
    }

    function insertAudioFile(filePath, fileName) {
        const audioElement = `<audio controls style="width: 100%; max-width: 600px; margin: 10px 0;"><source src="${filePath}" type="audio/mpeg">Ваш браузер не поддерживает аудио элемент.</audio>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + audioElement + '\n' + ta.value.substring(cursorPos);
        } else {
            // Вставляем аудио элемент
            const wrappedAudioHtml = wrapMediaWithControls(audioElement, 'audio');
            const ve = document.getElementById('contentVisual');
            ve.focus();
            const sel = window.getSelection();
            let range = null;
            
            if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
                range = savedRange;
            } else if (sel && sel.rangeCount > 0) {
                range = sel.getRangeAt(0);
            }
            
            if (!range) {
                ve.insertAdjacentHTML('beforeend', wrappedAudioHtml);
                const emptyDiv = document.createElement('div');
                emptyDiv.innerHTML = '<br>';
                ve.appendChild(emptyDiv);
            } else {
                range.deleteContents();
                
                // Создаем аудио элемент
                const temp = document.createElement('div');
                temp.innerHTML = wrappedAudioHtml;
                const audioNode = temp.firstChild;
                
                // Создаем пустой блок для курсора
                const emptyDiv = document.createElement('div');
                emptyDiv.innerHTML = '<br>';
                
                // Вставляем аудио
                range.insertNode(audioNode);
                
                // Вставляем пустой блок после аудио
                if (audioNode.nextSibling) {
                    audioNode.parentNode.insertBefore(emptyDiv, audioNode.nextSibling);
                } else {
                    audioNode.parentNode.appendChild(emptyDiv);
                }
                
                // Устанавливаем курсор в пустой блок
                const newRange = document.createRange();
                newRange.setStart(emptyDiv, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        }
        
        saveToHistory();
        closeMediaDialog();
        showNotification('Аудио файл добавлен в статью', 'success');
    }

    function deleteAudioFile(fileName) {
        showConfirm('Удалить аудио файл?').then(result => {
            if (!result) return;
            
            fetch('delete_audio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ filename: fileName })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Аудио файл удален', 'success');
                    loadAudioFilesList();
                } else {
                    showNotification('Ошибка: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showNotification('Ошибка удаления файла', 'error');
            });
        });
    }

    // Функции для работы с видео файлами
    function uploadVideoFile() {
        const fileInput = document.getElementById('videoFile');
        const file = fileInput.files[0];
        
        if (!file) {
            showNotification('Пожалуйста, выберите видео файл', 'warning');
            return;
        }
        
        // Проверяем тип файла
        if (!file.type.startsWith('video/')) {
            showNotification('Пожалуйста, выберите видео файл', 'warning');
            return;
        }
        
        const formData = new FormData();
        formData.append('video', file);
        
        fetch('upload_video.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Видео файл загружен', 'success');
                fileInput.value = '';
                loadVideoFilesList();
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showNotification('Ошибка загрузки файла', 'error');
        });
    }

    function loadVideoFilesList() {
        fetch('get_video_files.php')
            .then(response => response.json())
            .then(data => {
                const list = document.getElementById('videoFilesList');
                
                if (data.success && data.files.length > 0) {
                    list.innerHTML = data.files.map(file => `
                        <div style="padding: 10px 12px; margin-bottom: 8px; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s;" 
                             onmouseover="this.style.background='rgba(128,128,128,0.1)'" onmouseout="this.style.background='transparent'"
                             onclick="insertVideoFile('${file.path}', '${file.name}')">
                            <div style="min-width: 0; flex: 1; padding-right: 10px;">
                                <div style="color: var(--text-color); font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">🎬 ${file.name}</div>
                                <div style="color: var(--text-color); opacity: 0.6; font-size: 12px; margin-top: 2px;">${formatFileSize(file.size)}</div>
                            </div>
                            <button onclick="event.stopPropagation(); deleteVideoFile('${file.name}')" 
                                    class="delete-confirm-btn cancel" style="padding: 6px 10px; font-size: 12px; border-radius: 6px; color: gray; border-color: gray;">
                                Удалить
                            </button>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = '<div style="color: var(--text-color); opacity: 0.6;">Нет загруженных видео файлов</div>';
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                document.getElementById('videoFilesList').innerHTML = '<div style="color: #f44336;">Ошибка загрузки списка</div>';
            });
    }

    function insertVideoFile(filePath, fileName) {
        const videoElement = `<video controls style="width: 100%; max-width: 800px; margin: 10px 0;"><source src="${filePath}" type="video/mp4">Ваш браузер не поддерживает видео элемент.</video>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + videoElement + '\n' + ta.value.substring(cursorPos);
        } else {
            // Вставляем видео элемент
            const wrappedVideoHtml = wrapMediaWithControls(videoElement, 'video');
            const ve = document.getElementById('contentVisual');
            ve.focus();
            const sel = window.getSelection();
            let range = null;
            
            if (savedRange && ve.contains(savedRange.commonAncestorContainer)) {
                range = savedRange;
            } else if (sel && sel.rangeCount > 0) {
                range = sel.getRangeAt(0);
            }
            
            if (!range) {
                ve.insertAdjacentHTML('beforeend', wrappedVideoHtml);
                const emptyDiv = document.createElement('div');
                emptyDiv.innerHTML = '<br>';
                ve.appendChild(emptyDiv);
            } else {
                range.deleteContents();
                
                // Создаем видео элемент
                const temp = document.createElement('div');
                temp.innerHTML = wrappedVideoHtml;
                const videoNode = temp.firstChild;
                
                // Создаем пустой блок для курсора
                const emptyDiv = document.createElement('div');
                emptyDiv.innerHTML = '<br>';
                
                // Вставляем видео
                range.insertNode(videoNode);
                
                // Вставляем пустой блок после видео
                if (videoNode.nextSibling) {
                    videoNode.parentNode.insertBefore(emptyDiv, videoNode.nextSibling);
                } else {
                    videoNode.parentNode.appendChild(emptyDiv);
                }
                
                // Устанавливаем курсор в пустой блок
                const newRange = document.createRange();
                newRange.setStart(emptyDiv, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        }
        
        saveToHistory();
        closeMediaDialog();
        showNotification('Видео файл добавлен в статью', 'success');
    }

    function deleteVideoFile(fileName) {
        showConfirm('Удалить видео файл?').then(result => {
            if (!result) return;
            
            fetch('delete_video.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ filename: fileName })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Видео файл удален', 'success');
                    loadVideoFilesList();
                } else {
                    showNotification('Ошибка: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showNotification('Ошибка удаления файла', 'error');
            });
        });
    }

    function deleteAudioFile(fileName) {
        showConfirm('Удалить аудио файл?').then(result => {
            if (!result) return;
            
            fetch('delete_audio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ filename: fileName })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Аудио файл удален', 'success');
                    loadAudioFilesList();
                } else {
                    showNotification('Ошибка: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showNotification('Ошибка удаления файла', 'error');
            });
        });
    }

// Вспомогательные функции для извлечения ID
function extractYoutubeId(url) {
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return (match && match[2].length === 11) ? match[2] : null;
}

function extractVimeoId(url) {
    const regExp = /vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|)(\d+)(?:$|\/|\?)/;
    const match = url.match(regExp);
    return match ? match[3] : null;
}

    // Функции для работы со spoiler
    // Переменная для хранения выделенного текста для spoiler
    let savedSpoilerText = '';
    let savedSpoilerRange = null;

    function openSpoilerDialog() {
        savedSpoilerText = '';
        savedSpoilerRange = null;
        
        if (editorMode === 'visual') {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                savedSpoilerRange = range.cloneRange();
                const container = document.createElement('div');
                container.appendChild(range.cloneContents());
                savedSpoilerText = container.innerHTML;
            }
        } else if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            savedSpoilerText = ta.value.substring(start, end);
        }
        
        document.getElementById('spoilerDialog').style.display = 'block';
        document.getElementById('spoilerTitle').value = '';
        document.getElementById('spoilerTitle').focus();
    }

    function closeSpoilerDialog() {
        document.getElementById('spoilerDialog').style.display = 'none';
        savedSpoilerText = '';
        savedSpoilerRange = null;
    }

    function insertSpoiler() {
        const title = document.getElementById('spoilerTitle').value.trim() || 'Подробности';
        
        let selectedText = savedSpoilerText || 'Содержимое блока';
        
        const spoilerHtml = `<details class="spoiler-block"><summary class="spoiler-title">${title}</summary><div class="spoiler-content">${selectedText}</div></details>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const before = ta.value.substring(0, start);
            const after = ta.value.substring(end);
            ta.value = before + spoilerHtml + '\n' + after;
        } else {
            // Восстанавливаем сохраненный range если есть
            if (savedSpoilerRange) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedSpoilerRange);
                savedSpoilerRange.deleteContents();
            }
            insertImageBlockAtCaret(spoilerHtml);
        }
        
        saveToHistory();
        closeSpoilerDialog();
    }

    // Функции для работы с маркером
    let savedMarkerText = '';
    let savedMarkerRange = null;
    let selectedMarkerStyle = 'straight';

    function openMarkerDialog() {
        savedMarkerText = '';
        savedMarkerRange = null;
        
        if (editorMode === 'visual') {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                savedMarkerRange = range.cloneRange();
                savedMarkerText = range.toString();
            }
        } else if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            savedMarkerText = ta.value.substring(start, end);
        }
        
        if (!savedMarkerText) {
            showNotification('Выделите текст для применения маркера', 'warning');
            return;
        }
        
        document.getElementById('markerDialog').style.display = 'block';
        
        // Добавляем обработчики на кнопки стилей
        const styleBtns = document.querySelectorAll('.marker-style-btn');
        styleBtns.forEach(btn => {
            btn.onclick = function() {
                styleBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedMarkerStyle = this.getAttribute('data-style');
            };
        });
        
        // Добавляем обработчики на кнопки цветов
        const colorBtns = document.querySelectorAll('.marker-color-btn');
        colorBtns.forEach(btn => {
            btn.onclick = function() {
                const color = this.getAttribute('data-color');
                insertMarker(color, selectedMarkerStyle);
            };
        });
    }

    function closeMarkerDialog() {
        document.getElementById('markerDialog').style.display = 'none';
        savedMarkerText = '';
        savedMarkerRange = null;
    }

    function insertMarker(color, style) {
        if (!savedMarkerText) {
            closeMarkerDialog();
            return;
        }
        
        // Определяем название цвета для data-атрибута
        const colorNames = {
            '#ffeb3b': 'yellow',
            '#4caf50': 'green',
            '#2196f3': 'blue',
            '#ff9800': 'orange',
            '#e91e63': 'pink',
            '#9c27b0': 'purple'
        };
        const colorName = colorNames[color] || 'yellow';
        
        const markerHtml = `<mark data-marker-color="${colorName}" data-marker-style="${style}">${savedMarkerText}</mark>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const before = ta.value.substring(0, start);
            const after = ta.value.substring(end);
            ta.value = before + markerHtml + after;
            // Устанавливаем курсор после маркера
            const newPos = start + markerHtml.length;
            ta.setSelectionRange(newPos, newPos);
            ta.focus();
        } else {
            if (savedMarkerRange) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedMarkerRange);
                savedMarkerRange.deleteContents();
                
                const temp = document.createElement('div');
                temp.innerHTML = markerHtml;
                const frag = document.createDocumentFragment();
                let node, lastNode;
                while ((node = temp.firstChild)) {
                    lastNode = frag.appendChild(node);
                }
                savedMarkerRange.insertNode(frag);
                
                // Устанавливаем курсор после маркера
                if (lastNode) {
                    const newRange = document.createRange();
                    newRange.setStartAfter(lastNode);
                    newRange.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                    
                    // Добавляем пробел после маркера чтобы выйти из форматирования
                    const space = document.createTextNode('\u200B'); // Zero-width space
                    newRange.insertNode(space);
                    newRange.setStartAfter(space);
                    newRange.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(newRange);
                }
            }
        }
        
        saveToHistory();
        closeMarkerDialog();
    }

    // Функции для работы с кодом
    function insertCode() {
        saveSelection();
        document.getElementById('codeDialog').style.display = 'block';
    }

    function closeCodeDialog() {
        document.getElementById('codeDialog').style.display = 'none';
        document.getElementById('codeInput').value = '';
    }

    function insertCodeBlock() {
        const code = document.getElementById('codeInput').value;
        const language = document.getElementById('codeLanguage').value;
        
        if (code.trim() === '') {
            showNotification('Пожалуйста, введите код', 'warning');
            return;
        }

        const escapedCode = code
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        const codeBlock = `<pre class="code-block" data-language="${language}">${escapedCode}</pre>`;
        
        if (editorMode === 'code') {
            const ta = document.getElementById('content');
            const cursorPos = ta.selectionStart;
            ta.value = ta.value.substring(0, cursorPos) + codeBlock + ta.value.substring(cursorPos);
        } else {
            insertHtmlAtCaret(codeBlock);
        }
        
        saveToHistory();
        closeCodeDialog();
    }

    // Функции для управления статьями
    function toggleManagePosts() {
        const managePanel = document.getElementById('managePosts');
        managePanel.classList.toggle('active');
        
        if (managePanel.classList.contains('active')) {
            loadPosts();
        } else {
            // Очищаем поле поиска при закрытии панели
            const searchInput = document.getElementById('postsSearchInput');
            if (searchInput) {
                searchInput.value = '';
            }
        }
    }

    function loadPosts() {
        // Добавляем timestamp для предотвращения кэширования
        fetch('data/blog/posts-meta.json?t=' + Date.now())
            .then(response => response.json())
            .then(posts => {
                const postsList = document.getElementById('postsList');
                if (!posts || posts.length === 0) {
                    postsList.innerHTML = '<p class="manage-posts-empty">Пока нет статей</p>';
                    return;
                }
                const escapeHtml = function(str) {
                    if (!str) return '';
                    var div = document.createElement('div');
                    div.textContent = str;
                    return div.innerHTML;
                };
                
                // Сортируем статьи по ID в обратном порядке (новые первыми)
                const sortedPosts = [...posts].sort((a, b) => b.id - a.id);
                
                postsList.innerHTML = '<ul class="post-list">' +
                    sortedPosts.map(post => `
                        <li class="post-item">
                            <div class="post-item-title">${escapeHtml(post.title)}</div>
                            <span class="post-item-date">${escapeHtml(post.date)}</span>
                            <div class="post-item-actions">
                                <button type="button" class="edit-btn" onclick="editPost(${post.id})">Изменить</button>
                                <button type="button" class="additional-btn" onclick="openAdditionalSettings(${post.id}, '${escapeHtml(post.title)}')">Дополнительно</button>
                                <button type="button" class="delete-btn" onclick="deletePost(${post.id})">Удалить</button>
                            </div>
                        </li>
                    `).join('') +
                    '</ul>';
            })
            .catch(error => {
                console.error('Ошибка загрузки статей:', error);
                const postsList = document.getElementById('postsList');
                postsList.innerHTML = '<p class="manage-posts-empty">Пока нет статей</p>';
            });
    }

    function editPost(postId) {
        fetch('get_post_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: postId })
        })
        .then(response => {
            // Проверяем что ответ действительно JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Сервер вернул не JSON ответ. Проверьте настройки PHP и nginx.');
            }
            return response.text();
        })
        .then(text => {
            // Пытаемся распарсить JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Ответ сервера:', text);
                throw new Error('Ошибка парсинга JSON. Ответ сервера: ' + text.substring(0, 200));
            }
        })
        .then(data => {
            if (data.success) {
                document.getElementById('title').value = data.title;

                let editedContent = formatHTML(data.content);
                document.getElementById('content').value = editedContent;
                const ve = document.getElementById('contentVisual');
                if (editorMode === 'visual' && ve) {
                    ve.innerHTML = editedContent;
                    wrapExistingEditorImages();
                    addColumnResizers(); // Добавляем ручки изменения размера столбцов
                    
                    // Убеждаемся что блоки кода имеют правильную высоту
                    setTimeout(() => {
                        const codeBlocks = ve.querySelectorAll('.code-block');
                        codeBlocks.forEach(block => {
                            if (block.scrollHeight > 400) {
                                block.style.maxHeight = '400px';
                            } else {
                                block.style.maxHeight = 'none';
                            }
                        });
                    }, 100);
                }
                currentEditId = postId;
                const submitButton = document.getElementById('submitButton');
                submitButton.textContent = 'Сохранить изменения';
                submitButton.classList.add('editing');
                const floatingSaveBtn = document.getElementById('floatingSaveBtn');
                if (floatingSaveBtn) {
                    floatingSaveBtn.textContent = 'Сохранить изменения';
                    floatingSaveBtn.classList.add('editing');
                }
                toggleManagePosts();
                document.getElementById('blogForm').scrollIntoView();
                
                // Инициализируем историю с текущим состоянием
                clearHistory();
                saveToHistory();
            } else {
                showNotification('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки статьи:', error);
            showNotification('Ошибка при загрузке статьи: ' + error.message, 'error');
        });
    }

    let deletePostId = null;

    function filterPosts() {
        const searchInput = document.getElementById('postsSearchInput');
        const searchTerm = searchInput.value.toLowerCase().trim();
        const postItems = document.querySelectorAll('.post-item');
        
        let visibleCount = 0;
        
        postItems.forEach(item => {
            const title = item.querySelector('.post-item-title').textContent.toLowerCase();
            const date = item.querySelector('.post-item-date').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || date.includes(searchTerm)) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        // Показываем сообщение если ничего не найдено
        const postsList = document.getElementById('postsList');
        let emptyMessage = postsList.querySelector('.search-empty-message');
        
        if (visibleCount === 0 && searchTerm !== '') {
            if (!emptyMessage) {
                emptyMessage = document.createElement('p');
                emptyMessage.className = 'manage-posts-empty search-empty-message';
                emptyMessage.textContent = 'Ничего не найдено';
                postsList.appendChild(emptyMessage);
            }
        } else if (emptyMessage) {
            emptyMessage.remove();
        }
    }

    function deletePost(postId) {
        deletePostId = postId;
        const overlay = document.getElementById('deleteConfirmOverlay');
        overlay.classList.add('show');
    }
    
    function closeDeleteConfirm() {
        const overlay = document.getElementById('deleteConfirmOverlay');
        overlay.classList.remove('show');
        deletePostId = null;
    }
    
    function confirmDelete() {
        if (!deletePostId) return;
        
        fetch('delete_post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: deletePostId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const message = data.renumbered 
                    ? 'Статья удалена, нумерация обновлена' 
                    : 'Статья успешно удалена';
                showNotification(message, 'success');
                loadPosts();
                closeDeleteConfirm();
            } else {
                showNotification('Ошибка при удалении статьи', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка удаления:', error);
            showNotification('Ошибка при удалении статьи', 'error');
        });
    }

    // Обработчик отправки формы
    document.getElementById('modeVisualBtn').addEventListener('click', function(){ setMode('visual'); });
    document.getElementById('modeCodeBtn').addEventListener('click', function(){ setMode('code'); });
    setMode('visual');

    function handleSubmit(e) {
        if (e) e.preventDefault();
        const titleInput = document.getElementById('title');
        const title = titleInput.value.trim();
        
        if (!title) {
            showNotification('Пожалуйста, введите заголовок статьи', 'error');
            titleInput.focus();
            return;
        }

        const ta = document.getElementById('content');
        const ve = document.getElementById('contentVisual');
        
        let content;
        if (editorMode === 'visual') {
            // Очищаем контент от элементов интерфейса редактора
            content = cleanContentForSave(ve.innerHTML);
            ta.value = content;
        } else {
            content = ta.value;
        }
        
        const endpoint = currentEditId ? 'update_post.php' : 'save_post.php';
        const data = { title: title, content: content };
        if (currentEditId) { data.id = currentEditId; }
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(async (response) => {
            let payload;
            try { payload = await response.json(); } catch (_) { payload = null; }
            if (!response.ok || (payload && payload.success === false)) {
                throw new Error((payload && payload.error) || 'Server error');
            }
            showNotification(
                currentEditId ? 'Статья успешно обновлена!' : 'Статья успешно добавлена!',
                'success'
            );
            
            // Очищаем форму
            document.getElementById('blogForm').reset();
            
            // Очищаем визуальный редактор
            const ve = document.getElementById('contentVisual');
            if (ve) {
                ve.innerHTML = '';
            }
            
            // Очищаем текстовое поле
            const ta = document.getElementById('content');
            if (ta) {
                ta.value = '';
            }
            
            // Обновляем список статей
            loadPosts();
            
            currentEditId = null;
            const submitButton = document.getElementById('submitButton');
            submitButton.textContent = 'Сохранить';
            submitButton.classList.remove('editing');
            const floatingSaveBtn = document.getElementById('floatingSaveBtn');
            if (floatingSaveBtn) {
                floatingSaveBtn.textContent = 'Сохранить';
                floatingSaveBtn.classList.remove('editing');
            }
            
            // Очищаем историю
            clearHistory();
        })
        .catch(() => {
            showNotification('Ошибка при сохранении статьи', 'error');
        });
    }

    document.getElementById('blogForm').addEventListener('submit', handleSubmit);
    document.getElementById('submitButton').addEventListener('click', handleSubmit);

    // Обработчики изменения размера
    document.getElementById('imageSize').addEventListener('change', function(e) {
        const customInputs = document.getElementById('customSizeInputs');
        customInputs.style.display = e.target.value === 'custom' ? 'flex' : 'none';
        
        if (e.target.value !== 'custom') {
            document.getElementById('customWidth').value = '';
            document.getElementById('customHeight').value = '';
            document.getElementById('widthUnit').value = 'px';
            document.getElementById('heightUnit').value = 'px';
        }
    });

    document.getElementById('customFontSize').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            setCustomFontSize();
        }
    });
    function setTextColor(color) {
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = colorInsertStart;
            var end = colorInsertEnd;
            var selectedText = ta.value.substring(start, end);
            if (selectedText) {
                var colorSpan = '<span style="color: ' + color + ';">' + selectedText + '</span>';
                ta.value = ta.value.substring(0, start) + colorSpan + ta.value.substring(end);
                ta.focus();
                saveToHistory();
            }
        } else {
            var text = (savedRange && savedRange.toString()) || document.getSelection().toString();
            if (text) {
                var html = '<span style="color: ' + color + ';">' + text + '</span>';
                insertHtmlAtCaret(html);
                saveToHistory();
            }
        }
    }

    (function initColorPalette() {
        var presetColors = ['#000000','#333333','#666666','#999999','#cccccc','#ffffff','#ff0000','#ff6600','#ff9900','#ffcc00','#99cc00','#00cc00','#00cccc','#0066ff','#0000ff','#6600cc','#9900cc','#cc0099','#ff0066','#8b4513','#a0522d','#cd853f','#deb887','#ff69b4','#ffc0cb','#add8e6','#98fb98','#f0e68c','#ffd700','#ff6347'];
        function fillGrid(gridId) {
            var grid = document.getElementById(gridId);
            if (!grid) return;
            grid.innerHTML = '';
            presetColors.forEach(function(hex) {
                var swatch = document.createElement('span');
                swatch.className = 'color-swatch';
                swatch.style.background = hex;
                swatch.title = hex;
                swatch.setAttribute('data-color', hex);
                grid.appendChild(swatch);
            });
        }
        fillGrid('colorPaletteGridMain');

        function openColorPicker(wrap) {
            document.querySelectorAll('.color-picker-wrap.is-open').forEach(function(w) { if (w !== wrap) w.classList.remove('is-open'); });
            wrap.classList.add('is-open');
        }
        function toggleColorPicker(wrap) {
            var isOpen = wrap.classList.contains('is-open');
            document.querySelectorAll('.color-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
            if (!isOpen) {
                wrap.classList.add('is-open');
            }
        }
        function closeAllColorPickers() {
            document.querySelectorAll('.color-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
        }
        function applyColorAndClose(hex, wrap) {
            setTextColor(hex);
            wrap.classList.remove('is-open');
            var preview = wrap.querySelector('.color-preview');
            if (preview) preview.style.background = hex;
        }
        
        // Функция для меню "Прочее"
        window.toggleMoreMenu = function() {
            const wrap = document.getElementById('moreMenuWrap');
            if (!wrap) return;
            
            const isOpen = wrap.classList.contains('is-open');
            
            // Закрываем другие открытые меню
            document.querySelectorAll('.color-picker-wrap.is-open, .font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) {
                w.classList.remove('is-open');
            });
            
            if (!isOpen) {
                wrap.classList.add('is-open');
            } else {
                wrap.classList.remove('is-open');
                // Закрываем подменю
                document.querySelectorAll('.more-menu-item.has-submenu').forEach(function(item) {
                    item.classList.remove('submenu-open');
                });
            }
        };

        ['colorPickerWrapMain'].forEach(function(id) {
            var wrap = document.getElementById(id);
            if (!wrap) return;
            var btn = wrap.querySelector('.color-picker-btn');
            var popover = wrap.querySelector('.color-palette-popover');
            var customInput = wrap.querySelector('input[type="color"]');
            if (btn) {
                btn.addEventListener('mousedown', function(e) {
                    saveSelection();
                    if (editorMode === 'code') {
                        var ta = document.getElementById('content');
                        colorInsertStart = ta.selectionStart;
                        colorInsertEnd = ta.selectionEnd;
                    }
                });
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleColorPicker(wrap);
                });
            }
            if (popover) {
                popover.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var swatch = e.target.closest('.color-swatch');
                    if (swatch && swatch.dataset.color) applyColorAndClose(swatch.dataset.color, wrap);
                });
            }
            if (customInput) customInput.addEventListener('change', function() {
                applyColorAndClose(this.value, wrap);
            });
        });
        document.addEventListener('click', closeAllColorPickers);
    })();

    function applyCustomFontSize(wrapId) {
        var wrap = document.getElementById(wrapId);
        var input = wrap.querySelector('.font-size-custom input[type="number"]');
        var size = input && input.value ? parseInt(input.value, 10) : 0;
        if (size >= 8 && size <= 72) {
            var sizeStr = size + 'px';
            setFontSize(String(size));
            
            // Обновляем текст кнопки
            const sizeBtn = document.getElementById('fontSizeBtn');
            if (sizeBtn) {
                sizeBtn.textContent = sizeStr;
            }
            
            input.value = '';
            wrap.classList.remove('is-open');
        } else {
            showNotification('Введите размер от 8 до 72', 'warning');
        }
    }
    function applyCustomFontFamily(wrapId) {
        var wrap = document.getElementById(wrapId);
        var input = wrap.querySelector('.font-family-custom input[type="text"]');
        var font = input && input.value ? input.value.trim() : '';
        if (font) {
            setFontFamily(font);
            
            // Обновляем текст кнопки
            const fontBtn = document.getElementById('fontFamilyBtn');
            if (fontBtn) {
                fontBtn.textContent = font;
                fontBtn.style.fontFamily = font;
            }
            
            input.value = '';
            wrap.classList.remove('is-open');
        } else {
            showNotification('Введите название шрифта', 'warning');
        }
    }

    (function initFontSizeAndFamilyPopovers() {
        function closeAllFontPopovers() {
            document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
        }
        function toggleWrap(wrap) {
            var isOpen = wrap.classList.contains('is-open');
            document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) { w.classList.remove('is-open'); });
            if (!isOpen) {
                wrap.classList.add('is-open');
            }
        }
        function openWrap(wrap, closeOthers) {
            if (closeOthers) {
                document.querySelectorAll('.font-size-picker-wrap.is-open, .font-family-picker-wrap.is-open').forEach(function(w) { if (w !== wrap) w.classList.remove('is-open'); });
            }
            wrap.classList.add('is-open');
        }
        
        // Закрытие при клике вне меню
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.more-menu-wrap')) {
                const moreMenu = document.getElementById('moreMenuWrap');
                if (moreMenu) {
                    moreMenu.classList.remove('is-open');
                    // Закрываем подменю
                    document.querySelectorAll('.more-menu-item.has-submenu').forEach(function(item) {
                        item.classList.remove('submenu-open');
                    });
                }
            }
        });
        
        ['fontSizeWrapMain'].forEach(function(id) {
            var wrap = document.getElementById(id);
            if (!wrap) return;
            var btn = wrap.querySelector('.font-size-picker-btn');
            var popover = wrap.querySelector('.font-size-popover-inner');
            if (btn) {
                btn.addEventListener('mousedown', function() {
                    saveSelection();
                    if (editorMode === 'code') {
                        var ta = document.getElementById('content');
                        colorInsertStart = ta.selectionStart;
                        colorInsertEnd = ta.selectionEnd;
                    }
                });
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleWrap(wrap);
                });
            }
            if (popover) {
                popover.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var item = e.target.closest('.font-size-item[data-size]');
                    if (item) {
                        var sizeValue = item.getAttribute('data-size');
                        setFontSize(sizeValue);
                        
                        // Обновляем текст кнопки
                        const sizeBtn = document.getElementById('fontSizeBtn');
                        if (sizeBtn) {
                            sizeBtn.textContent = sizeValue + 'px';
                        }
                        
                        wrap.classList.remove('is-open');
                    }
                });
            }
        });
        ['fontFamilyWrapMain'].forEach(function(id) {
            var wrap = document.getElementById(id);
            if (!wrap) return;
            var btn = wrap.querySelector('.font-family-picker-btn');
            var popover = wrap.querySelector('.font-family-popover-inner');
            if (btn) {
                btn.addEventListener('mousedown', function() {
                    saveSelection();
                    if (editorMode === 'code') {
                        var ta = document.getElementById('content');
                        colorInsertStart = ta.selectionStart;
                        colorInsertEnd = ta.selectionEnd;
                    }
                });
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleWrap(wrap);
                });
            }
            if (popover) {
                popover.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var item = e.target.closest('.font-family-item[data-font]');
                    if (item) {
                        var fontName = item.getAttribute('data-font');
                        setFontFamily(fontName);
                        
                        // Обновляем текст кнопки
                        const fontBtn = document.getElementById('fontFamilyBtn');
                        if (fontBtn) {
                            fontBtn.textContent = fontName;
                            fontBtn.style.fontFamily = fontName;
                        }
                        
                        wrap.classList.remove('is-open');
                    }
                });
            }
        });
        document.addEventListener('click', closeAllFontPopovers);
    })();

// Функции для работы со шрифтом
    function setFontFamily(font) {
        if (editorMode === 'code') {
            var ta = document.getElementById('content');
            var start = colorInsertStart;
            var end = colorInsertEnd;
            var selectedText = ta.value.substring(start, end);
            if (selectedText) {
                // Применяем к выделенному тексту
                var fontSpan = '<span style="font-family: \'' + font.replace(/'/g, "\\'") + '\';">' + selectedText + '</span>';
                ta.value = ta.value.substring(0, start) + fontSpan + ta.value.substring(end);
                ta.selectionStart = start;
                ta.selectionEnd = start + fontSpan.length;
                ta.focus();
            } else {
                // Вставляем span для последующего текста
                var fontSpan = '<span style="font-family: \'' + font.replace(/'/g, "\\'") + '\';">​</span>';
                ta.value = ta.value.substring(0, start) + fontSpan + ta.value.substring(start);
                // Ставим курсор перед закрывающим тегом
                ta.selectionStart = ta.selectionEnd = start + fontSpan.length - 8;
                ta.focus();
            }
        } else {
            var ve = document.getElementById('contentVisual');
            if (!ve) return;
            
            ve.focus();
            
            // Применяем шрифт через execCommand
            document.execCommand('fontName', false, font);
        }
    }

function closeFontFamilyDialog() {
    document.getElementById('fontFamilyDialog').style.display = 'none';
    document.getElementById('customFontFamily').value = '';
}

function setCustomFontFamily() {
    const font = document.getElementById('customFontFamily').value.trim();
    if (font) {
        setFontFamily(font);
        closeFontFamilyDialog();
    } else {
        showNotification('Пожалуйста, введите название шрифта', 'warning');
    }
}

    function insertImageGrid(layout) {
    const [cols, rows] = layout.split('x').map(Number);
    const gridStyle = `display: grid; grid-template-columns: repeat(${cols}, 1fr); gap: 10px;`;
    let imagesHTML = '';

    for (let i = 0; i < cols * rows; i++) {
        // Плейсхолдер для добавления реальных изображений
        imagesHTML += `<img src="" alt="Изображение ${i+1}" style="width: 100%; height: auto;">`;
    }

    const gridHTML = `<div style="${gridStyle}">${imagesHTML}</div>`;

    if (editorMode === 'code') {
        const ta = document.getElementById('content');
        const cursorPos = ta.selectionStart;
        ta.value = ta.value.substring(0, cursorPos) + gridHTML + '\n' + ta.value.substring(cursorPos);
    } else {
        insertImageBlockAtCaret(gridHTML);
    }
}

// Прилипающая строка кнопок: при прокрутке только панель форматирования фиксируется сверху
(function() {
    var sentinel = document.getElementById('formatBarSentinel');
    var placeholder = document.getElementById('formatBarPlaceholder');
    var formatBar = document.getElementById('formatBarRow');
    var floatingSaveBtn = document.getElementById('floatingSaveBtn');
    var submitButton = document.getElementById('submitButton');
    if (!sentinel || !placeholder || !formatBar) return;
    var stickyObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                formatBar.classList.remove('is-floating');
                placeholder.style.display = 'none';
                if (floatingSaveBtn) floatingSaveBtn.style.display = 'none';
            } else {
                var h = formatBar.offsetHeight;
                placeholder.style.height = h + 'px';
                placeholder.style.display = 'block';
                formatBar.classList.add('is-floating');
                if (floatingSaveBtn && submitButton) {
                    floatingSaveBtn.textContent = submitButton.textContent;
                    floatingSaveBtn.style.display = 'block';
                }
            }
        });
    }, { root: null, rootMargin: '0px', threshold: 0 });
    stickyObserver.observe(sentinel);
})();

// Подсветка активных кнопок при изменении выделения
document.addEventListener('selectionchange', function() {
    if (editorMode === 'visual') saveSelection();
    updateActiveButtons();
});

// ——— Проверка целостности файлов при загрузке ———
async function checkIntegrity() {
    try {
        const response = await fetch('check_integrity.php');
        const data = await response.json();
        
        if (!data.success && data.errors.length > 0) {
            const overlay = document.getElementById('integrityErrorOverlay');
            overlay.classList.add('show');
        }
    } catch (error) {
        console.error('Ошибка проверки целостности:', error);
    }
}

async function fixIntegrityErrors() {
    const button = document.querySelector('.integrity-error-button');
    button.textContent = 'Исправление...';
    button.disabled = true;
    
    try {
        const response = await fetch('fix_integrity.php');
        const data = await response.json();
        
        if (data.success) {
            showNotification('Все ошибки успешно исправлены!', 'success');
            
            const overlay = document.getElementById('integrityErrorOverlay');
            overlay.classList.remove('show');
            
            button.textContent = 'Исправить';
            button.disabled = false;
        } else {
            showNotification('Не удалось исправить некоторые ошибки: ' + data.errors.join(', '), 'error');
            button.textContent = 'Исправить';
            button.disabled = false;
        }
    } catch (error) {
        console.error('Ошибка исправления:', error);
        showNotification('Ошибка при исправлении файлов', 'error');
        button.textContent = 'Исправить';
        button.disabled = false;
    }
}

// Запускаем проверку при загрузке страницы
window.addEventListener('load', checkIntegrity);

// ——— Менеджер бэкапов ———
async function openBackupManager() {
    const overlay = document.getElementById('backupManagerOverlay');
    const content = document.getElementById('backupManagerContent');
    
    overlay.classList.add('show');
    content.innerHTML = '<div class="backup-empty">Загрузка...</div>';
    
    try {
        const response = await fetch('get_backups.php');
        const data = await response.json();
        
        if (data.success) {
            if (Object.keys(data.backups).length === 0) {
                content.innerHTML = '<div class="backup-empty">Нет сохраненных бэкапов</div>';
            } else {
                renderBackups(data.backups);
            }
        } else {
            content.innerHTML = '<div class="backup-empty">Ошибка загрузки бэкапов</div>';
        }
    } catch (error) {
        console.error('Ошибка загрузки бэкапов:', error);
        content.innerHTML = '<div class="backup-empty">Ошибка загрузки бэкапов</div>';
    }
}

function closeBackupManager() {
    const overlay = document.getElementById('backupManagerOverlay');
    overlay.classList.remove('show');
}

function renderBackups(backups) {
    const content = document.getElementById('backupManagerContent');
    let html = '';
    
    for (const postId in backups) {
        const post = backups[postId];
        const isDeleted = post.deleted === true;
        const displayTitle = isDeleted 
            ? `🗑️ ${escapeHtml(post.postTitle)}` 
            : `Статья #${postId}: ${escapeHtml(post.postTitle)}`;
        
        html += `
            <div class="backup-post-group ${isDeleted ? 'deleted-post' : ''}" id="backup-group-${postId}">
                <div class="backup-post-header" onclick="toggleBackupGroup('${postId}')">
                    <h3 class="backup-post-title">${displayTitle}</h3>
                    <span class="backup-post-toggle">▼</span>
                </div>
                <div class="backup-list">
                    ${post.backups.map((backup, index) => `
                        <div class="backup-item">
                            <div class="backup-info">
                                <div class="backup-number">Бэкап #${backup.backupNumber}</div>
                                <div class="backup-date">${escapeHtml(backup.date)}</div>
                                ${isDeleted ? '<div class="backup-date" style="color: #dc3545; font-weight: 600;">Статья удалена: ' + escapeHtml(post.deletedAt || '') + '</div>' : ''}
                            </div>
                            <div class="backup-actions">
                                <button class="backup-btn" onclick="viewBackup('${postId}', '${backup.filename}')">Посмотреть</button>
                                ${!isDeleted ? `<button class="backup-btn" onclick="restoreBackup('${postId}', '${backup.filename}', ${backup.backupNumber}, '${escapeHtml(backup.date)}')">Восстановить</button>` : ''}
                                <button class="backup-btn" onclick="openDeleteBackup('${postId}', '${backup.filename}', ${backup.backupNumber})">Удалить</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    content.innerHTML = html;
}

function toggleBackupGroup(postId) {
    const group = document.getElementById('backup-group-' + postId);
    if (group) {
        group.classList.toggle('expanded');
    }
}

async function viewBackup(postId, filename) {
    try {
        const response = await fetch('get_backup_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({ postId: postId, filename: filename })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Открываем в новом окне
            const newWindow = window.open('', '_blank');
            newWindow.document.write(data.content);
            newWindow.document.close();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка просмотра бэкапа:', error);
        showNotification('Ошибка при просмотре бэкапа', 'error');
    }
}

// Восстановление бэкапа
let restoreBackupData = null;

function restoreBackup(postId, filename, backupNumber, backupDate) {
    restoreBackupData = { postId, filename };
    
    const overlay = document.getElementById('restoreBackupOverlay');
    const infoDiv = document.getElementById('restoreBackupInfo');
    
    // Заполняем информацию о бэкапе
    infoDiv.innerHTML = `
        <div class="restore-backup-info-item">
            <span class="restore-backup-info-label">Статья:</span>
            <span class="restore-backup-info-value">#${postId}</span>
        </div>
        <div class="restore-backup-info-item">
            <span class="restore-backup-info-label">Бэкап:</span>
            <span class="restore-backup-info-value">#${backupNumber}</span>
        </div>
        <div class="restore-backup-info-item">
            <span class="restore-backup-info-label">Дата создания:</span>
            <span class="restore-backup-info-value">${backupDate}</span>
        </div>
    `;
    
    overlay.classList.add('show');
}

function closeRestoreBackup() {
    const overlay = document.getElementById('restoreBackupOverlay');
    overlay.classList.remove('show');
    restoreBackupData = null;
}

async function confirmRestoreBackup() {
    if (!restoreBackupData) return;
    
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Восстановление...';
    
    try {
        const response = await fetch('restore_backup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({
                postId: restoreBackupData.postId,
                filename: restoreBackupData.filename
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Бэкап успешно восстановлен', 'success');
            closeRestoreBackup();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
            btn.disabled = false;
            btn.textContent = 'Восстановить';
        }
    } catch (error) {
        console.error('Ошибка восстановления бэкапа:', error);
        showNotification('Ошибка при восстановлении бэкапа', 'error');
        btn.disabled = false;
        btn.textContent = 'Восстановить';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Удаление бэкапа
let deleteBackupData = null;

function openDeleteBackup(postId, filename, backupNumber) {
    deleteBackupData = { postId, filename, backupNumber };
    
    const overlay = document.getElementById('deleteBackupOverlay');
    const input = document.getElementById('deleteBackupConfirmInput');
    const btn = document.getElementById('confirmDeleteBackupBtn');
    
    input.value = '';
    btn.disabled = true;
    
    overlay.classList.add('show');
    
    setTimeout(() => input.focus(), 100);
}

function closeDeleteBackup() {
    const overlay = document.getElementById('deleteBackupOverlay');
    overlay.classList.remove('show');
    deleteBackupData = null;
}

// Проверка ввода для активации кнопки удаления
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('deleteBackupConfirmInput');
    const btn = document.getElementById('confirmDeleteBackupBtn');
    
    if (input && btn) {
        input.addEventListener('input', function() {
            btn.disabled = input.value.trim() !== 'УДАЛИТЬ';
        });
        
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && input.value.trim() === 'УДАЛИТЬ') {
                openFinalDeleteConfirm();
            }
        });
    }
    
    // Обработчик для сохранения состояния галочки "Вставить как гиперссылку"
    const insertAsHyperlinkCheckbox = document.getElementById('insertAsHyperlink');
    if (insertAsHyperlinkCheckbox) {
        insertAsHyperlinkCheckbox.addEventListener('change', function() {
            localStorage.setItem('insertAsHyperlink', this.checked);
        });
    }
    
    // Проверка чекбокса для финального подтверждения
    const checkbox = document.getElementById('finalDeleteCheckbox');
    const finalBtn = document.getElementById('finalDeleteBtn');
    
    if (checkbox && finalBtn) {
        checkbox.addEventListener('change', function() {
            finalBtn.disabled = !checkbox.checked;
        });
    }
    
    // Загружаем настройки автосохранения при загрузке страницы
    loadAutosaveSettings();
    
    // Применяем настройки внешнего вида
    applyAppearanceSettings();
    
    // Применяем экспериментальные настройки
    applyExperimentalSettings();
});

function openFinalDeleteConfirm() {
    // Закрываем первое окно
    const firstOverlay = document.getElementById('deleteBackupOverlay');
    firstOverlay.classList.remove('show');
    
    // Открываем финальное окно
    const finalOverlay = document.getElementById('finalDeleteOverlay');
    const checkbox = document.getElementById('finalDeleteCheckbox');
    const btn = document.getElementById('finalDeleteBtn');
    
    checkbox.checked = false;
    btn.disabled = true;
    
    finalOverlay.classList.add('show');
}

function closeFinalDelete() {
    const overlay = document.getElementById('finalDeleteOverlay');
    overlay.classList.remove('show');
    
    // Возвращаемся к первому окну
    const firstOverlay = document.getElementById('deleteBackupOverlay');
    firstOverlay.classList.add('show');
}

async function executeFinalDelete() {
    if (!deleteBackupData) return;
    
    const btn = document.getElementById('finalDeleteBtn');
    btn.disabled = true;
    btn.textContent = 'Удаление...';
    
    try {
        const response = await fetch('delete_backup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({
                postId: deleteBackupData.postId,
                filename: deleteBackupData.filename
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Бэкап успешно удален', 'success');
            
            // Закрываем финальное окно
            const finalOverlay = document.getElementById('finalDeleteOverlay');
            finalOverlay.classList.remove('show');
            
            // Закрываем первое окно
            closeDeleteBackup();
            
            // Перезагружаем список бэкапов
            openBackupManager();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
            btn.disabled = false;
            btn.textContent = 'УДАЛИТЬ НАВСЕГДА';
        }
    } catch (error) {
        console.error('Ошибка удаления бэкапа:', error);
        showNotification('Ошибка при удалении бэкапа', 'error');
        btn.disabled = false;
        btn.textContent = 'УДАЛИТЬ НАВСЕГДА';
    }
}

// ——— Система includes ———
function openSaveInclude() {
    const overlay = document.getElementById('saveIncludeOverlay');
    const input = document.getElementById('includeNameInput');
    input.value = '';
    overlay.classList.add('show');
    
    // Закрываем меню "Прочее"
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');
    
    setTimeout(() => input.focus(), 100);
}

function closeSaveInclude() {
    const overlay = document.getElementById('saveIncludeOverlay');
    overlay.classList.remove('show');
}

async function confirmSaveInclude() {
    const input = document.getElementById('includeNameInput');
    const name = input.value.trim();
    
    if (!name) {
        showNotification('Введите название файла', 'warning');
        return;
    }
    
    // Получаем контент из редактора
    const ve = document.getElementById('contentVisual');
    const ta = document.getElementById('content');
    let content;
    
    if (editorMode === 'visual') {
        content = ve.innerHTML;
    } else {
        content = ta.value;
    }
    
    if (!content.trim()) {
        showNotification('Нет контента для сохранения', 'warning');
        return;
    }
    
    // Блокируем кнопку
    const saveBtn = document.querySelector('.save-include-btn.save');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Сохранение...';
    }
    
    try {
        const response = await fetch('save_include.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({ name: name, content: content })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Include сохранен: ' + (data.displayName || data.filename), 'success');
            includesListLoaded = false; // Сбрасываем флаг для перезагрузки списка
            closeSaveInclude();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка сохранения include:', error);
        showNotification('Ошибка при сохранении include', 'error');
    } finally {
        // Разблокируем кнопку
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить';
        }
    }
}

let includesListLoaded = false;
let articlesListLoaded = false;
let draftsListLoaded = false;

// Функции для работы с черновиками
function saveDraft() {
    const title = document.getElementById('title').value.trim();
    const content = editorMode === 'visual' 
        ? document.getElementById('contentVisual').innerHTML 
        : document.getElementById('content').value;
    
    if (!title && !content) {
        showAlert('Нечего сохранять в черновик');
        return;
    }
    
    fetch('save_draft.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ title: title, content: content })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Черновик сохранен');
            draftsListLoaded = false; // Сбрасываем флаг чтобы перезагрузить список
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка сохранения черновика:', error);
        showAlert('Ошибка при сохранении черновика');
    });
    
    // Закрываем меню
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');
}

function toggleDraftsSubmenu(event) {
    event.stopPropagation();
    
    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');
    
    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });
    
    if (!isOpen) {
        button.classList.add('submenu-open');
        loadDraftsList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadDraftsList() {
    const submenu = document.getElementById('draftsSubmenu');
    if (!submenu) return;
    
    try {
        const response = await fetch('get_drafts.php');
        const data = await response.json();
        
        if (data.success) {
            if (data.drafts.length === 0) {
                submenu.innerHTML = '<div class="more-submenu-empty">Нет черновиков</div>';
            } else {
                submenu.innerHTML = data.drafts.map(draft => {
                    const displayTitle = draft.title || 'Без названия';
                    const date = new Date(draft.timestamp * 1000).toLocaleString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    return `<div class="draft-item-wrap">
                        <button type="button" class="more-submenu-item draft-load-btn" onclick="loadDraft('${draft.filename}')" title="${displayTitle}">
                            <div class="draft-title">${displayTitle}</div>
                            <div class="draft-date">${date}</div>
                        </button>
                        <button type="button" class="draft-delete-btn" onclick="deleteDraft('${draft.filename}', event)" title="Удалить черновик">×</button>
                    </div>`;
                }).join('');
            }
            draftsListLoaded = true;
        }
    } catch (error) {
        console.error('Ошибка загрузки черновиков:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">Ошибка загрузки</div>';
    }
}

async function loadDraft(filename) {
    try {
        const response = await fetch('get_drafts.php');
        const data = await response.json();
        
        if (data.success) {
            const draft = data.drafts.find(d => d.filename === filename);
            
            if (draft) {
                // Вставляем заголовок и контент
                document.getElementById('title').value = draft.title || '';
                
                if (editorMode === 'visual') {
                    document.getElementById('contentVisual').innerHTML = draft.content || '';
                } else {
                    document.getElementById('content').value = draft.content || '';
                }
                
                // Закрываем меню
                const moreMenu = document.getElementById('moreMenuWrap');
                if (moreMenu) moreMenu.classList.remove('is-open');
                
                showNotification('Черновик загружен', 'success');
            } else {
                showAlert('Черновик не найден');
            }
        } else {
            showAlert('Ошибка загрузки черновика');
        }
    } catch (error) {
        console.error('Ошибка загрузки черновика:', error);
        showAlert('Ошибка при загрузке черновика');
    }
}

async function deleteDraft(filename, event) {
    event.stopPropagation();
    
    const result = await showConfirm('Удалить этот черновик?');
    if (!result) return;
    
    try {
        const response = await fetch('delete_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ filename: filename })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Черновик удален', 'success');
            draftsListLoaded = false;
            loadDraftsList(); // Перезагружаем список
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    } catch (error) {
        console.error('Ошибка удаления черновика:', error);
        showAlert('Ошибка при удалении черновика');
    }
}

function toggleIncludesSubmenu(event) {
    event.stopPropagation();
    
    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');
    
    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });
    
    if (!isOpen) {
        button.classList.add('submenu-open');
        loadIncludesList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadIncludesList() {
    if (includesListLoaded) return;
    
    const submenu = document.getElementById('includesSubmenu');
    if (!submenu) return;
    
    try {
        const response = await fetch('get_includes.php');
        const data = await response.json();
        
        if (data.success) {
            if (data.files.length === 0) {
                submenu.innerHTML = '<div class="more-submenu-empty">Нет сохраненных includes</div>';
            } else {
                submenu.innerHTML = data.files.map(file => 
                    `<div class="draft-item-wrap">
                        <button type="button" class="more-submenu-item draft-load-btn" onclick="insertInclude('${file.name}')" title="${file.displayName}">${file.displayName}</button>
                        <button type="button" class="draft-delete-btn" onclick="deleteInclude('${file.name}', event)" title="Удалить include">×</button>
                    </div>`
                ).join('');
            }
            includesListLoaded = true;
        }
    } catch (error) {
        console.error('Ошибка загрузки includes:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">Ошибка загрузки</div>';
    }
}

async function deleteInclude(filename, event) {
    if (event) event.stopPropagation();
    
    const result = await showConfirm('Удалить этот include?');
    if (!result) return;
    
    try {
        const response = await fetch('delete_include.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({ filename: filename })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Include успешно удален', 'success');
            includesListLoaded = false;
            loadIncludesList();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка удаления include:', error);
        showNotification('Ошибка при удалении include', 'error');
    }
}

async function insertInclude(filename) {
    try {
        const response = await fetch('get_include_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ filename: filename })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const ve = document.getElementById('contentVisual');
            const ta = document.getElementById('content');
            
            if (editorMode === 'visual') {
                if (savedRange) {
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(savedRange);
                }
                insertHtmlAtCursor(data.content);
                saveSelection();
            } else {
                const start = ta.selectionStart;
                const end = ta.selectionEnd;
                const text = ta.value;
                ta.value = text.substring(0, start) + data.content + text.substring(end);
                ta.selectionStart = ta.selectionEnd = start + data.content.length;
            }
            
            // Закрываем меню
            const moreMenu = document.getElementById('moreMenuWrap');
            if (moreMenu) moreMenu.classList.remove('is-open');
            
            showNotification('Include вставлен', 'success');
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка вставки include:', error);
        showNotification('Ошибка при вставке include', 'error');
    }
}

// Функции для вставки ссылок на статьи
function toggleArticlesSubmenu(event) {
    event.stopPropagation();
    
    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');
    
    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });
    
    if (!isOpen) {
        button.classList.add('submenu-open');
        loadArticlesList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadArticlesList() {
    const submenu = document.getElementById('articlesSubmenu');
    if (!submenu) return;
    
    try {
        const response = await fetch('data/blog/posts-meta.json?t=' + Date.now());
        const articles = await response.json();
        
        if (articles.length === 0) {
            submenu.innerHTML = '<div class="more-submenu-empty">Нет статей</div>';
        } else {
            submenu.innerHTML = articles.map(article => 
                `<button type="button" class="more-submenu-item" onclick="insertArticleLink('${article.filename}', '${article.title.replace(/'/g, "\\'")}')">
                    ${article.title}
                </button>`
            ).join('');
        }
    } catch (error) {
        console.error('Ошибка загрузки статей:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">Ошибка загрузки</div>';
    }
}

function insertArticleLink(filename, title) {
    const ve = document.getElementById('contentVisual');
    const ta = document.getElementById('content');
    
    const linkHtml = `<a href="${filename}">${title}</a>`;
    
    if (editorMode === 'visual') {
        if (savedRange) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
        insertHtmlAtCursor(linkHtml);
        saveSelection();
    } else {
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const text = ta.value;
        ta.value = text.substring(0, start) + linkHtml + text.substring(end);
        ta.selectionStart = ta.selectionEnd = start + linkHtml.length;
    }
    
    // Закрываем меню
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');
    
    showNotification('Ссылка на статью вставлена', 'success');
}

// ——— Проверка нумерации статей ———
async function checkPostNumbering() {
    const overlay = document.getElementById('numberingCheckOverlay');
    const content = document.getElementById('numberingCheckContent');
    const fixBtn = document.getElementById('fixNumberingBtn');
    
    overlay.classList.add('show');
    content.innerHTML = '<div class="numbering-status">Проверка нумерации...</div>';
    fixBtn.style.display = 'none';
    
    try {
        const response = await fetch('renumber_posts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'check' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.needsFix) {
                let issuesHtml = '<div class="numbering-status warning">';
                issuesHtml += '<strong>⚠ Обнаружены проблемы с нумерацией!</strong><br><br>';
                issuesHtml += 'Следующие статьи имеют неправильную нумерацию:';
                issuesHtml += '<div class="numbering-issues-list">';
                
                data.issues.forEach(issue => {
                    issuesHtml += `
                        <div class="numbering-issue-item">
                            <div class="numbering-issue-title">${issue.title}</div>
                            <div class="numbering-issue-detail">
                                Текущий номер: ${issue.currentId} → Должен быть: ${issue.expectedId}
                            </div>
                        </div>
                    `;
                });
                
                issuesHtml += '</div></div>';
                content.innerHTML = issuesHtml;
                fixBtn.style.display = 'block';
            } else {
                content.innerHTML = `
                    <div class="numbering-status success">
                        <strong>✓ Нумерация корректна!</strong><br><br>
                        Все статьи пронумерованы правильно. Исправление не требуется.
                    </div>
                `;
                fixBtn.style.display = 'none';
            }
        } else {
            content.innerHTML = `
                <div class="numbering-status warning">
                    <strong>Ошибка проверки</strong><br><br>
                    ${data.error || 'Не удалось выполнить проверку'}
                </div>
            `;
            fixBtn.style.display = 'none';
        }
    } catch (error) {
        console.error('Ошибка проверки нумерации:', error);
        content.innerHTML = `
            <div class="numbering-status warning">
                <strong>Ошибка проверки</strong><br><br>
                Не удалось выполнить проверку нумерации
            </div>
        `;
        fixBtn.style.display = 'none';
    }
}

async function fixNumbering() {
    const content = document.getElementById('numberingCheckContent');
    const fixBtn = document.getElementById('fixNumberingBtn');
    
    content.innerHTML = '<div class="numbering-status">Исправление нумерации...</div>';
    fixBtn.disabled = true;
    
    try {
        const response = await fetch('renumber_posts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'fix' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.changes && data.changes.length > 0) {
                let changesHtml = '<div class="numbering-status success">';
                changesHtml += '<strong>✓ Нумерация исправлена!</strong><br><br>';
                changesHtml += 'Выполнены следующие изменения:';
                changesHtml += '<div class="numbering-issues-list">';
                
                data.changes.forEach(change => {
                    changesHtml += `
                        <div class="numbering-issue-item">
                            <div class="numbering-issue-title">${change.title}</div>
                            <div class="numbering-issue-detail">
                                Статья №${change.oldId} → Статья №${change.newId}
                            </div>
                        </div>
                    `;
                });
                
                changesHtml += '</div></div>';
                content.innerHTML = changesHtml;
                
                showNotification('Нумерация исправлена', 'success');
                
                // Обновляем список статей если он открыт
                if (document.getElementById('managePosts').classList.contains('active')) {
                    loadPosts();
                }
            } else {
                content.innerHTML = `
                    <div class="numbering-status success">
                        <strong>✓ ${data.message}</strong><br><br>
                        Изменения не требуются.
                    </div>
                `;
            }
            
            fixBtn.style.display = 'none';
        } else {
            content.innerHTML = `
                <div class="numbering-status warning">
                    <strong>Ошибка исправления</strong><br><br>
                    ${data.error || 'Не удалось выполнить исправление'}
                </div>
            `;
            fixBtn.disabled = false;
        }
    } catch (error) {
        console.error('Ошибка исправления нумерации:', error);
        content.innerHTML = `
            <div class="numbering-status warning">
                <strong>Ошибка исправления</strong><br><br>
                Не удалось выполнить исправление нумерации
            </div>
        `;
        fixBtn.disabled = false;
    }
}

function closeNumberingCheck() {
    const overlay = document.getElementById('numberingCheckOverlay');
    overlay.classList.remove('show');
}

// ——— Гайд для первого запуска ———
const tutorialSteps = [
    {
        title: "👋 Добро пожаловать в NPBlog!",
        text: "Это гайд по основам работы в NPBlog.",
        element: null
    },
    {
        title: "📝 Поле для заголовка",
        text: "Сюда вводится заголовок вашей статьи. Он будет жирным шрифтом отображаться в общем списке постов и на самой странице.",
        element: "#title"
    },
    {
        title: "✏️ Главное окно редактора",
        text: "Это основная рабочая область. Вы можете просто писать текст, а также вставлять картинки и другие медиафайлы прямо сюда.",
        element: "#contentVisual"
    },
    {
        title: "👁 Режимы работы",
        text: "Вы можете переключаться между удобным «Визуальным» режимом (как в Word) и «Режимом кода», если вам нужно вручную подправить HTML-теги.",
        element: ".mode-toggle"
    },
    {
        title: "🔙 Отмена, Возврат и Сохранение",
        text: "Когда статья готова — жмите «Сохранить»!",
        element: ".editor-actions"
    },
    {
        title: "🎨 Базовое форматирование",
        text: "Здесь находятся стандартные инструменты: жирный шрифт, курсив, зачеркивание, подзаголовки, а также вставка таблиц и спойлеров.",
        element: "#formatBarRow > .toolbar-group:nth-child(1)"
    },
    {
        title: "📐 Выравнивание текста",
        text: "Эти кнопки позволяют выровнять текущий абзац, таблицу или картинку по левому краю, по центру или по правому краю.",
        element: "#formatBarRow > .toolbar-group:nth-child(3)"
    },
    {
        title: "🖼 Вставка ссылок и медиа",
        text: "Отсюда можно добавить гиперссылку, загрузить изображение с компьютера или вставить аудио/видео файлы.",
        element: "#formatBarRow > .toolbar-group:nth-child(5)"
    },
    {
        title: "🔤 Шрифты и Цвета",
        text: "Настройте размер шрифта, выберите гарнитуру (или загрузите свою!) и измените цвет текста, используя удобную палитру.",
        element: "#formatBarRow > .toolbar-group:nth-child(7)"
    },
    {
        title: "⋯ Дополнительное меню",
        text: "Под тремя точками скрыты важные функции: сохранение в черновик, менеджер файлов и добавление перекрестных ссылок на другие ваши статьи.",
        element: "#moreMenuWrap"
    },
    {
        title: "☰ Главное меню (Настройки)",
        text: "Важный раздел! Здесь находятся Управление статьями, Глобальные параметры (например, фоны), Менеджер бэкапов и смена темы.",
        element: "#editorMenuBtn"
    },
    {
        title: "💡 Контекстное меню",
        text: "Секретный совет: если кликнуть правой кнопкой мыши внутри редактора, откроется меню с быстрыми действиями (включая работу с таблицами).",
        element: "#contentVisual"
    },
    {
        title: "🎉 Вы готовы!",
        text: "Теперь вы знаете, где что находится. Если забудете — вы всегда можете заново запустить это обучение из Главного меню.",
        element: null
    }
];

let currentTutorialStep = 0;

function startTutorial() {
    fetch('get_editor_settings.php?t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            const settings = data.settings || {};
            if (settings.tutorialCompleted) return;
            
            currentTutorialStep = 0;
            showTutorialStep();
        })
        .catch(err => {
            console.error('Ошибка проверки настроек обучения:', err);
        });
}

function showTutorialStep() {
    const overlay = document.getElementById('tutorialOverlay');
    const tooltip = document.getElementById('tutorialTooltip');
    const complete = document.getElementById('tutorialComplete');
    const spotlight = document.getElementById('tutorialSpotlight');
    
    overlay.classList.add('show');
    tooltip.style.display = 'block';
    complete.style.display = 'none';
    
    const step = tutorialSteps[currentTutorialStep];
    
    // Обновляем контент
    document.getElementById('tutorialTitle').textContent = step.title;
    document.getElementById('tutorialText').textContent = step.text;
    
    // Обновляем прогресс
    const progressContainer = document.getElementById('tutorialProgress');
    progressContainer.innerHTML = '';
    tutorialSteps.forEach((_, index) => {
        const dot = document.createElement('div');
        dot.className = 'tutorial-progress-dot';
        if (index === currentTutorialStep) dot.classList.add('active');
        progressContainer.appendChild(dot);
    });
    
    // Сбрасываем стили
    tooltip.style.transform = '';
    
    // Позиционируем spotlight и tooltip
    if (step.element) {
        const element = document.querySelector(step.element);
        if (element) {
            const rect = element.getBoundingClientRect();
            const scrollY = window.scrollY || window.pageYOffset;
            const scrollX = window.scrollX || window.pageXOffset;
            
            spotlight.style.display = 'block';
            spotlight.style.top = (rect.top + scrollY - 8) + 'px';
            spotlight.style.left = (rect.left + scrollX - 8) + 'px';
            spotlight.style.width = (rect.width + 16) + 'px';
            spotlight.style.height = (rect.height + 16) + 'px';
            
            // Позиционируем tooltip
            tooltip.style.position = 'fixed';
            const tooltipRect = tooltip.getBoundingClientRect();
            const padding = 20;
            
            // Пробуем разместить снизу
            let tooltipTop = rect.bottom + padding;
            let tooltipLeft = rect.left;
            
            // Если не помещается снизу, размещаем сверху
            if (tooltipTop + tooltipRect.height > window.innerHeight - padding) {
                tooltipTop = rect.top - tooltipRect.height - padding;
            }
            
            // Если не помещается сверху, размещаем справа
            if (tooltipTop < padding) {
                tooltipTop = rect.top;
                tooltipLeft = rect.right + padding;
            }
            
            // Если не помещается справа, размещаем слева
            if (tooltipLeft + tooltipRect.width > window.innerWidth - padding) {
                tooltipLeft = rect.left - tooltipRect.width - padding;
            }
            
            // Проверяем границы по горизонтали
            if (tooltipLeft < padding) {
                tooltipLeft = padding;
            }
            if (tooltipLeft + tooltipRect.width > window.innerWidth - padding) {
                tooltipLeft = window.innerWidth - tooltipRect.width - padding;
            }
            
            // Проверяем границы по вертикали
            if (tooltipTop < padding) {
                tooltipTop = padding;
            }
            if (tooltipTop + tooltipRect.height > window.innerHeight - padding) {
                tooltipTop = window.innerHeight - tooltipRect.height - padding;
            }
            
            tooltip.style.top = tooltipTop + 'px';
            tooltip.style.left = tooltipLeft + 'px';
        }
    } else {
        spotlight.style.display = 'none';
        // Центрируем tooltip
        tooltip.style.position = 'fixed';
        tooltip.style.top = '50%';
        tooltip.style.left = '50%';
        tooltip.style.transform = 'translate(-50%, -50%)';
    }
}

function nextTutorialStep() {
    currentTutorialStep++;
    if (currentTutorialStep >= tutorialSteps.length) {
        showTutorialComplete();
    } else {
        showTutorialStep();
    }
}

function skipTutorial() {
    showConfirm('Вы уверены, что хотите пропустить обучение?').then(result => {
        if (!result) return;
        completeTutorial();
    });
}

function showTutorialComplete() {
    const tooltip = document.getElementById('tutorialTooltip');
    const complete = document.getElementById('tutorialComplete');
    const spotlight = document.getElementById('tutorialSpotlight');
    
    tooltip.style.display = 'none';
    spotlight.style.display = 'none';
    complete.style.display = 'block';
}

function completeTutorial() {
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tutorialCompleted: true })
    });
    const overlay = document.getElementById('tutorialOverlay');
    overlay.classList.remove('show');
}

function resetTutorial() {
    showConfirm('Вы уверены, что хотите сбросить обучение? Гайд появится снова при следующей загрузке страницы.').then(result => {
        if (!result) return;
        fetch('save_editor_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tutorialCompleted: false })
        }).then(() => {
            showNotification('Обучение сброшено. Перезагрузите страницу для запуска гайда.', 'success');
        });
    });
}

// Запускаем гайд при загрузке страницы
window.addEventListener('load', function() {
    setTimeout(startTutorial, 500);
});

// ——— Функции для загрузки файлов ———

function openFileUploadDialog() {
    // Сохраняем текущую позицию курсора
    if (typeof saveSelection === 'function') {
        saveSelection();
    }
    document.getElementById('fileUploadDialog').style.display = 'block';
    
    // Загружаем сохраненное состояние галочки из localStorage
    const savedState = localStorage.getItem('insertAsHyperlink');
    if (savedState !== null) {
        document.getElementById('insertAsHyperlink').checked = savedState === 'true';
    }
    
    loadDocumentsList();
    closeMoreMenu();
}

function closeFileUploadDialog() {
    document.getElementById('fileUploadDialog').style.display = 'none';
    // Не сбрасываем галочку, чтобы сохранить состояние
}

function closeMoreMenu() {
    var dropdown = document.getElementById('moreMenuDropdown');
    if (dropdown) {
        dropdown.classList.remove('show');
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Б';
    const k = 1024;
    const sizes = ['Б', 'КБ', 'МБ', 'ГБ'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function loadDocumentsList() {
    fetch('get_documents.php')
        .then(response => response.json())
        .then(data => {
            const listContainer = document.getElementById('fileUploadList');
            
            if (data.success && data.files.length > 0) {
                listContainer.innerHTML = '';
                data.files.forEach(file => {
                    const item = document.createElement('div');
                    item.className = 'file-upload-item';
                    
                    const info = document.createElement('div');
                    info.className = 'file-upload-item-info';
                    info.onclick = () => insertFileButton(file.name, file.path, file.size);
                    
                    const nameDiv = document.createElement('div');
                    nameDiv.style.cssText = 'display: flex; flex-direction: column; gap: 2px;';
                    
                    const name = document.createElement('div');
                    name.className = 'file-upload-item-name';
                    name.textContent = file.name;
                    
                    const size = document.createElement('div');
                    size.className = 'file-upload-item-size';
                    size.textContent = formatFileSize(file.size);
                    
                    nameDiv.appendChild(name);
                    nameDiv.appendChild(size);
                    info.appendChild(nameDiv);
                    
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'file-upload-item-delete';
                    deleteBtn.textContent = 'Удалить';
                    deleteBtn.onclick = (e) => {
                        e.stopPropagation();
                        deleteDocument(file.path);
                    };
                    
                    item.appendChild(info);
                    item.appendChild(deleteBtn);
                    listContainer.appendChild(item);
                });
            } else {
                listContainer.innerHTML = '<div class="file-upload-empty">Нет загруженных файлов</div>';
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки списка файлов:', error);
            document.getElementById('fileUploadList').innerHTML = '<div class="file-upload-empty">Ошибка загрузки списка</div>';
        });
}

function uploadDocument() {
    const fileInput = document.getElementById('documentFile');
    const file = fileInput.files[0];
    
    if (!file) {
        showNotification('Выберите файл для загрузки', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    
    fetch('upload_document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Файл успешно загружен', 'success');
            fileInput.value = '';
            loadDocumentsList();
        } else {
            showNotification('Ошибка загрузки: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showNotification('Ошибка загрузки файла', 'error');
    });
}

function deleteDocument(filePath) {
    if (!confirm('Удалить этот файл?')) {
        return;
    }
    
    fetch('delete_document.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ filePath: filePath })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Файл удален', 'success');
            loadDocumentsList();
        } else {
            showNotification('Ошибка удаления: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showNotification('Ошибка удаления файла', 'error');
    });
}

function insertFileButton(fileName, filePath, fileSize) {
    const ve = document.getElementById('contentVisual');
    ve.focus();
    
    // Преобразуем путь к файлу, добавляя / в начало если его нет
    if (!filePath.startsWith('/')) {
        filePath = '/' + filePath;
    }
    
    // Проверяем, нужно ли вставить как гиперссылку
    const insertAsHyperlink = document.getElementById('insertAsHyperlink').checked;
    
    let elementToInsert;
    
    if (insertAsHyperlink) {
        // Вставляем как простую гиперссылку
        const link = document.createElement('a');
        link.href = filePath;
        link.textContent = fileName;
        link.target = '_blank';
        link.setAttribute('download', fileName);
        elementToInsert = link;
    } else {
        // Создаем стандартную структуру медиа-обертки для поддержки оверлея
        const alignWrap = document.createElement('div');
        alignWrap.className = 'blog-image-align-wrap';
        alignWrap.style.textAlign = 'left'; // По умолчанию слева
        
        const mediaWrap = document.createElement('div');
        mediaWrap.className = 'blog-image-wrap';
        mediaWrap.style.display = 'inline-block';
        
        const fileButton = document.createElement('a');
        fileButton.href = filePath;
        fileButton.className = 'blog-file-button';
        fileButton.target = '_blank';
        fileButton.setAttribute('download', fileName);
        fileButton.contentEditable = 'false';
        fileButton.style.setProperty('font-family', 'Arial, sans-serif', 'important');
        fileButton.style.setProperty('-webkit-font-smoothing', 'antialiased', 'important');
        fileButton.style.setProperty('-moz-osx-font-smoothing', 'grayscale', 'important');
        fileButton.style.setProperty('text-rendering', 'optimizeLegibility', 'important');
        
        const icon = document.createElement('div');
        icon.className = 'blog-file-icon';
        icon.textContent = '📥';
        
        const info = document.createElement('div');
        info.className = 'blog-file-info';
        
        const name = document.createElement('div');
        name.className = 'blog-file-name';
        name.textContent = fileName;
        
        const size = document.createElement('div');
        size.className = 'blog-file-size';
        size.textContent = formatFileSize(fileSize);
        
        info.appendChild(name);
        info.appendChild(size);
        fileButton.appendChild(icon);
        fileButton.appendChild(info);
        
        mediaWrap.appendChild(fileButton);
        alignWrap.appendChild(mediaWrap);
        
        elementToInsert = alignWrap;
    }
    
    // Создаем пустой блок для курсора после элемента
    const emptyDiv = document.createElement('div');
    emptyDiv.innerHTML = '<br>';
    
    // Вставляем в редактор
    const sel = window.getSelection();
    let range = null;
    
    // Используем savedRange если он есть
    if (typeof savedRange !== 'undefined' && savedRange && ve.contains(savedRange.commonAncestorContainer)) {
        range = savedRange;
    } else if (sel && sel.rangeCount > 0) {
        range = sel.getRangeAt(0);
    }
    
    if (!range) {
        // Если нет range, добавляем в конец
        ve.appendChild(elementToInsert);
        if (!insertAsHyperlink) {
            ve.appendChild(emptyDiv);
        }
        range = document.createRange();
        range.setStart(insertAsHyperlink ? elementToInsert : emptyDiv, 0);
        range.collapse(true);
        if (sel) {
            sel.removeAllRanges();
            sel.addRange(range);
        }
        if (typeof savedRange !== 'undefined') {
            savedRange = range.cloneRange();
        }
    } else {
        // Удаляем выделенный контент
        range.deleteContents();
        
        // Вставляем элемент
        range.insertNode(elementToInsert);
        
        if (!insertAsHyperlink) {
            // Вставляем пустой блок после кнопки
            const parent = elementToInsert.parentNode;
            parent.insertBefore(emptyDiv, elementToInsert.nextSibling);
            
            // Устанавливаем курсор в пустой блок
            range.setStart(emptyDiv, 0);
        } else {
            // Для гиперссылки ставим курсор после неё
            range.setStartAfter(elementToInsert);
        }
        
        range.collapse(true);
        if (sel) {
            sel.removeAllRanges();
            sel.addRange(range);
        }
        if (typeof savedRange !== 'undefined') {
            savedRange = range.cloneRange();
        }
    }
    
    saveToHistory();
    closeFileUploadDialog();
    showNotification('Файл добавлен в статью', 'success');
}

// ——— Работа с якорями и содержанием ———
function addAnchor() {
    if (editorMode !== 'visual') {
        showNotification('Якоря можно добавлять только в визуальном режиме', 'warning');
        return;
    }
    
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) {
        showNotification('Пожалуйста, выделите текст для якоря', 'warning');
        return;
    }
    
    const range = sel.getRangeAt(0);
    
    // Проверяем, находится ли выделение уже внутри существующего якоря
    let anchorSpan = null;
    let startNode = range.startContainer;
    if (startNode.nodeType === Node.TEXT_NODE) {
        startNode = startNode.parentNode;
    }
    anchorSpan = startNode.closest('span[data-npblog-anchor="true"]');
    
    if (!anchorSpan) {
        let endNode = range.endContainer;
        if (endNode.nodeType === Node.TEXT_NODE) {
            endNode = endNode.parentNode;
        }
        anchorSpan = endNode.closest('span[data-npblog-anchor="true"]');
    }
    
    // Если якорь найден, убираем его (unwrap)
    if (anchorSpan) {
        const parent = anchorSpan.parentNode;
        if (parent) {
            const hasOnlyIcon = anchorSpan.innerText.trim() === '⚓' || anchorSpan.textContent.trim() === '⚓';
            if (hasOnlyIcon) {
                parent.removeChild(anchorSpan);
            } else {
                const fragment = document.createDocumentFragment();
                while (anchorSpan.firstChild) {
                    fragment.appendChild(anchorSpan.firstChild);
                }
                parent.replaceChild(fragment, anchorSpan);
            }
            saveToHistory();
            showNotification('Якорь удален', 'info');
            return;
        }
    }
    
    // Автоматически определяем следующий числовой ID
    const ve = document.getElementById('contentVisual');
    if (!ve) return;
    
    let nextId = 1;
    while (ve.querySelector('[id="' + nextId + '"]')) {
        nextId++;
    }
    const anchorId = String(nextId);
    
    const span = document.createElement('span');
    span.id = anchorId;
    span.setAttribute('data-npblog-anchor', 'true');
    
    if (range.collapsed) {
        span.innerHTML = '⚓'; // Если текст не выделен, вставляем иконку
        range.insertNode(span);
    } else {
        try {
            const contents = range.extractContents();
            span.appendChild(contents);
            range.insertNode(span);
        } catch (e) {
            console.error("Ошибка при создании якоря:", e);
            showNotification('Не удалось создать якорь в этом месте', 'error');
            return;
        }
    }
    
    // Выделяем добавленный якорь
    const newRange = document.createRange();
    newRange.selectNodeContents(span);
    sel.removeAllRanges();
    sel.addRange(newRange);
    
    saveToHistory();
    showNotification(`Якорь #${anchorId} успешно добавлен`, 'success');
}

function toggleTocSubmenu(event) {
    event.stopPropagation();
    
    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');
    
    // Закрываем другие подменю
    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });
    
    if (!isOpen) {
        button.classList.add('submenu-open');
        loadTocList();
    } else {
        button.classList.remove('submenu-open');
    }
}

function loadTocList() {
    const submenu = document.getElementById('tocSubmenu');
    if (!submenu) return;
    
    const ve = document.getElementById('contentVisual');
    if (!ve) return;
    
    // Ищем все элементы с ID
    const anchors = ve.querySelectorAll('[id]');
    
    if (anchors.length === 0) {
        submenu.innerHTML = '<div class="more-submenu-empty">Нет якорей в статье</div>';
        return;
    }
    
    let html = '';
    anchors.forEach(el => {
        const id = el.id;
        if (!id) return;
        
        let text = el.innerText.trim();
        // Убираем иконку якоря ⚓ из текста пункта меню, если она там есть
        if (text.startsWith('⚓')) {
            text = text.substring(1).trim();
        }
        
        if (!text) {
            text = `Якорь: #${id}`;
        } else {
            if (text.length > 25) {
                text = text.substring(0, 22) + '...';
            }
            text = `${text} (#${id})`;
        }
        
        html += `
        <div class="toc-menu-item-row">
            <button type="button" class="more-submenu-item" onclick="insertAnchorLink('${id}')" title="Вставить ссылку на #${id}">${text}</button>
            <button type="button" class="toc-delete-btn" onclick="removeAnchorById('${id}', event)" title="Удалить якорь #${id}">×</button>
        </div>`;
    });
    
    submenu.innerHTML = html;
}

function removeAnchorById(id, event) {
    if (event) {
        event.stopPropagation();
    }
    
    if (editorMode !== 'visual') {
        showNotification('Якоря можно удалять только в визуальном режиме', 'warning');
        return;
    }
    
    const ve = document.getElementById('contentVisual');
    if (!ve) return;
    
    const anchorSpan = ve.querySelector('[id="' + id + '"]');
    if (anchorSpan) {
        const parent = anchorSpan.parentNode;
        if (parent) {
            const hasOnlyIcon = anchorSpan.innerText.trim() === '⚓' || anchorSpan.textContent.trim() === '⚓';
            if (hasOnlyIcon) {
                parent.removeChild(anchorSpan);
            } else {
                const fragment = document.createDocumentFragment();
                while (anchorSpan.firstChild) {
                    fragment.appendChild(anchorSpan.firstChild);
                }
                parent.replaceChild(fragment, anchorSpan);
            }
            saveToHistory();
            showNotification(`Якорь #${id} удален`, 'info');
            loadTocList(); // Обновляем список сразу
        }
    }
}

function insertAnchorLink(id) {
    if (editorMode !== 'visual') {
        showNotification('Ссылки на якоря можно вставлять только в визуальном режиме', 'warning');
        return;
    }
    
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const range = sel.getRangeAt(0);
    
    let text = sel.toString().trim();
    if (!text) {
        const ve = document.getElementById('contentVisual');
        const anchorEl = ve ? ve.querySelector('[id="' + id + '"]') : null;
        let anchorText = "";
        if (anchorEl) {
            anchorText = anchorEl.innerText.trim();
            if (anchorText.startsWith('⚓')) {
                anchorText = anchorText.substring(1).trim();
            }
        }
        
        if (!anchorText) {
            anchorText = "Перейти к разделу";
        }
        
        text = prompt("Введите текст для ссылки-якоря:", anchorText);
        if (text === null) return; // Отмена
        if (!text) text = anchorText;
    }
    
    const link = document.createElement('a');
    link.href = '#' + id;
    link.innerText = text;
    
    range.deleteContents();
    range.insertNode(link);
    
    // Ставим курсор после вставленной ссылки
    const newRange = document.createRange();
    newRange.setStartAfter(link);
    newRange.collapse(true);
    sel.removeAllRanges();
    sel.addRange(newRange);
    
    saveToHistory();
    
    // Закрываем выпадающие меню
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');
    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        btn.classList.remove('submenu-open');
    });
    
    showNotification('Ссылка на якорь вставлена', 'success');
}

// Экспортируем в window для inline-событий
window.addAnchor = addAnchor;
window.toggleTocSubmenu = toggleTocSubmenu;
window.loadTocList = loadTocList;
window.insertAnchorLink = insertAnchorLink;
window.removeAnchorById = removeAnchorById;
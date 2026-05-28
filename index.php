<?php
$settingsFile = 'editor_settings.json';
$amoled = false;
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!empty($settings['amoledTheme'])) {
        $amoled = true;
    }
}
?>
<!DOCTYPE html>
<html<?php echo $amoled ? ' data-amoled="true"' : ''; ?>>
<head>
    <title>Редактор</title>
    <meta charset="utf-8">
    <script>if(localStorage.getItem('theme') === 'dark') document.documentElement.setAttribute('data-theme', 'dark');</script>
    <link rel="stylesheet" href="editor-style.css?v=1779014530">
</head>
<body>
    
    <!-- Контейнер для уведомлений -->
    <div class="notification-container" id="notificationContainer"></div>
    
    <!-- Диалог подтверждения удаления -->
    <div class="delete-confirm-overlay" id="deleteConfirmOverlay">
        <div class="delete-confirm-dialog">
            <div class="delete-confirm-header">
                <div class="delete-confirm-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                    </svg>
                </div>
                <h2 class="delete-confirm-title">Удалить статью?</h2>
            </div>
            <div class="delete-confirm-message">
                Вы уверены, что хотите удалить эту статью? Это действие нельзя отменить.
            </div>
            <div class="delete-confirm-buttons">
                <button class="delete-confirm-btn cancel" onclick="closeDeleteConfirm()">Отмена</button>
                <button class="delete-confirm-btn delete" onclick="confirmDelete()">Удалить</button>
            </div>
        </div>
    </div>

    <!-- Диалог сохранения в includes -->
    <div class="save-include-overlay" id="saveIncludeOverlay">
        <div class="save-include-dialog">
            <h2 class="save-include-title">Сохранить в includes</h2>
            <label class="save-include-label">Название файла:</label>
            <input type="text" class="save-include-input" id="includeNameInput" placeholder="Например: контакты">
            <div class="save-include-buttons">
                <button class="save-include-btn cancel" onclick="closeSaveInclude()">Отмена</button>
                <button class="save-include-btn save" onclick="confirmSaveInclude()">Сохранить</button>
            </div>
        </div>
    </div>

    <!-- Менеджер бэкапов -->
    <div class="backup-manager-overlay" id="backupManagerOverlay">
        <div class="backup-manager-dialog">
            <div class="backup-manager-header">
                <h2 class="backup-manager-title">Менеджер бэкапов</h2>
                <button class="backup-manager-close" onclick="closeBackupManager()">×</button>
            </div>
            <div class="backup-manager-content" id="backupManagerContent">
                <div class="backup-empty">Загрузка...</div>
            </div>
        </div>
    </div>

    <!-- Диалог подтверждения удаления бэкапа -->
    <div class="delete-backup-overlay" id="deleteBackupOverlay">
        <div class="delete-backup-dialog">
            <div class="delete-backup-header">
                <div class="delete-backup-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L1 21h22L12 2zm0 3.5L19.5 19h-15L12 5.5zM11 10v4h2v-4h-2zm0 5v2h2v-2h-2z"/>
                    </svg>
                </div>
                <h2 class="delete-backup-title">Удалить бэкап?</h2>
            </div>
            <div class="delete-backup-message">
                Вы собираетесь удалить бэкап. Это действие необратимо.
            </div>
            <div class="delete-backup-warning">
                ⚠ Внимание! После удаления восстановить бэкап будет невозможно.
            </div>
            <div class="delete-backup-confirm-text">
                Для подтверждения введите слово <strong>УДАЛИТЬ</strong>:
            </div>
            <input type="text" class="delete-backup-input" id="deleteBackupConfirmInput" placeholder="УДАЛИТЬ">
            <div class="delete-backup-buttons">
                <button class="delete-backup-btn cancel" onclick="closeDeleteBackup()">Отмена</button>
                <button class="delete-backup-btn delete" id="confirmDeleteBackupBtn" disabled onclick="openFinalDeleteConfirm()">Удалить бэкап</button>
            </div>
        </div>
    </div>

    <!-- Финальное подтверждение удаления -->
    <div class="final-delete-overlay" id="finalDeleteOverlay">
        <div class="final-delete-dialog">
            <h2 class="final-delete-title">⚠ ПОСЛЕДНЕЕ ПРЕДУПРЕЖДЕНИЕ ⚠</h2>
            <div class="final-delete-message">
                Вы действительно хотите безвозвратно удалить этот бэкап?
            </div>
            <div class="final-delete-checkbox-wrap">
                <label class="final-delete-checkbox-label">
                    <input type="checkbox" class="final-delete-checkbox" id="finalDeleteCheckbox">
                    <span class="final-delete-checkbox-text">
                        Я осознаю все риски и согласен с безвозвратным удалением этого бэкапа. Я понимаю, что восстановить его будет невозможно.
                    </span>
                </label>
            </div>
            <div class="final-delete-buttons">
                <button class="final-delete-btn cancel" onclick="closeFinalDelete()">Отмена</button>
                <button class="final-delete-btn confirm" id="finalDeleteBtn" disabled onclick="executeFinalDelete()">УДАЛИТЬ НАВСЕГДА</button>
            </div>
        </div>
    </div>

    <!-- Диалог подтверждения восстановления -->
    <div class="restore-backup-overlay" id="restoreBackupOverlay">
        <div class="restore-backup-dialog">
            <div class="restore-backup-header">
                <div class="restore-backup-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>
                    </svg>
                </div>
                <h2 class="restore-backup-title">Восстановить бэкап?</h2>
            </div>
            <div class="restore-backup-message">
                Вы собираетесь восстановить статью из бэкапа. Текущая версия статьи будет заменена.
            </div>
            <div class="restore-backup-info" id="restoreBackupInfo">
                <!-- Информация о бэкапе будет добавлена через JS -->
            </div>
            <div class="restore-backup-warning">
                ⚠ Внимание! Текущая версия статьи будет перезаписана содержимым из бэкапа.
            </div>
            <div class="restore-backup-buttons">
                <button class="restore-backup-btn cancel" onclick="closeRestoreBackup()">Отмена</button>
                <button class="restore-backup-btn restore" onclick="confirmRestoreBackup()">Восстановить</button>
            </div>
        </div>
    </div>

    <!-- Диалог проверки нумерации -->
    <div class="numbering-check-overlay" id="numberingCheckOverlay">
        <div class="numbering-check-dialog">
            <div class="numbering-check-header">
                <h2 class="numbering-check-title">Проверка нумерации статей</h2>
                <button class="numbering-check-close" onclick="closeNumberingCheck()">×</button>
            </div>
            <div class="numbering-check-content" id="numberingCheckContent">
                <div class="numbering-status">Проверка...</div>
            </div>
            <div class="numbering-check-buttons">
                <button class="numbering-check-btn close" onclick="closeNumberingCheck()">Закрыть</button>
                <button class="numbering-check-btn fix" id="fixNumberingBtn" style="display:none;" onclick="fixNumbering()">Исправить</button>
            </div>
        </div>
    </div>

    <!-- Гайд для первого запуска -->
    <div class="tutorial-overlay" id="tutorialOverlay">
        <div class="tutorial-spotlight" id="tutorialSpotlight"></div>
        <div class="tutorial-tooltip" id="tutorialTooltip">
            <div class="tutorial-progress" id="tutorialProgress"></div>
            <h3 id="tutorialTitle"></h3>
            <p id="tutorialText"></p>
            <div class="tutorial-buttons">
                <button class="tutorial-btn skip" onclick="skipTutorial()">Пропустить</button>
                <button class="tutorial-btn next" onclick="nextTutorialStep()">Далее</button>
            </div>
        </div>
        <div class="tutorial-complete-dialog" id="tutorialComplete" style="display:none;">
            <div class="tutorial-complete-icon">🎉</div>
            <h2>Обучение завершено!</h2>
            <p>Теперь вы знаете основы работы с редактором NPBlog. Приятного использования!</p>
            <button class="tutorial-complete-btn" onclick="completeTutorial()">OK</button>
        </div>
    </div>

    <!-- Фиксированный хеадер редактора -->
    <header class="editor-header">
        <div class="header-left">
            <span class="header-logo">NPBlog</span>
            <span class="toolbar-divider" id="logoDivider"></span>
            
            <div class="mode-toggle" id="headerModeToggle" onmousedown="event.preventDefault()">
                <button type="button" id="modeVisualBtn" class="format-btn" title="Визуальный режим">Визуально</button>
                <button type="button" id="modeCodeBtn" class="format-btn" title="Режим кода">Код</button>
            </div>
            
            <span class="toolbar-divider" id="modeActionsDivider"></span>
            
            <div class="editor-actions" id="headerEditorActions" onmousedown="event.preventDefault()">
                <button type="button" id="undoBtn" class="format-btn" onclick="undoEdit()" title="Отменить (Ctrl+Z)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
                        <path d="M3 7v6h6" />
                        <path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13" />
                    </svg>
                </button>
                <button type="button" id="redoBtn" class="format-btn" onclick="redoEdit()" title="Вернуть (Ctrl+Y)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
                        <path d="M21 7v6h-6" />
                        <path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7" />
                    </svg>
                </button>
            </div>
            
            <span class="toolbar-divider" id="actionsFormattingDivider"></span>
            
            <div class="formatting-buttons" id="formatBarRow" onmousedown="event.preventDefault()">
                <span class="toolbar-group">
                    <button type="button" id="btn-bold" class="format-btn" onclick="formatText('b')" title="Жирный">B</button>
                    <button type="button" id="btn-italic" class="format-btn" onclick="formatText('i')" title="Курсив"><i>I</i></button>
                    <button type="button" id="btn-underline" class="format-btn" onclick="formatText('u')" title="Подчеркнутый">U</button>
                    <button type="button" id="btn-strike" class="format-btn" onclick="formatText('s')" title="Зачеркнутый"><s>S</s></button>
                    <button type="button" id="btn-sup" class="format-btn" onclick="formatText('sup')" title="Верхний индекс"><span>X<sup>2</sup></span></button>
                    <button type="button" id="btn-sub" class="format-btn" onclick="formatText('sub')" title="Нижний индекс"><span>X<sub>2</sub></span></button>
                    <button type="button" id="btn-h2" class="format-btn" onclick="formatText('h2')" title="Подзаголовок">H</button>
                    <button type="button" id="btn-table" class="format-btn" onclick="openTableDialog()" title="Вставить таблицу">⊞</button>
                    <button type="button" id="btn-spoiler" class="format-btn" onclick="openSpoilerDialog()" title="Сворачиваемый блок"><span class="spoiler-icon">▼</span></button>
                    <button type="button" id="btn-marker" class="format-btn" onclick="openMarkerDialog()" title="Маркер">🖍</button>
                    <button type="button" id="btn-anchor" class="format-btn" onclick="addAnchor()" title="Добавить якорь">⚓</button>
                    <button type="button" id="btn-ascii" class="format-btn" onclick="openAsciiDrawer()" title="ASCII Рисовалка">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <line x1="9" y1="3" x2="9" y2="21"/>
                            <line x1="15" y1="3" x2="15" y2="21"/>
                            <line x1="3" y1="9" x2="21" y2="9"/>
                            <line x1="3" y1="15" x2="21" y2="15"/>
                        </svg>
                    </button>
                </span>
                <span class="toolbar-divider"></span>
                <span class="toolbar-group">
                    <button type="button" class="format-btn" onclick="alignText('left')" title="По левому краю">◄</button>
                    <button type="button" class="format-btn" onclick="alignText('center')" title="По центру">≡</button>
                    <button type="button" class="format-btn" onclick="alignText('right')" title="По правому краю">►</button>
                </span>
                <span class="toolbar-divider"></span>
                <span class="toolbar-group">
                    <button type="button" class="format-btn" onclick="addLink()" title="Ссылка">🔗</button>
                    <button type="button" class="format-btn" onclick="showImageUpload()" title="Добавить изображение">📷</button>
                    <button type="button" class="format-btn" onclick="showMediaDialog()" title="Добавить медиа">🎬</button>
                </span>
                <span class="toolbar-divider"></span>
                <span class="toolbar-group">
                    <div class="font-size-picker-wrap" id="fontSizeWrapMain">
                        <button type="button" id="fontSizeBtn" class="format-btn font-size-picker-btn" title="Размер шрифта">14px</button>
                        <div class="font-size-popover">
                            <div class="font-size-popover-inner">
                                <button type="button" class="font-size-item" data-size="12">12px</button>
                                <button type="button" class="font-size-item" data-size="14">14px</button>
                                <button type="button" class="font-size-item" data-size="16">16px</button>
                                <button type="button" class="font-size-item" data-size="18">18px</button>
                                <button type="button" class="font-size-item" data-size="20">20px</button>
                                <button type="button" class="font-size-item" data-size="24">24px</button>
                                <button type="button" class="font-size-item" data-size="28">28px</button>
                                <button type="button" class="font-size-item" data-size="32">32px</button>
                                <div class="font-size-custom">
                                    <label>Свой размер (8–72)</label>
                                    <input type="number" id="fontSizeCustomMain" min="8" max="72" placeholder="px">
                                    <button type="button" onclick="applyCustomFontSize('fontSizeWrapMain')">Применить</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="font-family-picker-wrap" id="fontFamilyWrapMain">
                        <button type="button" id="fontFamilyBtn" class="format-btn font-family-picker-btn" title="Шрифт">Arial</button>
                        <div class="font-family-popover">
                            <div class="font-family-popover-inner">
                                <button type="button" class="font-family-item" data-font="Arial" style="font-family:Arial">Arial</button>
                                <button type="button" class="font-family-item" data-font="Times New Roman" style="font-family:'Times New Roman'">Times New Roman</button>
                                <button type="button" class="font-family-item" data-font="Open Sans" style="font-family:'Open Sans'">Open Sans</button>
                                <button type="button" class="font-family-item" data-font="Verdana" style="font-family:Verdana">Verdana</button>
                                <button type="button" class="font-family-item" data-font="Helvetica" style="font-family:Helvetica">Helvetica</button>
                                <button type="button" class="font-family-item" data-font="Georgia" style="font-family:Georgia">Georgia</button>
                                <button type="button" class="font-family-item" data-font="PT Sans" style="font-family:'PT Sans'">PT Sans</button>
                                <button type="button" class="font-family-item" data-font="Comic Sans MS" style="font-family:'Comic Sans MS'">Comic Sans MS</button>
                                <div class="font-family-custom">
                                    <button type="button" onclick="openCustomFontsModal()">📁 Свой шрифт</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="color-picker-wrap" id="colorPickerWrapMain">
                        <button type="button" class="color-picker-btn" title="Цвет текста" aria-label="Цвет текста"><span class="color-preview" style="background:#333;"></span></button>
                        <div class="color-palette-popover">
                            <div class="color-palette-grid" id="colorPaletteGridMain"></div>
                            <div class="color-palette-custom">
                                <label>Свой цвет <input type="color" id="textColorCustomMain" value="#333333"></label>
                            </div>
                        </div>
                    </div>
                </span>
                <span class="toolbar-divider"></span>
                <span class="toolbar-group">
                    <div class="more-menu-wrap" id="moreMenuWrap">
                        <button type="button" class="format-btn" title="Прочее" onclick="toggleMoreMenu()">⋯</button>
                        <div class="more-menu-dropdown" id="moreMenuDropdown">
                            <button type="button" class="more-menu-item" onclick="saveDraft()">Сохранить в черновик</button>
                            <button type="button" class="more-menu-item has-submenu" onclick="toggleDraftsSubmenu(event)">
                                Черновики
                                <div class="more-submenu" id="draftsSubmenu">
                                    <div class="more-submenu-empty">Загрузка...</div>
                                </div>
                            </button>
                            <button type="button" class="more-menu-item" onclick="openSaveInclude()">Сохранить в includes</button>
                            <button type="button" class="more-menu-item has-submenu" onclick="toggleIncludesSubmenu(event)">
                                Вставить
                                <div class="more-submenu" id="includesSubmenu">
                                    <div class="more-submenu-empty">Загрузка...</div>
                                </div>
                            </button>
                            <button type="button" class="more-menu-item has-submenu" onclick="toggleArticlesSubmenu(event)">
                                Вставить ссылку на статью
                                <div class="more-submenu" id="articlesSubmenu">
                                    <div class="more-submenu-empty">Загрузка...</div>
                                </div>
                            </button>
                            <button type="button" class="more-menu-item has-submenu" onclick="toggleTocSubmenu(event)">
                                Содержание
                                <div class="more-submenu" id="tocSubmenu">
                                    <div class="more-submenu-empty">Нет якорей в статье</div>
                                </div>
                            </button>
                            <button type="button" class="more-menu-item" onclick="openFileUploadDialog()">Загрузить файл</button>
                        </div>
                    </div>
                </span>
            </div>
        </div>
        
        <div class="header-right">
            <!-- Таймер автосохранения -->
            <div id="autosaveBadge" onmousedown="event.preventDefault()" style="display: none;">
                <span id="autosaveBadgeText">Автосохранение через 60с</span>
            </div>
            
            <!-- Кнопка сохранения -->
            <button type="submit" id="submitButton" form="blogForm">Сохранить</button>
            
            <!-- Главное меню -->
            <div class="editor-menu-wrap" id="editorMenuWrap">
                <button type="button" class="editor-menu-btn" id="editorMenuBtn" aria-haspopup="true" aria-expanded="false">Меню</button>
                <div class="editor-menu-dropdown" role="menu">
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="toggleManagePosts()">Управление статьями</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="openGlobalSettings()">Глобальные параметры</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="openBackupManager()">Менеджер бэкапов</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="checkPostNumbering()">Проверка нумерации</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="resetTutorial()">Сбросить обучение</button>
                    <button type="button" class="editor-menu-item" id="theme-toggle" role="menuitem">Изменить тему</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="window.location.href='ftp.php'">Опубликовать по FTP</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="window.location.href='data/blog.html'">Перейти к Blog.html</button>
                    <button type="button" class="editor-menu-item" role="menuitem" onclick="openSystemUpdateModal()">Обновить NPBlog</button>
                    <div class="editor-menu-version">ver 2.188</div>
                </div>
            </div>
        </div>
    </header>
<!-- тест2 -->
    <form id="blogForm">
        <input class="content228 editor-field" type="text" id="title" placeholder="Заголовок статьи" required>
        <textarea class="content228 editor-field" id="content" placeholder="Содержание статьи" style="display:none;"></textarea>
        <div id="contentVisual" class="content228 editor-field" contenteditable="true"></div>
    </form>

    <div id="editorContextMenu" class="editor-context-menu" role="menu">
        <button type="button" class="editor-context-item" data-cmd="paste" role="menuitem">Вставить</button>
        <button type="button" class="editor-context-item" data-cmd="copy" role="menuitem">Копировать</button>
        <button type="button" class="editor-context-item" data-cmd="cut" role="menuitem">Вырезать</button>
        <button type="button" class="editor-context-item" data-cmd="delete" role="menuitem">Удалить</button>
        <span class="editor-context-sep"></span>
        <button type="button" class="editor-context-item" data-cmd="link" role="menuitem">Вставить ссылку</button>
        <button type="button" class="editor-context-item" data-cmd="image" role="menuitem">Вставить изображение</button>
        <button type="button" class="editor-context-item" data-cmd="list" role="menuitem">Вставить список</button>
        <span class="editor-context-sep table-context-sep" style="display: none;"></span>
        <button type="button" class="editor-context-item table-context-item" data-cmd="addRow" role="menuitem" style="display: none;">Добавить строку</button>
        <button type="button" class="editor-context-item table-context-item" data-cmd="deleteRow" role="menuitem" style="display: none;">Удалить строку</button>
        <button type="button" class="editor-context-item table-context-item" data-cmd="addColumn" role="menuitem" style="display: none;">Добавить столбец</button>
        <button type="button" class="editor-context-item table-context-item" data-cmd="deleteColumn" role="menuitem" style="display: none;">Удалить столбец</button>
        <button type="button" class="editor-context-item table-context-item" data-cmd="colorCell" role="menuitem" style="display: none;">Перекрасить ячейку</button>
        <span class="editor-context-sep table-context-sep" style="display: none;"></span>
        <button type="button" class="editor-context-item table-context-item" data-cmd="deleteTable" role="menuitem" style="display: none;">Удалить таблицу</button>
    </div>

    <!-- -->

        <div class="manage-posts" id="managePosts">
        <div class="manage-posts-header">
            <h2>Все статьи</h2>
            <button type="button" class="close-manage" onclick="toggleManagePosts()" aria-label="Закрыть">×</button>
        </div>
        <div style="padding: 16px 16px 0;">
            <input type="text" id="postsSearchInput" class="posts-search-input" placeholder="🔍 Поиск по статьям..." oninput="filterPosts()">
        </div>
        <div id="postsList"></div>
    </div>
    
    <div id="imageUploadDialog" class="dialog">
    <div class="dialog-content">
        <h3>Добавить изображение</h3>
        

        <div class="image-source-toggle">
            <label>
                <input type="radio" name="imageSource" value="file" checked> Загрузить файл
            </label>
            <label>
                <input type="radio" name="imageSource" value="url"> Вставить ссылку
            </label>
        </div>


        <div id="fileUploadContainer">
            <input type="file" id="imageFile" accept="image/*" multiple>
        </div>

        <div id="imageGridPreviewContainer" style="display: none; margin: 15px 0;"></div>


        <div id="urlContainer" style="display: none;">
            <input type="text" id="imageUrl" placeholder="Введите URL изображения (несколько — с новой строки или через запятую)" class="image-url-input">
        </div>
        <div class="form-group">
    <label for="imageCaption">Подпись к изображению:</label>
    <input type="text" id="imageCaption" class="form-control" placeholder="Введите подпись (необязательно)">
</div>

        <div class="image-size-controls">
            <label>
                Размер:
                <select id="imageSize">
                    <option value="small">Маленький</option>
                    <option value="medium" selected>Средний</option>
                    <option value="large">Большой</option>
                </select>
            </label>
            <label>
                Расположение:
                <select id="gridLayout">
                    <option value="">Обычное</option>
                    <option value="2x1">2×1</option>
                    <option value="2x2">2×2</option>
                    <option value="3x1">3×1</option>
                    <option value="3x2">3×2</option>
                    <option value="3x3">3×3</option>
                </select>
            </label>
            <div id="customSizeInputs" style="display: none;">
                <div class="size-input-group">
                    <input type="number" id="customWidth" placeholder="Ширина">
                    <select id="widthUnit">
                        <option value="px">px</option>
                        <option value="%">%</option>
                    </select>
                </div>
                <div class="size-input-group">
                    <input type="number" id="customHeight" placeholder="Высота">
                    <select id="heightUnit">
                        <option value="px">px</option>
                        <option value="%">%</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="dialog-buttons">
            <button onclick="processImage()">Добавить</button>
            <button onclick="closeImageDialog()">Отмена</button>
        </div>
    </div>
</div>

    <div id="codeDialog" class="dialog code-dialog">
    <div class="dialog-content">
        <h3>Вставить код</h3>
        <select id="codeLanguage" class="language-select">
            <option value="javascript">JavaScript</option>
            <option value="php">PHP</option>
            <option value="html">HTML</option>
            <option value="css">CSS</option>
            <option value="python">Python</option>
            <option value="sql">SQL</option>
            <option value="java">Java</option>
            <option value="cpp">C++</option>
            <option value="csharp">C#</option>
            <option value="ruby">Ruby</option>
            <option value="plain">Текст</option>
        </select>
        <textarea id="codeInput" class="code-input" placeholder="Вставьте ваш код сюда..."></textarea>
        <div class="dialog-buttons">
            <button onclick="insertCodeBlock()">Вставить</button>
            <button onclick="closeCodeDialog()">Отмена</button>
        </div>
    </div>
</div>

<!-- Диалог загрузки файлов -->
<div id="fileUploadDialog" class="file-upload-dialog">
    <div class="dialog-content">
        <h3>Загрузить файл</h3>
        
        <div class="form-group">
            <label style="display: block; margin-bottom: 10px; font-weight: 500;">Выберите файл:</label>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <label style="display: flex; gap: 10px; align-items: center; border: 2px dashed var(--border-color); border-radius: 8px; padding: 12px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='rgba(128,128,128,0.1)'" onmouseout="this.style.background='transparent'">
                    <input type="file" id="documentFile" style="display: none;" onchange="document.getElementById('documentFileName').textContent = this.files[0] ? this.files[0].name : 'Файл не выбран'">
                    <div style="background: var(--text-color); color: var(--bg-color); padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 13px;">Обзор...</div>
                    <span id="documentFileName" style="color: var(--text-color); opacity: 0.7; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1;">Файл не выбран</span>
                </label>
                <button type="button" class="delete-confirm-btn delete" onclick="uploadDocument()" style="width: 100%; padding: 10px; border-radius: 8px; font-size: 14px;">Загрузить файл</button>
            </div>
        </div>
        
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;">
                <input type="checkbox" id="insertAsHyperlink" style="cursor: pointer;">
                <span>Вставить как гиперссылку</span>
            </label>
        </div>
        
        <div class="form-group">
            <label>Загруженные файлы:</label>
            <div class="file-upload-list" id="fileUploadList">
                <div class="file-upload-empty">Загрузка списка файлов...</div>
            </div>
        </div>
        
        <div class="dialog-buttons">
            <button onclick="closeFileUploadDialog()">Закрыть</button>
        </div>
    </div>
</div>

<div id="fontSizeDialog" class="dialog">
    <div class="dialog-content">
        <h3>Указать размер шрифта</h3>
        <input type="number" id="customFontSize" min="8" max="72" placeholder="Размер в px">
        <div class="dialog-buttons">
            <button onclick="setCustomFontSize()">Применить</button>
            <button onclick="closeFontSizeDialog()">Отмена</button>
        </div>
    </div>
</div>


<div id="mediaDialog" class="dialog">
    <div class="dialog-content">
        <h3>Добавить медиа</h3>
        
        <div class="form-group" style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 10px; font-weight: 500;">Тип медиа:</label>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="radio" name="mediaType" value="video-url" checked style="margin-right: 5px;">
                    <span>Видео (YouTube/Vimeo)</span>
                </label>
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="radio" name="mediaType" value="video-file" style="margin-right: 5px;">
                    <span>Видео файл</span>
                </label>
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="radio" name="mediaType" value="audio" style="margin-right: 5px;">
                    <span>Аудио файл</span>
                </label>
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="radio" name="mediaType" value="audio-stream" style="margin-right: 5px;">
                    <span>Аудио поток</span>
                </label>
            </div>
        </div>
        
        <div id="videoUrlSection">
            <input type="text" id="mediaUrl" placeholder="Вставьте ссылку на YouTube или Vimeo" class="media-input">
        </div>

        <div id="audioStreamSection" style="display: none;">
            <input type="text" id="audioStreamUrl" placeholder="Вставьте ссылку на аудиопоток (например, радио или прямой URL)" class="media-input">
        </div>
        
        <div id="videoFileSection" style="display: none;">
            <div class="form-group">
                <label style="display: block; margin-bottom: 10px; font-weight: 500;">Загрузить видео файл:</label>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <label style="display: flex; gap: 10px; align-items: center; border: 2px dashed var(--border-color); border-radius: 8px; padding: 12px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='rgba(128,128,128,0.1)'" onmouseout="this.style.background='transparent'">
                        <input type="file" id="videoFile" accept="video/*" style="display: none;" onchange="document.getElementById('videoFileName').textContent = this.files[0] ? this.files[0].name : 'Файл не выбран'">
                        <div style="background: var(--text-color); color: var(--bg-color); padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 13px;">Обзор...</div>
                        <span id="videoFileName" style="color: var(--text-color); opacity: 0.7; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1;">Файл не выбран</span>
                    </label>
                    <button type="button" class="delete-confirm-btn delete" onclick="uploadVideoFile()" style="width: 100%; padding: 10px; border-radius: 8px; font-size: 14px;">Загрузить видео</button>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label style="display: block; margin-bottom: 10px;">Загруженные видео файлы:</label>
                <div id="videoFilesList" style="max-height: 200px; overflow-y: auto; border: 2px solid var(--border-color); border-radius: 8px; padding: 10px;">
                    <div style="color: var(--text-color); opacity: 0.6;">Загрузка списка...</div>
                </div>
            </div>
        </div>
        
        <div id="audioMediaSection" style="display: none;">
            <div class="form-group">
                <label style="display: block; margin-bottom: 10px; font-weight: 500;">Загрузить аудио файл:</label>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <label style="display: flex; gap: 10px; align-items: center; border: 2px dashed var(--border-color); border-radius: 8px; padding: 12px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='rgba(128,128,128,0.1)'" onmouseout="this.style.background='transparent'">
                        <input type="file" id="audioFile" accept="audio/*" style="display: none;" onchange="document.getElementById('audioFileName').textContent = this.files[0] ? this.files[0].name : 'Файл не выбран'">
                        <div style="background: var(--text-color); color: var(--bg-color); padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 13px;">Обзор...</div>
                        <span id="audioFileName" style="color: var(--text-color); opacity: 0.7; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1;">Файл не выбран</span>
                    </label>
                    <button type="button" class="delete-confirm-btn delete" onclick="uploadAudioFile()" style="width: 100%; padding: 10px; border-radius: 8px; font-size: 14px;">Загрузить аудио</button>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label style="display: block; margin-bottom: 10px;">Загруженные аудио файлы:</label>
                <div id="audioFilesList" style="max-height: 200px; overflow-y: auto; border: 2px solid var(--border-color); border-radius: 8px; padding: 10px;">
                    <div style="color: var(--text-color); opacity: 0.6;">Загрузка списка...</div>
                </div>
            </div>
        </div>
        
        <div class="dialog-buttons">
            <button onclick="insertMedia()">Вставить</button>
            <button onclick="closeMediaDialog()">Отмена</button>
        </div>
    </div>
</div>

<div id="spoilerDialog" class="dialog">
    <div class="dialog-content">
        <h3>Сворачиваемый блок</h3>
        <label for="spoilerTitle">Заголовок блока:</label>
        <input type="text" id="spoilerTitle" placeholder="Например: Подробности" class="form-control">
        <div class="dialog-buttons">
            <button onclick="insertSpoiler()">Вставить</button>
            <button onclick="closeSpoilerDialog()">Отмена</button>
        </div>
    </div>
</div>

<div id="markerDialog" class="dialog">
    <div class="dialog-content">
        <h3>Выделить маркером</h3>
        <label>Выберите стиль:</label>
        <div class="marker-styles">
            <button class="marker-style-btn active" data-style="straight" title="Ровное">
                <span class="marker-style-preview marker-preview-straight">Текст</span>
            </button>
            <button class="marker-style-btn" data-style="rough" title="Кривое">
                <span class="marker-style-preview marker-preview-rough">Текст</span>
            </button>
            <button class="marker-style-btn" data-style="zigzag" title="Зигзагом">
                <span class="marker-style-preview marker-preview-zigzag">Текст</span>
            </button>
            <button class="marker-style-btn" data-style="wavy" title="Волнистое">
                <span class="marker-style-preview marker-preview-wavy">Текст</span>
            </button>
        </div>
        <label style="margin-top: 16px;">Выберите цвет:</label>
        <div class="marker-colors">
            <button class="marker-color-btn" data-color="#ffeb3b" style="background: #ffeb3b;" title="Желтый"></button>
            <button class="marker-color-btn" data-color="#4caf50" style="background: #4caf50;" title="Зеленый"></button>
            <button class="marker-color-btn" data-color="#2196f3" style="background: #2196f3;" title="Синий"></button>
            <button class="marker-color-btn" data-color="#ff9800" style="background: #ff9800;" title="Оранжевый"></button>
            <button class="marker-color-btn" data-color="#e91e63" style="background: #e91e63;" title="Розовый"></button>
            <button class="marker-color-btn" data-color="#9c27b0" style="background: #9c27b0;" title="Фиолетовый"></button>
        </div>
        <div class="dialog-buttons">
            <button onclick="closeMarkerDialog()">Отмена</button>
        </div>
    </div>
</div>

<div id="tableDialog" class="dialog">
    <div class="dialog-content">
        <h3>Вставить таблицу</h3>
        <div class="form-group">
            <label for="tableRows">Количество строк:</label>
            <input type="number" id="tableRows" class="form-control" min="1" max="20" value="3" placeholder="Введите количество строк">
        </div>
        <div class="form-group">
            <label for="tableCols">Количество столбцов:</label>
            <input type="number" id="tableCols" class="form-control" min="1" max="7" value="3" placeholder="Введите количество столбцов">
        </div>
        <div class="dialog-buttons">
            <button onclick="insertTable()">Вставить</button>
            <button onclick="closeTableDialog()">Отмена</button>
        </div>
    </div>
</div>

<div id="cellColorDialog" class="dialog">
    <div class="dialog-content">
        <h3>Перекрасить ячейку</h3>
        <div class="form-group">
            <label>Выберите цвет:</label>
            <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin: 15px 0;">
                <button type="button" onclick="setCellColor('#ffffff')" style="width: 40px; height: 40px; background: #ffffff; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Белый"></button>
                <button type="button" onclick="setCellColor('#f0f0f0')" style="width: 40px; height: 40px; background: #f0f0f0; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Светло-серый"></button>
                <button type="button" onclick="setCellColor('#ffebee')" style="width: 40px; height: 40px; background: #ffebee; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Светло-красный"></button>
                <button type="button" onclick="setCellColor('#fff3e0')" style="width: 40px; height: 40px; background: #fff3e0; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Светло-оранжевый"></button>
                <button type="button" onclick="setCellColor('#fffde7')" style="width: 40px; height: 40px; background: #fffde7; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Светло-желтый"></button>
                <button type="button" onclick="setCellColor('#e8f5e9')" style="width: 40px; height: 40px; background: #e8f5e9; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Светло-зеленый"></button>
                <button type="button" onclick="setCellColor('#e3f2fd')" style="width: 40px; height: 40px; background: #e3f2fd; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Светло-синий"></button>
                <button type="button" onclick="setCellColor('#f3e5f5')" style="width: 40px; height: 40px; background: #f3e5f5; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Светло-фиолетовый"></button>
                <button type="button" onclick="setCellColor('#ffcdd2')" style="width: 40px; height: 40px; background: #ffcdd2; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Красный"></button>
                <button type="button" onclick="setCellColor('#ffe0b2')" style="width: 40px; height: 40px; background: #ffe0b2; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Оранжевый"></button>
                <button type="button" onclick="setCellColor('#fff9c4')" style="width: 40px; height: 40px; background: #fff9c4; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Желтый"></button>
                <button type="button" onclick="setCellColor('#c8e6c9')" style="width: 40px; height: 40px; background: #c8e6c9; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Зеленый"></button>
                <button type="button" onclick="setCellColor('#bbdefb')" style="width: 40px; height: 40px; background: #bbdefb; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Синий"></button>
                <button type="button" onclick="setCellColor('#e1bee7')" style="width: 40px; height: 40px; background: #e1bee7; border: 2px solid #ccc; border-radius: 6px; cursor: pointer;" title="Фиолетовый"></button>
            </div>
            <button type="button" onclick="setCellColor('')" style="width: 100%; padding: 8px; margin-top: 10px; background: var(--bg-color); color: var(--text-color); border: 2px solid var(--border-color); border-radius: 6px; cursor: pointer;">Убрать цвет</button>
        </div>
        <div class="dialog-buttons">
            <button onclick="closeCellColorDialog()">Закрыть</button>
        </div>
    </div>
</div>

<div id="linkDialog" class="dialog">
    <div class="dialog-content">
        <h3>Вставить ссылку</h3>
        <div class="form-group">
            <label for="linkUrl">URL</label>
            <input type="text" id="linkUrl" class="form-control" placeholder="https://">
        </div>
        <div class="form-group">
            <label for="linkText">Текст ссылки (необязательно)</label>
            <input type="text" id="linkText" class="form-control" placeholder="Оставьте пустым — будет использован выделенный текст">
        </div>
        <div class="dialog-buttons">
            <button onclick="insertLinkFromDialog()">Вставить</button>
            <button onclick="closeLinkDialog()">Отмена</button>
        </div>
    </div>
</div>

<script src="editor-main.js?v=1779014530"></script>

<script src="editor-img.js?v=1779014519"></script>

<!-- Модальное окно дополнительных настроек -->
<div id="additionalSettingsModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <h3 style="margin: 0 0 20px 0; color: var(--text-color); font-size: 20px;">Дополнительные настройки</h3>
        <p id="additionalSettingsPostTitle" style="color: var(--text-color); margin-bottom: 20px; opacity: 0.7;"></p>
        
        <!-- Глобальный фон -->
        <div id="globalBackgroundInfo" style="display: none; margin-bottom: 20px; padding: 15px; border: 2px solid #ffc107; border-radius: 8px; background: rgba(255, 193, 7, 0.05);">
            <p style="color: var(--text-color); font-weight: 500; margin-bottom: 10px;">🌍 Применен глобальный фон:</p>
            <div style="display: flex; align-items: center; gap: 15px;">
                <img id="globalBackgroundPreview" src="" alt="Глобальный фон" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid var(--border-color);">
                <div>
                    <p id="globalBackgroundName" style="color: var(--text-color); font-size: 14px; word-break: break-all;"></p>
                    <p id="globalBackgroundModeText" style="color: var(--text-color); font-size: 12px; opacity: 0.7; margin-top: 5px;"></p>
                    <p style="color: var(--text-color); font-size: 12px; opacity: 0.6; margin-top: 5px; font-style: italic;">Загрузите свой фон ниже, чтобы переопределить глобальный</p>
                </div>
            </div>
        </div>
        
        <!-- Текущий фон статьи -->
        <div id="currentBackgroundInfo" style="display: none; margin-bottom: 20px; padding: 15px; border: 2px solid var(--border-color); border-radius: 8px;">
            <p style="color: var(--text-color); font-weight: 500; margin-bottom: 10px;">Текущий фон статьи:</p>
            <div style="display: flex; align-items: center; gap: 15px;">
                <img id="currentBackgroundPreview" src="" alt="Фон" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid var(--border-color);">
                <div>
                    <p id="currentBackgroundName" style="color: var(--text-color); font-size: 14px; word-break: break-all;"></p>
                    <p id="currentBackgroundMode" style="color: var(--text-color); font-size: 12px; opacity: 0.7; margin-top: 5px;"></p>
                </div>
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Фоновое изображение:</label>
            <input type="file" id="backgroundInput" accept="image/*" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 10px;">
            
            <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Режим отображения:</label>
            <select id="backgroundMode" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 15px;">
                <option value="cover">Растянуть (cover)</option>
                <option value="contain">По размеру (contain)</option>
                <option value="repeat">Замостить (repeat)</option>
            </select>
            
            <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Область фона:</label>
            <select id="backgroundScope" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 15px;">
                <option value="content">Только статья (920px)</option>
                <option value="fullpage">Вся страница</option>
            </select>
            
            <button type="button" onclick="uploadBackground()" style="padding: 10px 20px; background: var(--text-color); color: var(--bg-color); border: none; border-radius: 8px; cursor: pointer; font-weight: 500; margin-right: 10px;">Загрузить фон</button>
            <button type="button" onclick="removeBackground()" style="padding: 10px 20px; background: transparent; color: var(--text-color); border: 2px solid var(--text-color); border-radius: 8px; cursor: pointer; font-weight: 500;">Вернуть стандартный фон</button>
        </div>
        
        <!-- Настройки подложки -->
        <div style="margin-bottom: 20px; padding-top: 20px; border-top: 2px solid var(--border-color);">
            <label style="display: flex; align-items: center; margin-bottom: 15px; color: var(--text-color); font-weight: 500; cursor: pointer;">
                <input type="checkbox" id="overlayEnabled" onchange="toggleOverlaySettings()" style="width: 20px; height: 20px; margin-right: 10px; cursor: pointer;">
                Включить подложку под статью
            </label>
            
            <div id="overlaySettings" style="display: none; padding-left: 30px;">
                <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Цвет подложки:</label>
                <input type="color" id="overlayColor" value="#ffffff" style="width: 100%; height: 40px; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; margin-bottom: 15px;">
                
                <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Прозрачность: <span id="overlayOpacityValue">90%</span></label>
                <input type="range" id="overlayOpacity" min="0" max="100" value="90" oninput="updateOpacityValue()" style="width: 100%; margin-bottom: 15px;">
            </div>
            
            <button type="button" onclick="saveOverlaySettings()" style="padding: 10px 20px; background: var(--text-color); color: var(--bg-color); border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Сохранить настройки подложки</button>
        </div>
        
        <div style="text-align: right; margin-top: 20px;">
            <button type="button" onclick="closeAdditionalSettings()" style="padding: 10px 20px; background: var(--text-color); color: var(--bg-color); border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Закрыть</button>
        </div>
    </div>
</div>

<!-- Модальное окно глобальных параметров -->
<div id="globalSettingsModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); border-radius: 12px; max-width: 900px; width: 90%; height: 80vh; box-shadow: 0 4px 20px rgba(0,0,0,0.3); display: flex; overflow: hidden;">
        <!-- Навигация слева -->
        <div style="width: 200px; background: rgba(0,0,0,0.05); border-right: 2px solid var(--border-color); padding: 20px; overflow-y: auto;">
            <h3 style="margin: 0 0 20px 0; color: var(--text-color); font-size: 18px;">Навигация</h3>
            <button type="button" onclick="showGlobalSection('backgrounds')" class="global-nav-btn active" data-section="backgrounds" style="display: block; width: 100%; padding: 10px; margin-bottom: 5px; background: transparent; color: var(--text-color); border: none; border-radius: 6px; cursor: pointer; text-align: left; font-size: 14px; transition: background 0.2s;">
                Фон статей
            </button>
            <button type="button" onclick="showGlobalSection('blogview')" class="global-nav-btn" data-section="blogview" style="display: block; width: 100%; padding: 10px; margin-bottom: 5px; background: transparent; color: var(--text-color); border: none; border-radius: 6px; cursor: pointer; text-align: left; font-size: 14px; transition: background 0.2s;">
                Вид blog.html
            </button>
            <button type="button" onclick="showGlobalSection('autosave')" class="global-nav-btn" data-section="autosave" style="display: block; width: 100%; padding: 10px; margin-bottom: 5px; background: transparent; color: var(--text-color); border: none; border-radius: 6px; cursor: pointer; text-align: left; font-size: 14px; transition: background 0.2s;">
                Автосохранение
            </button>
            <button type="button" onclick="showGlobalSection('appearance')" class="global-nav-btn" data-section="appearance" style="display: block; width: 100%; padding: 10px; margin-bottom: 5px; background: transparent; color: var(--text-color); border: none; border-radius: 6px; cursor: pointer; text-align: left; font-size: 14px; transition: background 0.2s;">
                Внешний вид
            </button>
            <button type="button" onclick="showGlobalSection('experimental')" class="global-nav-btn" data-section="experimental" style="display: block; width: 100%; padding: 10px; margin-bottom: 5px; background: transparent; color: var(--text-color); border: none; border-radius: 6px; cursor: pointer; text-align: left; font-size: 14px; transition: background 0.2s;">
                Экспериментальные
            </button>
            <button type="button" onclick="showGlobalSection('rss')" class="global-nav-btn" data-section="rss" style="display: block; width: 100%; padding: 10px; margin-bottom: 5px; background: transparent; color: var(--text-color); border: none; border-radius: 6px; cursor: pointer; text-align: left; font-size: 14px; transition: background 0.2s;">
                RSS Виджет
            </button>
            <!-- Здесь можно добавить другие пункты навигации -->
        </div>
        
        <!-- Контент справа -->
        <div style="flex: 1; padding: 30px; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: var(--text-color); font-size: 20px;" id="globalSectionTitle">Фон статей</h3>
                <button type="button" onclick="closeGlobalSettings()" style="background: transparent; border: none; font-size: 28px; color: var(--text-color); cursor: pointer; line-height: 1;">×</button>
            </div>
            
            <!-- Секция: Фон статей -->
            <div id="globalSection-backgrounds" class="global-section">
                <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8;">Загрузите фоновое изображение, которое будет применяться ко всем статьям по умолчанию.</p>
                
                <!-- Текущий глобальный фон -->
                <div id="currentGlobalBackgroundInfo" style="display: none; margin-bottom: 20px; padding: 15px; border: 2px solid var(--border-color); border-radius: 8px;">
                    <p style="color: var(--text-color); margin-bottom: 10px; font-weight: 500;">Текущий глобальный фон:</p>
                    <img id="currentGlobalBackgroundPreview" src="" style="max-width: 200px; max-height: 150px; border: 2px solid var(--border-color); border-radius: 8px; margin-bottom: 10px;">
                    <p style="color: var(--text-color); font-size: 14px; margin-bottom: 5px;" id="currentGlobalBackgroundName"></p>
                    <p style="color: var(--text-color); font-size: 14px;" id="currentGlobalBackgroundMode"></p>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Фоновое изображение:</label>
                    <input type="file" id="globalBackgroundInput" accept="image/*" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 10px;">
                    
                    <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Режим отображения:</label>
                    <select id="globalBackgroundMode" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 15px;">
                        <option value="cover">Растянуть (cover)</option>
                        <option value="contain">По размеру (contain)</option>
                        <option value="repeat">Замостить (repeat)</option>
                    </select>
                    
                    <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Область фона:</label>
                    <select id="globalBackgroundScope" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 20px;">
                        <option value="content">Только статья (920px)</option>
                        <option value="fullpage">Вся страница</option>
                    </select>
                    
                    <div style="display: flex; gap: 8px; flex-wrap: nowrap; overflow-x: auto;">
                        <button type="button" onclick="uploadGlobalBackground()" class="global-action-btn global-action-btn-primary">Загрузить фон</button>
                        <button type="button" onclick="removeGlobalBackground()" class="global-action-btn global-action-btn-secondary">Удалить фон</button>
                    </div>
                </div>
                
                <div style="padding: 15px; background: rgba(255, 193, 7, 0.1); border: 2px solid rgba(255, 193, 7, 0.5); border-radius: 8px; margin-top: 20px;">
                    <p style="color: var(--text-color); font-size: 14px; margin: 0;">
                        ⚠️ Глобальный фон применяется ко всем существующим статьям и будет автоматически применяться к новым статьям. Индивидуальные настройки фона статьи имеют приоритет над глобальным фоном.
                    </p>
                </div>
            </div>
            
            <!-- Секция: Вид blog.html -->
            <div id="globalSection-blogview" class="global-section" style="display: none;">
                <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8;">Настройте внешний вид страницы со списком статей (blog.html).</p>
                
                <div style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color);">
                    <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Заголовок страницы:</label>
                    <input type="text" id="blogPageTitle" placeholder="Блог" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 20px; font-size: 14px;">
                    
                    <button type="button" onclick="saveBlogViewSettings()" class="global-action-btn global-action-btn-primary">Сохранить настройки</button>
                </div>

                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: var(--text-color); font-size: 16px;">Фон страницы списка статей (blog.html)</h4>
                    
                    <!-- Текущий фон blog.html -->
                    <div id="currentBlogBackgroundInfo" style="display: none; margin-bottom: 20px; padding: 15px; border: 2px solid var(--border-color); border-radius: 8px;">
                        <p style="color: var(--text-color); margin-bottom: 10px; font-weight: 500;">Текущий фон списка статей:</p>
                        <img id="currentBlogBackgroundPreview" src="" style="max-width: 200px; max-height: 150px; border: 2px solid var(--border-color); border-radius: 8px; margin-bottom: 10px;">
                        <p style="color: var(--text-color); font-size: 14px; margin-bottom: 5px;" id="currentBlogBackgroundName"></p>
                        <p style="color: var(--text-color); font-size: 14px;" id="currentBlogBackgroundMode"></p>
                    </div>

                    <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Фоновое изображение:</label>
                    <input type="file" id="blogBackgroundInput" accept="image/*" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 10px;">
                    
                    <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Режим отображения:</label>
                    <select id="blogBackgroundMode" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 20px;">
                        <option value="cover">Растянуть (cover)</option>
                        <option value="contain">По размеру (contain)</option>
                        <option value="repeat">Замостить (repeat)</option>
                    </select>

                    <div style="display: flex; gap: 8px; flex-wrap: nowrap; overflow-x: auto;">
                        <button type="button" onclick="uploadBlogBackground()" class="global-action-btn global-action-btn-primary">Загрузить фон</button>
                        <button type="button" onclick="removeBlogBackground()" class="global-action-btn global-action-btn-secondary">Удалить фон</button>
                    </div>
                </div>
            </div>
            
            <!-- Секция: Автосохранение -->
            <div id="globalSection-autosave" class="global-section" style="display: none;">
                <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8;">Настройте автоматическое сохранение статей во время редактирования.</p>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; margin-bottom: 20px; cursor: pointer;">
                        <input type="checkbox" id="autosaveEnabled" onchange="toggleAutosavePreview()" style="width: 20px; height: 20px; margin-right: 10px; cursor: pointer;">
                        <span style="color: var(--text-color); font-weight: 500; font-size: 16px;">Включить автосохранение</span>
                    </label>
                    
                    <label style="display: block; margin-bottom: 10px; color: var(--text-color); font-weight: 500;">Интервал автосохранения (секунды):</label>
                    <input type="number" id="autosaveInterval" min="10" max="600" value="60" style="display: block; width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); margin-bottom: 20px; font-size: 14px;">
                    
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" onclick="saveAutosaveSettings()" class="global-action-btn global-action-btn-primary">Сохранить настройки</button>
                        <button type="button" onclick="openAutosaveManager()" class="global-action-btn global-action-btn-accent">Менеджер автосохранений</button>
                    </div>
                </div>
                

                <div style="padding: 15px; background: rgba(33, 150, 243, 0.1); border: 2px solid rgba(33, 150, 243, 0.3); border-radius: 8px; margin-top: 20px;">
                    <p style="color: var(--text-color); font-size: 14px; margin: 0;">
                        💡 Автосохранение создает резервную копию вашей работы через заданный интервал времени. Все автосохранения доступны в менеджере.
                    </p>
                </div>
            </div>
            
            <!-- Секция: Внешний вид -->
            <div id="globalSection-appearance" class="global-section" style="display: none;">
                <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8;">Настройте внешний вид редактора статей.</p>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; margin-bottom: 20px; cursor: pointer;">
                        <input type="checkbox" id="hideEditorModeButtons" style="width: 20px; height: 20px; margin-right: 10px; cursor: pointer;">
                        <span style="color: var(--text-color); font-weight: 500; font-size: 16px;">Скрыть кнопки "Визуально" и "Код"</span>
                    </label>
                    
                    <label style="display: flex; align-items: center; margin-bottom: 20px; cursor: pointer;">
                        <input type="checkbox" id="amoledTheme" style="width: 20px; height: 20px; margin-right: 10px; cursor: pointer;">
                        <span style="color: var(--text-color); font-weight: 500; font-size: 16px;">Включить абсолютно черный фон (для AMOLED дисплеев)</span>
                    </label>
                    
                    <button type="button" onclick="saveAppearanceSettings()" class="global-action-btn global-action-btn-primary">Сохранить настройки</button>
                </div>
                
                <div style="padding: 15px; background: rgba(33, 150, 243, 0.1); border: 2px solid rgba(33, 150, 243, 0.3); border-radius: 8px; margin-top: 20px;">
                    <p style="color: var(--text-color); font-size: 14px; margin: 0;">
                        💡 При скрытии кнопок переключения режимов редактор будет работать только в визуальном режиме.
                    </p>
                </div>
            </div>
            
            <!-- Секция: Экспериментальные функции -->
            <div id="globalSection-experimental" class="global-section" style="display: none;">
                <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8;">Включите или отключите экспериментальные функции редактора.</p>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; margin-bottom: 20px; cursor: pointer;">
                        <input type="checkbox" id="enableUndoRedo" style="width: 20px; height: 20px; margin-right: 10px; cursor: pointer;">
                        <span style="color: var(--text-color); font-weight: 500; font-size: 16px;">Включить Undo/Redo (отмена/возврат изменений)</span>
                    </label>
                    
                    <button type="button" onclick="saveExperimentalSettings()" class="global-action-btn global-action-btn-primary">Сохранить настройки</button>
                </div>
                
                <div style="padding: 15px; background: rgba(255, 152, 0, 0.1); border: 2px solid rgba(255, 152, 0, 0.3); border-radius: 8px; margin-top: 20px;">
                    <p style="color: var(--text-color); font-size: 14px; margin: 0;">
                        ⚠️ Экспериментальные функции могут работать нестабильно. Используйте на свой риск.
                    </p>
                </div>
            </div>
            
            <!-- Секция: Интеграция RSS (Виджет) -->
            <div id="globalSection-rss" class="global-section" style="display: none;">
                <p style="color: var(--text-color); margin-bottom: 20px; opacity: 0.8;">
                    Получите готовый код интерактивного виджета RSS ленты для вставки на главную страницу вашего сайта
                </p>
                
                <!-- Интерактивное превью виджета -->
                <div style="margin-bottom: 24px; padding: 20px; background: rgba(0,0,0,0.02); border: 2px dashed var(--border-color); border-radius: 12px;">
                    <span style="display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-color); opacity: 0.5; margin-bottom: 12px;">Вид виджета</span>
                    <div id="rssLivePreviewContainer" style="min-height: 44px; display: flex; align-items: center;">
                        <div style="font-size: 14px; color: var(--text-color); opacity: 0.6; font-style: italic;">Загрузка превью виджета...</div>
                    </div>
                </div>

                <!-- Поля с кодом для вставки -->
                <div style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 20px;">
                    <!-- Шаг 1: HTML код -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label style="color: var(--text-color); font-weight: 600; font-size: 14px;">Шаг 1: Вставьте этот HTML-код в место вывода виджета</label>
                            <button type="button" onclick="copyToClipboard('rssHtmlCode', this)" style="padding: 6px 12px; font-size: 12px; background: var(--primary-color, #4CAF50); color: #fff; border: none; border-radius: 6px; cursor: pointer; transition: all 0.2s; font-weight: 500;">Копировать HTML</button>
                        </div>
                        <textarea id="rssHtmlCode" readonly style="width: 100%; height: 60px; font-family: monospace; font-size: 13px; padding: 12px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); resize: none; box-sizing: border-box;"></textarea>
                    </div>

                    <!-- Шаг 2: JS код -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label style="color: var(--text-color); font-weight: 600; font-size: 14px;">Шаг 2: Вставьте этот JS-код в конец страницы (перед &lt;/body&gt;)</label>
                            <button type="button" onclick="copyToClipboard('rssJsCode', this)" style="padding: 6px 12px; font-size: 12px; background: var(--primary-color, #4CAF50); color: #fff; border: none; border-radius: 6px; cursor: pointer; transition: all 0.2s; font-weight: 500;">Копировать JS</button>
                        </div>
                        <textarea id="rssJsCode" readonly style="width: 100%; height: 320px; font-family: monospace; font-size: 12px; padding: 12px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); resize: none; box-sizing: border-box; line-height: 1.5;"></textarea>
                    </div>
                </div>

                <div style="padding: 15px; background: rgba(33, 150, 243, 0.1); border: 2px solid rgba(33, 150, 243, 0.3); border-radius: 8px; margin-top: 20px;">
                    <p style="color: var(--text-color); font-size: 13px; margin: 0; line-height: 1.5;">
                        💡 <strong>Совет по стилизации:</strong> Вы можете полностью изменить внешний вид ссылки виджета на вашем сайте с помощью CSS стилей для класса <code>.npblog-rss-link</code>, прописав его в файле стилей вашего сайта.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно уведомлений -->
<div id="notificationModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); border-radius: 12px; max-width: 450px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); overflow: hidden;">
        <div style="padding: 24px;">
            <h3 id="notificationTitle" style="margin: 0 0 15px 0; color: var(--text-color); font-size: 18px; font-weight: 600;"></h3>
            <p id="notificationMessage" style="color: var(--text-color); margin: 0 0 20px 0; line-height: 1.6; opacity: 0.9;"></p>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button id="notificationCancelBtn" onclick="closeNotificationModal(false)" style="padding: 10px 20px; background: transparent; color: var(--text-color); border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; font-weight: 500; display: none;">Отмена</button>
                <button id="notificationOkBtn" onclick="closeNotificationModal(true)" style="padding: 10px 20px; background: var(--text-color); color: var(--bg-color); border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно пользовательских шрифтов -->
<div id="customFontsModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); border-radius: 12px; max-width: 600px; width: 90%; max-height: 70vh; box-shadow: 0 4px 20px rgba(0,0,0,0.3); overflow: hidden; display: flex; flex-direction: column;">
        <div style="padding: 20px; border-bottom: 2px solid var(--border-color); display: flex; justify-content: space-between; align-items: flex-start; flex-direction: column; gap: 10px;">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3 style="margin: 0; color: var(--text-color); font-size: 20px;">Пользовательские шрифты</h3>
                <button type="button" onclick="closeCustomFontsModal()" style="background: transparent; border: none; font-size: 28px; color: var(--text-color); cursor: pointer; line-height: 1;">×</button>
            </div>
            <p style="color: var(--text-color); margin: 0; opacity: 0.7; font-size: 13px;">
                Загрузите файлы шрифтов (.ttf, .otf, .woff, .woff2)
            </p>
            <input type="file" id="fontUploadInput" accept=".ttf,.otf,.woff,.woff2" style="display: none;" onchange="uploadFontFile()">
            <button type="button" onclick="document.getElementById('fontUploadInput').click()" class="global-action-btn global-action-btn-primary" style="margin-top: 10px;">Загрузить шрифт с устройства</button>
        </div>
        <div style="padding: 20px; overflow-y: auto; flex: 1;">
            <div id="customFontsList" style="display: grid; gap: 12px;">
                <!-- Список шрифтов будет загружен динамически -->
            </div>
            <div id="customFontsEmpty" style="display: none; text-align: center; padding: 40px; color: var(--text-color); opacity: 0.5;">
                <p>Нет загруженных шрифтов</p>
                <p style="font-size: 14px; margin-top: 10px;">Добавьте файлы шрифтов в папку data/fonts/</p>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно менеджера автосохранений -->
<div id="autosaveManagerModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); border-radius: 12px; max-width: 800px; width: 90%; max-height: 80vh; box-shadow: 0 4px 20px rgba(0,0,0,0.3); overflow: hidden; display: flex; flex-direction: column;">
        <div style="padding: 20px; border-bottom: 2px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-color); font-size: 20px;">Менеджер автосохранений</h3>
            <button type="button" onclick="closeAutosaveManager()" style="background: transparent; border: none; font-size: 28px; color: var(--text-color); cursor: pointer; line-height: 1;">×</button>
        </div>
        <div style="padding: 20px; overflow-y: auto; flex: 1;">
            <div id="autosavesList" style="display: grid; gap: 12px;">
                <!-- Список автосохранений будет загружен динамически -->
            </div>
            <div id="autosavesEmpty" style="display: none; text-align: center; padding: 40px; color: var(--text-color); opacity: 0.5;">
                <p>Нет автосохранений</p>
                <p style="font-size: 14px; margin-top: 10px;">Автосохранения появятся здесь после включения функции автосохранения</p>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно обновления системы -->
<div id="systemUpdateModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; box-shadow: 0 4px 20px rgba(0,0,0,0.3); overflow: hidden; display: flex; flex-direction: column;">
        <div style="padding: 20px; border-bottom: 2px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-color); font-size: 20px;">Обновление NPBlog</h3>
            <button type="button" onclick="closeSystemUpdateModal()" style="background: transparent; border: none; font-size: 28px; color: var(--text-color); cursor: pointer; line-height: 1;">×</button>
        </div>
        <div style="padding: 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 15px;">
            <div id="systemVersionsInfo" style="background: rgba(0,0,0,0.05); padding: 15px; border-radius: 8px; font-size: 14px; color: var(--text-color); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    Текущая версия: <strong id="currentSysVersion">Загрузка...</strong>
                </div>
                <button type="button" onclick="openRestoreModal()" style="background: transparent; border: 1px solid var(--border-color); padding: 5px 10px; border-radius: 5px; cursor: pointer; color: var(--text-color);">Откат (Rollback)</button>
            </div>
            
            <p style="color: var(--text-color);">Выберите архив .zip с новой версией NPBlog.</p>
            <input type="file" id="systemUpdateInput" accept=".zip" style="display: none;" onchange="handleSystemUpdatePreview()">
            <button type="button" id="systemUpdateBtn" onclick="document.getElementById('systemUpdateInput').click()" class="global-action-btn global-action-btn-primary">Выбрать архив</button>
            <div id="updatePreviewContainer" style="display: none; flex-direction: column; gap: 10px;">
                <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; color: #1565c0; font-size: 14px;">Версия в архиве: <strong id="newSysVersion">Неизвестно</strong></div>
                <h4 style="color: var(--text-color); margin: 0;">Будут заменены следующие файлы:</h4>
                <div id="updateFileList" style="max-height: 150px; overflow-y: auto; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 5px; font-size: 13px; color: var(--text-color);"></div>
                <p style="color: var(--text-color); font-size: 12px; opacity: 0.8;">Ваши статьи, медиафайлы и настройки останутся нетронутыми. Перед обновлением будет создан бекап всего проекта.</p>
                <button type="button" id="startUpdateProcessBtn" onclick="startSystemUpdateProcess()" class="global-action-btn global-action-btn-primary" style="background-color: #d32f2f;">Начать обновление</button>
            </div>
            <div id="updateProgressContainer" style="display: none; flex-direction: column; gap: 10px;">
                <p id="updateStatusText" style="color: var(--text-color); margin: 0; font-weight: bold;">Подготовка...</p>
                <div style="width: 100%; height: 10px; background: rgba(0,0,0,0.1); border-radius: 5px; overflow: hidden;">
                    <div id="updateProgressBar" style="width: 0%; height: 100%; background: #4CAF50; transition: width 0.3s;"></div>
                </div>
            </div>
            <div id="updateSuccessContainer" style="display: none; flex-direction: column; gap: 10px; align-items: center; padding-top: 10px;">
                <p style="color: #4CAF50; font-weight: bold; font-size: 18px; margin: 0;">Обновление успешно завершено!</p>
                <button type="button" onclick="window.location.reload()" class="global-action-btn global-action-btn-primary">Обновить страницу</button>
            </div>
        </div>
    </div>
</div>

<div id="restoreSystemModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10001; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; box-shadow: 0 4px 20px rgba(0,0,0,0.3); overflow: hidden; display: flex; flex-direction: column;">
        <div style="padding: 20px; border-bottom: 2px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-color); font-size: 20px;">Откат системы (Rollback)</h3>
            <button type="button" onclick="closeRestoreModal()" style="background: transparent; border: none; font-size: 28px; color: var(--text-color); cursor: pointer; line-height: 1;">×</button>
        </div>
        <div style="padding: 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 15px;">
            <p style="color: var(--text-color);">Выберите бэкап для восстановления:</p>
            <div id="restoreBackupsList" style="display: flex; flex-direction: column; gap: 10px;">Загрузка списка...</div>
            <div id="restoreProgressContainer" style="display: none; flex-direction: column; gap: 10px; margin-top: 20px;">
                <p style="color: var(--text-color); margin: 0; font-weight: bold;">Восстановление системы... (Пожалуйста, подождите)</p>
            </div>
            <div id="restoreSuccessContainer" style="display: none; flex-direction: column; gap: 10px; align-items: center; padding-top: 10px;">
                <p style="color: #4CAF50; font-weight: bold; font-size: 18px; margin: 0;">Система успешно восстановлена!</p>
                <button type="button" onclick="window.location.reload()" class="global-action-btn global-action-btn-primary">Обновить страницу</button>
            </div>
        </div>
    </div>
</div>

<script>
function openRestoreModal() {
    closeSystemUpdateModal();
    const modal = document.getElementById('restoreSystemModal');
    modal.style.display = 'flex';
    document.getElementById('restoreProgressContainer').style.display = 'none';
    document.getElementById('restoreSuccessContainer').style.display = 'none';
    
    // Load backups
    const list = document.getElementById('restoreBackupsList');
    list.innerHTML = 'Загрузка...';
    
    fetch('restore_system.php?action=list_backups')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                list.innerHTML = '';
                if (data.backups.length === 0) {
                    list.innerHTML = '<p>Бэкапы не найдены.</p>';
                    return;
                }
                data.backups.forEach(b => {
                    const el = document.createElement('div');
                    el.style.cssText = 'padding: 10px; background: rgba(0,0,0,0.05); border-radius: 5px; display: flex; justify-content: space-between; align-items: center;';
                    
                    const dt = new Date(b.time * 1000).toLocaleString();
                    const size = (b.size / 1024 / 1024).toFixed(2) + ' MB';
                    
                    el.innerHTML = `
                        <div style="color: var(--text-color);">
                            <div style="font-weight: bold;">${b.filename}</div>
                            <div style="font-size: 12px; opacity: 0.7;">Создан: ${dt} | Размер: ${size}</div>
                        </div>
                        <button class="global-action-btn global-action-btn-primary" style="background-color: #d32f2f;" onclick="startRestore('${b.filename}')">Восстановить</button>
                    `;
                    list.appendChild(el);
                });
            } else {
                list.innerHTML = '<p style="color: red;">Ошибка: ' + data.error + '</p>';
            }
        });
}

function closeRestoreModal() {
    document.getElementById('restoreSystemModal').style.display = 'none';
}

function startRestore(filename) {
    if (!confirm('Вы уверены? Это перезапишет текущие файлы системы файлами из бэкапа.')) return;
    
    document.getElementById('restoreProgressContainer').style.display = 'flex';
    
    fetch('restore_system.php?action=restore', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ filename: filename })
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('restoreProgressContainer').style.display = 'none';
        if (data.success) {
            document.getElementById('restoreSuccessContainer').style.display = 'flex';
            document.getElementById('restoreBackupsList').style.display = 'none';
        } else {
            alert('Ошибка восстановления: ' + data.error);
        }
    })
    .catch(err => {
        document.getElementById('restoreProgressContainer').style.display = 'none';
        alert('Критическая ошибка при восстановлении');
    });
}

let currentAdditionalPostId = null;
let currentSelectedFont = null;

function openAdditionalSettings(postId, postTitle) {
    currentAdditionalPostId = postId;
    document.getElementById('additionalSettingsPostTitle').textContent = 'Статья: ' + postTitle;
    
    // Загружаем настройки из post_backgrounds.json
    fetch('get_post_backgrounds.php?postId=' + postId)
        .then(response => response.json())
        .then(data => {
            const settings = data.settings || {};
            
            // Устанавливаем режим отображения
            document.getElementById('backgroundMode').value = settings.backgroundMode || 'cover';
            
            // Устанавливаем область фона
            document.getElementById('backgroundScope').value = settings.backgroundScope || 'content';
            
            // Проверяем глобальный фон
            return fetch('data/global-settings.json?t=' + Date.now())
                .then(response => {
                    if (!response.ok) {
                        throw new Error('No global settings');
                    }
                    return response.json();
                })
                .then(globalSettings => {
                    return { settings, globalSettings };
                })
                .catch(() => {
                    return { settings, globalSettings: null };
                });
        })
        .then(({ settings, globalSettings }) => {
            const currentBgInfo = document.getElementById('currentBackgroundInfo');
            const globalBgInfo = document.getElementById('globalBackgroundInfo');
            
            // Отображаем текущий фон статьи если есть
            if (settings.background) {
                const bgPreview = document.getElementById('currentBackgroundPreview');
                const bgName = document.getElementById('currentBackgroundName');
                const bgMode = document.getElementById('currentBackgroundMode');
                
                bgPreview.src = '/data/backgrounds/' + settings.background;
                bgName.textContent = settings.background;
                
                const modeText = {
                    'cover': 'Растянуть',
                    'contain': 'По размеру',
                    'repeat': 'Замостить'
                };
                const scopeText = {
                    'content': 'Только статья',
                    'fullpage': 'Вся страница'
                };
                bgMode.textContent = 'Режим: ' + (modeText[settings.backgroundMode] || 'Растянуть') + ' | Область: ' + (scopeText[settings.backgroundScope] || 'Только статья');
                
                currentBgInfo.style.display = 'block';
                globalBgInfo.style.display = 'none';
            } else if (globalSettings && globalSettings.background) {
                // Показываем глобальный фон если у статьи нет своего
                const bgPreview = document.getElementById('globalBackgroundPreview');
                const bgName = document.getElementById('globalBackgroundName');
                const bgMode = document.getElementById('globalBackgroundModeText');
                
                bgPreview.src = '/data/backgrounds/' + globalSettings.background;
                bgName.textContent = globalSettings.background;
                
                const modeText = {
                    'cover': 'Растянуть',
                    'contain': 'По размеру',
                    'repeat': 'Замостить'
                };
                const scopeText = {
                    'content': 'Только статья',
                    'fullpage': 'Вся страница'
                };
                bgMode.textContent = 'Режим: ' + (modeText[globalSettings.backgroundMode] || 'Растянуть') + ' | Область: ' + (scopeText[globalSettings.backgroundScope] || 'Только статья');
                
                globalBgInfo.style.display = 'block';
                currentBgInfo.style.display = 'none';
                
                // Устанавливаем значения из глобальных настроек
                document.getElementById('backgroundMode').value = globalSettings.backgroundMode || 'cover';
                document.getElementById('backgroundScope').value = globalSettings.backgroundScope || 'content';
            } else {
                currentBgInfo.style.display = 'none';
                globalBgInfo.style.display = 'none';
            }
            
            // Загружаем настройки подложки
            if (settings.overlayEnabled) {
                document.getElementById('overlayEnabled').checked = true;
                document.getElementById('overlayColor').value = settings.overlayColor || '#ffffff';
                document.getElementById('overlayOpacity').value = settings.overlayOpacity || 90;
                document.getElementById('overlayOpacityValue').textContent = (settings.overlayOpacity || 90) + '%';
                document.getElementById('overlaySettings').style.display = 'block';
            } else {
                document.getElementById('overlayEnabled').checked = false;
                document.getElementById('overlayColor').value = '#ffffff';
                document.getElementById('overlayOpacity').value = 90;
                document.getElementById('overlayOpacityValue').textContent = '90%';
                document.getElementById('overlaySettings').style.display = 'none';
            }
        })
        .catch(() => {
            document.getElementById('backgroundMode').value = 'cover';
            document.getElementById('backgroundScope').value = 'content';
            document.getElementById('currentBackgroundInfo').style.display = 'none';
            document.getElementById('globalBackgroundInfo').style.display = 'none';
            document.getElementById('overlayEnabled').checked = false;
            document.getElementById('overlaySettings').style.display = 'none';
        });
    
    const modal = document.getElementById('additionalSettingsModal');
    modal.style.display = 'flex';
    
    // Запускаем анимацию после небольшой задержки
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function closeAdditionalSettings() {
    const modal = document.getElementById('additionalSettingsModal');
    modal.classList.remove('show');
    
    // Скрываем модальное окно после завершения анимации
    setTimeout(() => {
        modal.style.display = 'none';
        document.getElementById('backgroundInput').value = '';
        currentAdditionalPostId = null;
    }, 300);
}

function uploadBackground() {
    const fileInput = document.getElementById('backgroundInput');
    const file = fileInput.files[0];
    const mode = document.getElementById('backgroundMode').value;
    const scope = document.getElementById('backgroundScope').value;
    
    if (!file) {
        showAlert('Выберите файл');
        return;
    }
    
    const formData = new FormData();
    formData.append('background', file);
    formData.append('postId', currentAdditionalPostId);
    formData.append('mode', mode);
    formData.append('scope', scope);
    
    fetch('upload_background.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Фон успешно загружен');
            fileInput.value = '';
            
            // Обновляем отображение текущего фона
            const bgPreview = document.getElementById('currentBackgroundPreview');
            const bgName = document.getElementById('currentBackgroundName');
            const bgMode = document.getElementById('currentBackgroundMode');
            const currentBgInfo = document.getElementById('currentBackgroundInfo');
            
            bgPreview.src = '/data/backgrounds/' + data.filename;
            bgName.textContent = data.filename;
            
            const modeText = {
                'cover': 'Растянуть',
                'contain': 'По размеру',
                'repeat': 'Замостить'
            };
            const scopeText = {
                'content': 'Только статья',
                'fullpage': 'Вся страница'
            };
            bgMode.textContent = 'Режим: ' + (modeText[mode] || 'Растянуть') + ' | Область: ' + (scopeText[scope] || 'Только статья');
            
            currentBgInfo.style.display = 'block';
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка загрузки фона');
    });
}

function removeBackground() {
    showConfirm('Вернуть стандартный фон?').then(result => {
        if (!result) return;
        
        fetch('remove_background.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ postId: currentAdditionalPostId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.globalApplied) {
                showAlert('Индивидуальный фон удален. Применен глобальный фон.');
            } else {
                showAlert('Фон удален');
            }
            
            // Перезагружаем настройки чтобы показать глобальный фон если он есть
            closeAdditionalSettings();
            // Небольшая задержка перед повторным открытием
            setTimeout(() => {
                // Находим название статьи
                fetch('data/blog/posts-meta.json')
                    .then(response => response.json())
                    .then(meta => {
                        const post = meta.find(p => p.id === currentAdditionalPostId);
                        if (post) {
                            openAdditionalSettings(currentAdditionalPostId, post.title);
                        }
                    });
            }, 100);
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка удаления фона');
    });
    });
}

function toggleOverlaySettings() {
    const enabled = document.getElementById('overlayEnabled').checked;
    const settings = document.getElementById('overlaySettings');
    settings.style.display = enabled ? 'block' : 'none';
}

function updateOpacityValue() {
    const value = document.getElementById('overlayOpacity').value;
    document.getElementById('overlayOpacityValue').textContent = value + '%';
}

function saveOverlaySettings() {
    const enabled = document.getElementById('overlayEnabled').checked;
    const color = document.getElementById('overlayColor').value;
    const opacity = document.getElementById('overlayOpacity').value;
    
    const data = {
        postId: currentAdditionalPostId,
        overlayEnabled: enabled,
        overlayColor: color,
        overlayOpacity: opacity
    };
    
    fetch('save_overlay_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Настройки подложки сохранены');
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка сохранения настроек');
    });
}

// Глобальные параметры
function openGlobalSettings() {
    const modal = document.getElementById('globalSettingsModal');
    modal.style.display = 'flex';
    
    // Запускаем анимацию после небольшой задержки
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    loadGlobalBackground();
}

function closeGlobalSettings() {
    const modal = document.getElementById('globalSettingsModal');
    modal.classList.remove('show');
    
    // Скрываем модальное окно после завершения анимации
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function showGlobalSection(sectionName) {
    // Обновляем активную кнопку навигации
    document.querySelectorAll('.global-nav-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.section === sectionName) {
            btn.classList.add('active');
        }
    });
    
    // Показываем нужную секцию
    document.querySelectorAll('.global-section').forEach(section => {
        section.style.display = 'none';
    });
    document.getElementById('globalSection-' + sectionName).style.display = 'block';
    
    // Обновляем заголовок
    const titles = {
        'backgrounds': 'Фон статей',
        'blogview': 'Вид blog.html',
        'autosave': 'Автосохранение',
        'appearance': 'Внешний вид',
        'experimental': 'Экспериментальные функции',
        'rss': 'Интеграция RSS (Виджет)'
    };
    document.getElementById('globalSectionTitle').textContent = titles[sectionName] || '';
    
    // Загружаем настройки для секции
    if (sectionName === 'blogview') {
        loadBlogViewSettings();
    } else if (sectionName === 'autosave') {
        loadAutosaveSettings();
    } else if (sectionName === 'appearance') {
        loadAppearanceSettings();
    } else if (sectionName === 'experimental') {
        loadExperimentalSettings();
    } else if (sectionName === 'rss') {
        loadRssSection();
    }
}

function loadRssSection() {
    // 1. Путь к папке блога относительно главной страницы сайта
    var blogPath = "data/blog/";
    
    // 2. Генерируем HTML код
    var htmlCode = '<!-- Контейнер виджета RSS ленты NPBlog -->\n<div id="npblog-rss-ticker"></div>';
    document.getElementById('rssHtmlCode').value = htmlCode;
    
    // 3. Генерируем чистый JS код без заготовленных стилей
    var jsCode = '<script>\n' +
        '(function() {\n' +
        '    // Путь к вашей папке блога относительно главной страницы\n' +
        '    var blogPath = "' + blogPath + '";\n\n' +
        '    fetch(blogPath + "posts-meta.json?t=" + Date.now())\n' +
        '        .then(function(response) {\n' +
        '            if (!response.ok) throw new Error("HTTP error " + response.status);\n' +
        '            return response.json();\n' +
        '        })\n' +
        '        .then(function(posts) {\n' +
        '            if (!posts || posts.length === 0) return;\n\n' +
        '            // Сортируем статьи по ID по убыванию, чтобы получить самую свежую\n' +
        '            posts.sort(function(a, b) { return b.id - a.id; });\n' +
        '            var latestPost = posts[0];\n' +
        '            if (!latestPost) return;\n\n' +
        '            var tickerContainer = document.getElementById("npblog-rss-ticker");\n' +
        '            if (!tickerContainer) return;\n\n' +
        '            // Создаем чистую ссылку с названием новой статьи без инлайновых стилей\n' +
        '            var link = document.createElement("a");\n' +
        '            link.href = blogPath + latestPost.filename;\n' +
        '            link.className = "npblog-rss-link";\n' +
        '            link.textContent = "Вышла новая статья: " + latestPost.title;\n\n' +
        '            tickerContainer.appendChild(link);\n' +
        '        })\n' +
        '        .catch(function(err) {\n' +
        '            console.error("NPBlog RSS Ticker error:", err);\n' +
        '        });\n' +
        '})();\n' +
        '<\/script>';
    document.getElementById('rssJsCode').value = jsCode;
    
    // 4. Отрисовываем чистый предпросмотр в админке
    var previewContainer = document.getElementById('rssLivePreviewContainer');
    previewContainer.innerHTML = '<div style="font-size: 14px; color: var(--text-color); opacity: 0.6; font-style: italic;">Загрузка данных...</div>';
    
    fetch('data/blog/posts-meta.json?t=' + Date.now())
        .then(response => response.json())
        .then(posts => {
            if (!posts || posts.length === 0) {
                previewContainer.innerHTML = '<div style="font-size: 14px; color: #f44336; font-weight: 500;">Нет опубликованных статей для вывода в виджет</div>';
                return;
            }
            
            posts.sort((a, b) => b.id - a.id);
            var latestPost = posts[0];
            
            previewContainer.innerHTML = '';
            
            var link = document.createElement('a');
            link.href = 'data/blog/' + latestPost.filename;
            link.target = '_blank';
            link.className = 'npblog-rss-link';
            link.textContent = 'Вышла новая статья: ' + latestPost.title;
            
            // Простые дефолтные стили ссылок браузера для чистого превью
            link.style.color = '#3b82f6';
            link.style.textDecoration = 'underline';
            link.style.cursor = 'pointer';
            link.style.fontSize = '14px';
            
            previewContainer.appendChild(link);
        })
        .catch(err => {
            console.error(err);
            previewContainer.innerHTML = '<div style="font-size: 14px; color: #f44336; font-weight: 500;">Ошибка загрузки превью виджета</div>';
        });
}

function copyToClipboard(elementId, btnElement) {
    var textarea = document.getElementById(elementId);
    if (!textarea) return;
    
    textarea.select();
    textarea.setSelectionRange(0, 99999); // Для мобильных устройств
    
    try {
        navigator.clipboard.writeText(textarea.value).then(function() {
            var originalText = btnElement.textContent;
            btnElement.textContent = 'Скопировано! ✓';
            btnElement.style.background = '#4CAF50';
            
            setTimeout(function() {
                btnElement.textContent = originalText;
                btnElement.style.background = '';
            }, 2000);
        });
    } catch (err) {
        // Резервный способ
        document.execCommand('copy');
        var originalText = btnElement.textContent;
        btnElement.textContent = 'Скопировано! ✓';
        btnElement.style.background = '#4CAF50';
        
        setTimeout(function() {
            btnElement.textContent = originalText;
            btnElement.style.background = '';
        }, 2000);
    }
}

function loadGlobalBackground() {
    fetch('data/global-settings.json?t=' + Date.now())
        .then(response => {
            if (!response.ok) {
                // Файл не существует
                throw new Error('File not found');
            }
            return response.json();
        })
        .then(settings => {
            if (settings && settings.background) {
                document.getElementById('globalBackgroundMode').value = settings.backgroundMode || 'cover';
                document.getElementById('globalBackgroundScope').value = settings.backgroundScope || 'content';
                
                const bgPreview = document.getElementById('currentGlobalBackgroundPreview');
                const bgName = document.getElementById('currentGlobalBackgroundName');
                const bgMode = document.getElementById('currentGlobalBackgroundMode');
                const currentBgInfo = document.getElementById('currentGlobalBackgroundInfo');
                
                bgPreview.src = '/data/backgrounds/' + settings.background;
                bgName.textContent = settings.background;
                
                const modeText = {
                    'cover': 'Растянуть',
                    'contain': 'По размеру',
                    'repeat': 'Замостить'
                };
                const scopeText = {
                    'content': 'Только статья',
                    'fullpage': 'Вся страница'
                };
                bgMode.textContent = 'Режим: ' + (modeText[settings.backgroundMode] || 'Растянуть') + ' | Область: ' + (scopeText[settings.backgroundScope] || 'Только статья');
                
                currentBgInfo.style.display = 'block';
            } else {
                document.getElementById('currentGlobalBackgroundInfo').style.display = 'none';
                // Устанавливаем значения по умолчанию
                document.getElementById('globalBackgroundMode').value = 'cover';
                document.getElementById('globalBackgroundScope').value = 'content';
            }
        })
        .catch(() => {
            // Файл не существует или произошла ошибка
            document.getElementById('currentGlobalBackgroundInfo').style.display = 'none';
            // Устанавливаем значения по умолчанию
            document.getElementById('globalBackgroundMode').value = 'cover';
            document.getElementById('globalBackgroundScope').value = 'content';
        });
}

function uploadGlobalBackground() {
    const fileInput = document.getElementById('globalBackgroundInput');
    const file = fileInput.files[0];
    const mode = document.getElementById('globalBackgroundMode').value;
    const scope = document.getElementById('globalBackgroundScope').value;
    
    if (!file) {
        showAlert('Выберите файл');
        return;
    }
    
    const formData = new FormData();
    formData.append('background', file);
    formData.append('mode', mode);
    formData.append('scope', scope);
    
    fetch('upload_global_background.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Глобальный фон успешно загружен и применен ко всем статьям');
            fileInput.value = '';
            loadGlobalBackground();
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка загрузки фона');
    });
}

function removeGlobalBackground() {
    showConfirm('Удалить глобальный фон из всех статей?').then(result => {
        if (!result) return;
        
        fetch('remove_global_background.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Глобальный фон удален');
            loadGlobalBackground();
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка удаления фона');
    });
    });
}

function updateBackgroundStyles() {
    showConfirm('Обновить стили фона во всех статьях? Это применит новые отступы padding к существующим статьям.').then(result => {
        if (!result) return;
        
        fetch('update_background_styles.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Стили обновлены в ' + data.updated + ' статьях');
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка обновления стилей');
    });
    });
}

// Функции для модальных уведомлений
let notificationCallback = null;

function showAlert(message, title = 'Уведомление') {
    return new Promise((resolve) => {
        const modal = document.getElementById('notificationModal');
        const titleEl = document.getElementById('notificationTitle');
        const messageEl = document.getElementById('notificationMessage');
        const cancelBtn = document.getElementById('notificationCancelBtn');
        const okBtn = document.getElementById('notificationOkBtn');
        
        titleEl.textContent = title;
        messageEl.textContent = message;
        cancelBtn.style.display = 'none';
        okBtn.textContent = 'OK';
        
        notificationCallback = resolve;
        modal.style.display = 'flex';
        
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
    });
}

function showConfirm(message, title = 'Подтверждение') {
    return new Promise((resolve) => {
        const modal = document.getElementById('notificationModal');
        const titleEl = document.getElementById('notificationTitle');
        const messageEl = document.getElementById('notificationMessage');
        const cancelBtn = document.getElementById('notificationCancelBtn');
        const okBtn = document.getElementById('notificationOkBtn');
        
        titleEl.textContent = title;
        messageEl.textContent = message;
        cancelBtn.style.display = 'inline-block';
        okBtn.textContent = 'Подтвердить';
        
        notificationCallback = resolve;
        modal.style.display = 'flex';
        
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
    });
}

function closeNotificationModal(result) {
    const modal = document.getElementById('notificationModal');
    modal.classList.remove('show');
    
    setTimeout(() => {
        modal.style.display = 'none';
        if (notificationCallback) {
            notificationCallback(result);
            notificationCallback = null;
        }
    }, 300);
}

// Функции для настроек вида blog.html
function loadBlogViewSettings() {
    fetch('data/blog-view-settings.json?t=' + Date.now())
        .then(response => {
            if (!response.ok) {
                throw new Error('Settings not found');
            }
            return response.json();
        })
        .then(settings => {
            document.getElementById('blogPageTitle').value = settings.title || 'Блог';
            
            // Загружаем инфо о текущем фоне
            const bgInfo = document.getElementById('currentBlogBackgroundInfo');
            if (settings.background) {
                document.getElementById('currentBlogBackgroundPreview').src = 'data/backgrounds/' + settings.background + '?t=' + Date.now();
                document.getElementById('currentBlogBackgroundName').textContent = 'Имя файла: ' + settings.background;
                
                let modeText = 'Режим: ';
                if (settings.backgroundMode === 'cover') modeText += 'Растянуть (cover)';
                else if (settings.backgroundMode === 'contain') modeText += 'По размеру (contain)';
                else if (settings.backgroundMode === 'repeat') modeText += 'Замостить (repeat)';
                
                document.getElementById('currentBlogBackgroundMode').textContent = modeText;
                document.getElementById('blogBackgroundMode').value = settings.backgroundMode || 'cover';
                bgInfo.style.display = 'block';
            } else {
                bgInfo.style.display = 'none';
            }
        })
        .catch(() => {
            document.getElementById('blogPageTitle').value = 'Блог';
            document.getElementById('currentBlogBackgroundInfo').style.display = 'none';
        });
}

function uploadBlogBackground() {
    const fileInput = document.getElementById('blogBackgroundInput');
    const file = fileInput.files[0];
    const mode = document.getElementById('blogBackgroundMode').value;
    
    if (!file) {
        showAlert('Выберите файл');
        return;
    }
    
    const formData = new FormData();
    formData.append('background', file);
    formData.append('mode', mode);
    
    fetch('upload_blog_background.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Фон для blog.html успешно загружен и применен');
            fileInput.value = '';
            loadBlogViewSettings();
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка загрузки фона');
    });
}

function removeBlogBackground() {
    showConfirm('Удалить фон со страницы blog.html?').then(result => {
        if (!result) return;
        
        fetch('remove_blog_background.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Фон удален со страницы blog.html');
                loadBlogViewSettings();
            } else {
                showAlert('Ошибка: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert('Ошибка удаления фона');
        });
    });
}

// Переменные для автосохранения
let autosaveCountdownTimer = null;
let autosaveEnabled = false;
let autosaveInterval = 60;
let autosaveCountdown = 0;

// Функции для автосохранения
function loadAutosaveSettings() {
    loadAndApplyAllSettings();
}

function saveAutosaveSettings() {
    const enabled = document.getElementById('autosaveEnabled').checked;
    const interval = parseInt(document.getElementById('autosaveInterval').value);
    
    if (interval < 10 || interval > 600) {
        showAlert('Интервал должен быть от 10 до 600 секунд');
        return;
    }
    
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ autosaveEnabled: enabled, autosaveInterval: interval })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAndApplyAllSettings();
            showAlert('Настройки автосохранения сохранены');
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка сохранения настроек');
    });
}

function startAutosave() {
    stopAutosave(); // Останавливаем предыдущий таймер если есть
    
    autosaveCountdown = autosaveInterval;
    updateAutosaveBadge();
    
    // Единый таймер обратного отсчета
    autosaveCountdownTimer = setInterval(() => {
        // Проверяем наличие контента
        if (!hasEditorContent()) {
            // Если контента нет, сбрасываем таймер
            autosaveCountdown = autosaveInterval;
            updateAutosaveBadge();
            return;
        }
        
        autosaveCountdown--;
        updateAutosaveBadge();
        
        if (autosaveCountdown <= 0) {
            // Выполняем автосохранение
            performAutosave();
            // Сбрасываем счетчик
            autosaveCountdown = autosaveInterval;
            updateAutosaveBadge();
        }
    }, 1000);
    
    document.getElementById('autosaveBadge').style.display = 'block';
}

function hasEditorContent() {
    const title = document.getElementById('title').value.trim();
    const content = editorMode === 'visual' 
        ? document.getElementById('contentVisual').innerHTML.trim()
        : document.getElementById('content').value.trim();
    
    // Проверяем, есть ли заголовок или контент (не считая пустые теги)
    const hasTitle = title.length > 0;
    const hasContent = content.length > 0 && content !== '<br>' && content !== '<div><br></div>';
    
    return hasTitle || hasContent;
}

function stopAutosave() {
    if (autosaveCountdownTimer) {
        clearInterval(autosaveCountdownTimer);
        autosaveCountdownTimer = null;
    }
    
    document.getElementById('autosaveBadge').style.display = 'none';
}

function updateAutosaveBadge() {
    const badge = document.getElementById('autosaveBadgeText');
    if (badge) {
        if (hasEditorContent()) {
            badge.textContent = `Автосохранение через ${autosaveCountdown}с`;
        } else {
            badge.textContent = 'Ожидание контента...';
        }
    }
}

function performAutosave() {
    const title = document.getElementById('title').value.trim();
    const content = editorMode === 'visual' 
        ? document.getElementById('contentVisual').innerHTML 
        : document.getElementById('content').value;
    
    if (!title && !content) {
        return; // Нечего сохранять
    }
    
    const formData = new FormData();
    formData.append('title', title);
    formData.append('content', content);
    
    fetch('save_autosave.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const text = await response.text();
        if (!text) throw new Error('Сервер вернул пустой ответ (0 байт). Возможно, ошибка PHP (без вывода ошибок) или блокировка Nginx.');
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Невалидный JSON от сервера. Ответ:', text);
            throw e;
        }
    })
    .then(data => {
        if (data.success) {
            console.log('Автосохранение выполнено');
            // Можно показать небольшое уведомление
            showNotification('Автосохранение выполнено', 'success');
        }
    })
    .catch(error => {
        console.error('Ошибка автосохранения:', error);
    });
}

function checkAutosaveExists() {
    // Эта функция сохранена для совместимости,
    // но больше не отображает элемент autosaveInfo, так как он был удален.
}

function toggleAutosavePreview() {
    // Эта функция вызывается при изменении чекбокса
    // Можно добавить дополнительную логику если нужно
}

// Функции менеджера автосохранений
function openAutosaveManager() {
    const modal = document.getElementById('autosaveManagerModal');
    modal.style.display = 'flex';
    
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    loadAutosavesList();
}

function closeAutosaveManager() {
    const modal = document.getElementById('autosaveManagerModal');
    modal.classList.remove('show');
    
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function loadAutosavesList() {
    fetch('get_all_autosaves.php')
        .then(response => response.json())
        .then(data => {
            const listDiv = document.getElementById('autosavesList');
            const emptyDiv = document.getElementById('autosavesEmpty');
            
            if (data.success && data.autosaves && data.autosaves.length > 0) {
                listDiv.innerHTML = '';
                listDiv.style.display = 'block';
                emptyDiv.style.display = 'none';
                
                const groupedAutosaves = {};
                data.autosaves.forEach(autosave => {
                    const title = autosave.title || 'Без названия';
                    if (!groupedAutosaves[title]) {
                        groupedAutosaves[title] = [];
                    }
                    groupedAutosaves[title].push(autosave);
                });
                
                let html = '';
                let groupIndex = 0;
                for (const title in groupedAutosaves) {
                    const saves = groupedAutosaves[title];
                    const safeTitle = escapeHtml(title);
                    
                    html += `
                        <div class="backup-post-group" id="autosave-group-${groupIndex}">
                            <div class="backup-post-header" onclick="toggleAutosaveGroup(${groupIndex})">
                                <h3 class="backup-post-title">${safeTitle}</h3>
                                <span class="backup-post-toggle">▼</span>
                            </div>
                            <div class="backup-list">
                                ${saves.map(autosave => {
                                    const dateObj = new Date(autosave.timestamp * 1000);
                                    const dateStr = dateObj.toLocaleString('ru-RU', {
                                        day: '2-digit', month: '2-digit', year: 'numeric',
                                        hour: '2-digit', minute: '2-digit'
                                    });
                                    return `
                                        <div class="backup-item">
                                            <div class="backup-info">
                                                <div class="backup-number">Автосохранение</div>
                                                <div class="backup-date">${escapeHtml(dateStr)}</div>
                                            </div>
                                            <div class="backup-actions">
                                                <button class="backup-btn" onclick="loadAutosaveById('${autosave.id}')">Загрузить</button>
                                                <button class="backup-btn" onclick="deleteAutosaveById('${autosave.id}')" style="color: #dc3545; border-color: rgba(220, 53, 69, 0.3);">Удалить</button>
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    `;
                    groupIndex++;
                }
                listDiv.innerHTML = html;
            } else {
                listDiv.style.display = 'none';
                emptyDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки автосохранений:', error);
            document.getElementById('autosavesList').style.display = 'none';
            document.getElementById('autosavesEmpty').style.display = 'block';
        });
}

function toggleAutosaveGroup(index) {
    const group = document.getElementById('autosave-group-' + index);
    if (group) {
        group.classList.toggle('expanded');
    }
}

function loadAutosaveById(id) {
    fetch('get_autosave.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.autosave) {
            document.getElementById('title').value = data.autosave.title || '';
            
            if (editorMode === 'visual') {
                document.getElementById('contentVisual').innerHTML = data.autosave.content || '';
            } else {
                document.getElementById('content').value = data.autosave.content || '';
            }
            
            showNotification('Автосохранение загружено', 'success');
            closeAutosaveManager();
            closeGlobalSettings();
        } else {
            showAlert('Ошибка загрузки автосохранения');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка при загрузке автосохранения');
    });
}

function deleteAutosaveById(id) {
    showConfirm('Удалить это автосохранение?').then(result => {
        if (!result) return;
        
        fetch('delete_autosave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Автосохранение удалено', 'success');
                loadAutosavesList();
                checkAutosaveExists();
            } else {
                showAlert('Ошибка: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert('Ошибка при удалении автосохранения');
        });
    });
}

function saveBlogViewSettings() {
    const title = document.getElementById('blogPageTitle').value.trim() || 'Блог';
    
    fetch('save_blog_view_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ title: title })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Настройки сохранены! Изменения применятся при следующем обновлении списка статей.');
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка сохранения настроек');
    });
}

// Единая функция загрузки и применения всех настроек редактора
function loadAndApplyAllSettings() {
    fetch('get_editor_settings.php?t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success && data.settings) {
                const settings = data.settings;
                
                // 1. Автосохранение
                autosaveEnabled = settings.autosaveEnabled || false;
                autosaveInterval = settings.autosaveInterval || 60;
                
                const autosaveEnabledCheck = document.getElementById('autosaveEnabled');
                const autosaveIntervalInput = document.getElementById('autosaveInterval');
                if (autosaveEnabledCheck) autosaveEnabledCheck.checked = autosaveEnabled;
                if (autosaveIntervalInput) autosaveIntervalInput.value = autosaveInterval;
                
                if (autosaveEnabled) {
                    startAutosave();
                } else {
                    stopAutosave();
                }
                checkAutosaveExists();
                
                // 2. Внешний вид и экспериментальные функции
                const hideModeButtons = settings.hideEditorModeButtons || false;
                const amoledTheme = settings.amoledTheme || false;
                const enableUndoRedo = settings.enableUndoRedo || false;
                
                const hideModeCheck = document.getElementById('hideEditorModeButtons');
                const amoledCheck = document.getElementById('amoledTheme');
                const enableUndoRedoCheck = document.getElementById('enableUndoRedo');
                if (hideModeCheck) hideModeCheck.checked = hideModeButtons;
                if (amoledCheck) amoledCheck.checked = amoledTheme;
                if (enableUndoRedoCheck) enableUndoRedoCheck.checked = enableUndoRedo;
                
                window.amoledThemeEnabled = amoledTheme;
                if (typeof updateAmoledState === 'function') {
                    updateAmoledState();
                }
                
                // Переключение отображения переключателя режимов и разделителей
                const modeToggle = document.getElementById('headerModeToggle');
                const logoDivider = document.getElementById('logoDivider');
                if (modeToggle) {
                    if (hideModeButtons) {
                        modeToggle.style.display = 'none';
                        if (logoDivider) logoDivider.style.display = 'none';
                        if (typeof setMode === 'function') {
                            setMode('visual');
                        }
                    } else {
                        modeToggle.style.display = 'flex';
                        if (logoDivider) logoDivider.style.display = '';
                    }
                }
                
                // Переключение отображения кнопок истории (undo/redo) и разделителя
                const editorActions = document.getElementById('headerEditorActions');
                const modeActionsDivider = document.getElementById('modeActionsDivider');
                if (editorActions) {
                    if (enableUndoRedo) {
                        editorActions.style.display = 'flex';
                        if (modeActionsDivider) modeActionsDivider.style.display = '';
                        
                        const undoBtn = document.getElementById('undoBtn');
                        const redoBtn = document.getElementById('redoBtn');
                        if (undoBtn) undoBtn.style.display = '';
                        if (redoBtn) redoBtn.style.display = '';
                    } else {
                        editorActions.style.display = 'none';
                        if (modeActionsDivider) modeActionsDivider.style.display = 'none';
                    }
                }
                
                // Управление разделителем перед панелью форматирования
                const actionsFormattingDivider = document.getElementById('actionsFormattingDivider');
                if (actionsFormattingDivider) {
                    if (!hideModeButtons || enableUndoRedo) {
                        actionsFormattingDivider.style.display = '';
                    } else {
                        actionsFormattingDivider.style.display = 'none';
                    }
                }
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки настроек редактора:', error);
        });
}

// Функции для настроек внешнего вида
function loadAppearanceSettings() {
    loadAndApplyAllSettings();
}

function saveAppearanceSettings() {
    const hideButtons = document.getElementById('hideEditorModeButtons').checked;
    const amoled = document.getElementById('amoledTheme').checked;
    
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            hideEditorModeButtons: hideButtons,
            amoledTheme: amoled
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAndApplyAllSettings();
            showAlert('Настройки внешнего вида сохранены!');
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка сохранения настроек');
    });
}

function applyAppearanceSettings() {
    loadAndApplyAllSettings();
}

// Функции для экспериментальных настроек
function loadExperimentalSettings() {
    loadAndApplyAllSettings();
}

function saveExperimentalSettings() {
    const enableUndoRedo = document.getElementById('enableUndoRedo').checked;
    
    fetch('save_editor_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ enableUndoRedo: enableUndoRedo })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAndApplyAllSettings();
            showAlert('Экспериментальные настройки сохранены!');
        } else {
            showAlert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Ошибка сохранения настроек');
    });
}

function applyExperimentalSettings() {
    loadAndApplyAllSettings();
}

// Функции для работы с пользовательскими шрифтами
function openCustomFontsModal() {
    const modal = document.getElementById('customFontsModal');
    modal.style.display = 'flex';
    
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    loadCustomFonts();
}

function closeCustomFontsModal() {
    const modal = document.getElementById('customFontsModal');
    modal.classList.remove('show');
    
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function uploadFontFile() {
    const input = document.getElementById('fontUploadInput');
    const file = input.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('fontFile', file);
    
    fetch('upload_font.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Шрифт успешно загружен', 'success');
            loadCustomFonts(); // Refresh list
            
            // Reload custom fonts globally
            const styleElement = document.getElementById('customFontsStyle');
            if (styleElement) {
                fetch('get_custom_fonts_css.php?t=' + Date.now())
                    .then(r => r.text())
                    .then(css => {
                        styleElement.textContent = css;
                    });
            }
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
        input.value = ''; // Reset file input
    })
    .catch(error => {
        console.error('Ошибка загрузки шрифта:', error);
        showNotification('Ошибка при загрузке шрифта', 'error');
        input.value = '';
    });
}

function loadCustomFonts() {
    fetch('get_custom_fonts.php')
        .then(response => response.json())
        .then(data => {
            const fontsList = document.getElementById('customFontsList');
            const fontsEmpty = document.getElementById('customFontsEmpty');
            
            if (data.success && data.fonts.length > 0) {
                fontsList.innerHTML = '';
                fontsEmpty.style.display = 'none';
                fontsList.style.display = 'grid';
                
                // Создаём @font-face правила для каждого шрифта
                let styleTag = document.getElementById('customFontsStyles');
                if (!styleTag) {
                    styleTag = document.createElement('style');
                    styleTag.id = 'customFontsStyles';
                    document.head.appendChild(styleTag);
                }
                
                let fontFaceRules = '';
                
                data.fonts.forEach(font => {
                    // Определяем формат шрифта
                    let format = 'truetype';
                    if (font.format === 'woff') format = 'woff';
                    else if (font.format === 'woff2') format = 'woff2';
                    else if (font.format === 'otf') format = 'opentype';
                    
                    // Добавляем @font-face правило
                    fontFaceRules += `
                        @font-face {
                            font-family: '${font.name}';
                            src: url('${font.path}') format('${format}');
                        }
                    `;
                    
                    // Создаём кнопку для шрифта
                    const fontBtn = document.createElement('button');
                    fontBtn.type = 'button';
                    fontBtn.className = 'font-family-item';
                    fontBtn.style.fontFamily = `'${font.name}'`;
                    fontBtn.style.padding = '14px 16px';
                    fontBtn.style.fontSize = '16px';
                    fontBtn.textContent = font.name;
                    fontBtn.onclick = () => applyCustomFont(font.name);
                    
                    fontsList.appendChild(fontBtn);
                });
                
                styleTag.textContent = fontFaceRules;
            } else {
                fontsList.style.display = 'none';
                fontsEmpty.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки шрифтов:', error);
        });
}

function applyCustomFont(fontName) {
    currentSelectedFont = fontName;
    
    // Обновляем текст кнопки
    const fontBtn = document.getElementById('fontFamilyBtn');
    if (fontBtn) {
        fontBtn.textContent = fontName;
        fontBtn.style.fontFamily = fontName;
    }
    
    // Применяем шрифт
    setFontFamily(fontName);
    
    // Закрываем модальное окно
    closeCustomFontsModal();
    
    // Закрываем popover шрифтов
    const wrap = document.getElementById('fontFamilyWrapMain');
    if (wrap) {
        wrap.classList.remove('is-open');
    }
}

// Система обновления NPBlog
let updateToken = '';
let updateRootFolder = '';

function openSystemUpdateModal() {
    const modal = document.getElementById('systemUpdateModal');
    modal.style.display = 'flex';
    
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    document.getElementById('updatePreviewContainer').style.display = 'none';
    document.getElementById('updateProgressContainer').style.display = 'none';
    document.getElementById('updateSuccessContainer').style.display = 'none';
    document.getElementById('systemUpdateBtn').style.display = 'block';
    document.getElementById('systemUpdateInput').value = '';
    
    // Fetch current version if version.json exists
    fetch('version.json?t=' + Date.now())
        .then(response => {
            if (!response.ok) throw new Error('version.json not found');
            return response.json();
        })
        .then(data => {
            if (data && data.version) {
                document.getElementById('currentSysVersion').textContent = data.version;
            } else {
                document.getElementById('currentSysVersion').textContent = 'Неизвестно';
            }
        })
        .catch(() => {
            document.getElementById('currentSysVersion').textContent = 'Не найдена (вероятно < 2.174)';
        });
    
    
    // Закрываем меню, если оно открыто
    const menuWrap = document.getElementById('editorMenuWrap');
    if (menuWrap && menuWrap.classList.contains('is-open')) {
        menuWrap.classList.remove('is-open');
    }
}

function closeSystemUpdateModal() {
    const modal = document.getElementById('systemUpdateModal');
    modal.classList.remove('show');
    
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function handleSystemUpdatePreview() {
    const input = document.getElementById('systemUpdateInput');
    const file = input.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('updateFile', file);

    document.getElementById('systemUpdateBtn').style.display = 'none';
    document.getElementById('updateProgressContainer').style.display = 'flex';
    document.getElementById('updateStatusText').textContent = 'Анализ архива...';
    document.getElementById('updateProgressBar').style.width = '30%';

    fetch('update_system.php?action=preview', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('updateProgressContainer').style.display = 'none';
        
            if (data.success) {
            updateToken = data.token;
            updateRootFolder = data.rootFolder;
            
            document.getElementById('currentSysVersion').textContent = data.currentVersion || 'Неизвестно';
            document.getElementById('newSysVersion').textContent = data.newVersion || 'Неизвестно';
            
            const listContainer = document.getElementById('updateFileList');
            listContainer.innerHTML = '';
            
            if (data.files.length === 0) {
                listContainer.innerHTML = '<p style="color: #d32f2f;">В архиве не найдено подходящих файлов для обновления.</p>';
                document.getElementById('startUpdateProcessBtn').style.display = 'none';
            } else {
                data.files.forEach(f => {
                    const el = document.createElement('div');
                    el.textContent = f;
                    listContainer.appendChild(el);
                });
                document.getElementById('startUpdateProcessBtn').style.display = 'block';
            }
            
            document.getElementById('updatePreviewContainer').style.display = 'flex';
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
            document.getElementById('systemUpdateBtn').style.display = 'block';
            input.value = '';
        }
    })
    .catch(error => {
        console.error('Update preview error:', error);
        showNotification('Ошибка при анализе архива', 'error');
        document.getElementById('updateProgressContainer').style.display = 'none';
        document.getElementById('systemUpdateBtn').style.display = 'block';
        input.value = '';
    });
}

function startSystemUpdateProcess() {
    document.getElementById('updatePreviewContainer').style.display = 'none';
    document.getElementById('updateProgressContainer').style.display = 'flex';
    document.getElementById('updateStatusText').textContent = 'Создание бекапа проекта и обновление файлов... (не закрывайте вкладку)';
    document.getElementById('updateProgressBar').style.width = '70%';

    fetch('update_system.php?action=update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ token: updateToken, rootFolder: updateRootFolder })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('updateProgressBar').style.width = '100%';
            document.getElementById('updateProgressContainer').style.display = 'none';
            document.getElementById('updateSuccessContainer').style.display = 'flex';
        } else {
            document.getElementById('updateProgressContainer').style.display = 'none';
            document.getElementById('systemUpdateBtn').style.display = 'block';
            showNotification('Ошибка обновления: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Update process error:', error);
        document.getElementById('updateProgressContainer').style.display = 'none';
        document.getElementById('systemUpdateBtn').style.display = 'block';
        showNotification('Критическая ошибка при обновлении', 'error');
    });
}
</script>

<!-- Модальное окно Редактора изображений -->
<div id="imageEditorModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 10005; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); border-radius: 16px; max-width: 95vw; width: 1200px; height: 90vh; box-shadow: 0 10px 40px rgba(0,0,0,0.5); overflow: hidden; display: flex; flex-direction: column; border: 1px solid var(--border-color);">
        <!-- Заголовок -->
        <div style="padding: 15px 25px; border-bottom: 2px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.03);">
            <h3 style="margin: 0; color: var(--text-color); font-size: 20px; display: flex; align-items: center; gap: 10px;">
                <span>🎨</span> Редактор изображений
            </h3>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" id="imgEditorUndoBtn" onclick="undoImgEditorState()" class="global-action-btn" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-color); padding: 6px 14px; font-size: 14px; display: flex; align-items: center; gap: 8px;" title="Отменить последнее действие (Ctrl+Z)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
                        <path d="M3 7v6h6" />
                        <path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13" />
                    </svg>
                    Отменить
                </button>
                <button type="button" onclick="saveImgEditorChanges()" class="global-action-btn global-action-btn-primary" style="padding: 6px 18px; font-size: 14px; background: var(--accent-color); color: #fff; border: none; font-weight: bold; border-radius: 6px; display: flex; align-items: center; gap: 6px;">
                    <span>💾</span> Сохранить
                </button>
                <button type="button" onclick="closeImgEditorModal()" style="background: transparent; border: none; font-size: 32px; color: var(--text-color); cursor: pointer; line-height: 1; padding: 0 5px; margin-left: 10px;">×</button>
            </div>
        </div>
        
        <!-- Основная область -->
        <div style="flex: 1; display: flex; overflow: hidden; background: rgba(0,0,0,0.05);">
            <!-- Левая панель инструментов -->
            <div style="width: 260px; border-right: 2px solid var(--border-color); background: var(--bg-color); display: flex; flex-direction: column; gap: 20px; padding: 25px; overflow-y: auto;">
                
                <!-- Инструменты -->
                <div>
                    <h4 style="margin: 0 0 12px 0; color: var(--text-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Инструменты</h4>
                    <div style="display: flex; flex-direction: column; gap: 8px;" id="imgEditorToolsContainer">
                        <button type="button" class="img-editor-tool-btn active" data-tool="pencil" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: left; cursor: pointer; font-weight: 500; width: 100%;">
                            <span style="font-size: 16px;">✏️</span> Карандаш
                        </button>
                        <button type="button" class="img-editor-tool-btn" data-tool="line" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: left; cursor: pointer; font-weight: 500; width: 100%;">
                            <span style="font-size: 16px;">📏</span> Прямая линия
                        </button>
                        <button type="button" class="img-editor-tool-btn" data-tool="arrow" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: left; cursor: pointer; font-weight: 500; width: 100%;">
                            <span style="font-size: 16px;">↗️</span> Стрелка
                        </button>
                        <button type="button" class="img-editor-tool-btn" data-tool="pixelate" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: left; cursor: pointer; font-weight: 500; width: 100%;">
                            <span style="font-size: 16px;">⬛</span> Пикселизация
                        </button>
                        <button type="button" class="img-editor-tool-btn" data-tool="text" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: left; cursor: pointer; font-weight: 500; width: 100%;">
                            <span style="font-size: 16px;">🔤</span> Текст
                        </button>
                    </div>
                </div>
                
                <!-- Цвет -->
                <div id="imgEditorColorSection">
                    <h4 style="margin: 0 0 12px 0; color: var(--text-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Цвет</h4>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <input type="color" id="imgEditorColorPicker" value="#ff0000" style="width: 100%; height: 40px; border: 1px solid var(--border-color); border-radius: 8px; padding: 2px; cursor: pointer; background: transparent;">
                        <!-- Пресеты -->
                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px;">
                            <div class="color-preset active" data-color="#ff0000" style="background: #ff0000;"></div>
                            <div class="color-preset" data-color="#00ff00" style="background: #00ff00;"></div>
                            <div class="color-preset" data-color="#0000ff" style="background: #0000ff;"></div>
                            <div class="color-preset" data-color="#ffff00" style="background: #ffff00;"></div>
                            <div class="color-preset" data-color="#00ffff" style="background: #00ffff;"></div>
                            <div class="color-preset" data-color="#ff00ff" style="background: #ff00ff;"></div>
                            <div class="color-preset" data-color="#ffffff" style="background: #ffffff; border: 1px solid #ddd;"></div>
                            <div class="color-preset" data-color="#000000" style="background: #000000;"></div>
                            <div class="color-preset" data-color="#ff9800" style="background: #ff9800;"></div>
                            <div class="color-preset" data-color="#9c27b0" style="background: #9c27b0;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Толщина -->
                <div id="imgEditorSizeSection">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <h4 style="margin: 0; color: var(--text-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;" id="imgEditorSizeLabel">Толщина кисти</h4>
                        <span id="imgEditorSizeValue" style="color: var(--text-color); font-weight: bold; font-size: 12px;">5 px</span>
                    </div>
                    <input type="range" id="imgEditorSizeSlider" min="1" max="50" value="5" style="width: 100%; cursor: pointer;">
                </div>

                <!-- Размер шрифта -->
                <div id="imgEditorFontSizeSection" style="display: none;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <h4 style="margin: 0; color: var(--text-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Размер текста</h4>
                        <span id="imgEditorFontSizeValue" style="color: var(--text-color); font-weight: bold; font-size: 12px;">30 px</span>
                    </div>
                    <input type="range" id="imgEditorFontSizeSlider" min="10" max="100" value="30" style="width: 100%; cursor: pointer;">
                </div>
                
                <div style="margin-top: auto; padding: 12px; border-radius: 8px; background: rgba(0,0,0,0.03); font-size: 12px; color: var(--text-color); opacity: 0.8; border: 1px solid var(--border-color);">
                    <strong>💡 Подсказка:</strong><br>
                    <span id="imgEditorHelpText">Рисуйте мышкой на изображении зажав левую кнопку.</span>
                </div>
            </div>
            
            <!-- Центральная область холста -->
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 30px; overflow: auto; position: relative;" id="imgEditorCanvasContainer">
                <canvas id="imgEditorCanvas" style="box-shadow: 0 4px 30px rgba(0,0,0,0.15); max-width: 100%; max-height: 100%; object-fit: contain; cursor: crosshair; background-image: radial-gradient(var(--border-color) 15%, transparent 16%), radial-gradient(var(--border-color) 15%, transparent 16%); background-size: 16px 16px; background-position: 0 0, 8px 8px; background-color: var(--bg-color); border: 1px dashed var(--border-color);"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно Редактора ASCII-арта -->
<div id="asciiEditorModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 10006; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: var(--bg-color); border-radius: 16px; max-width: 95vw; width: 1000px; height: 85vh; box-shadow: 0 10px 40px rgba(0,0,0,0.5); overflow: hidden; display: flex; flex-direction: column; border: 1px solid var(--border-color);">
        <!-- Заголовок -->
        <div style="padding: 15px 25px; border-bottom: 2px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.03);">
            <h3 style="margin: 0; color: var(--text-color); font-size: 20px; display: flex; align-items: center; gap: 10px;">
                <span>👾</span> ASCII Рисовалка
            </h3>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" id="asciiEditorUndoBtn" onclick="undoAsciiState()" class="global-action-btn" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-color); padding: 6px 14px; font-size: 14px; display: flex; align-items: center; gap: 8px;" title="Отменить (Ctrl+Z)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
                        <path d="M3 7v6h6" />
                        <path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13" />
                    </svg>
                    Отменить
                </button>
                <button type="button" onclick="saveAsciiArt()" class="global-action-btn global-action-btn-primary" style="padding: 6px 18px; font-size: 14px; background: var(--accent-color); color: #fff; border: none; font-weight: bold; border-radius: 6px; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <span>💾</span> Сохранить
                </button>
                <button type="button" onclick="closeAsciiEditor()" style="background: transparent; border: none; font-size: 32px; color: var(--text-color); cursor: pointer; line-height: 1; padding: 0 5px; margin-left: 10px;">×</button>
            </div>
        </div>
        
        <!-- Основная область -->
        <div style="flex: 1; display: flex; overflow: hidden; background: rgba(0,0,0,0.05);">
            <!-- Левая панель инструментов -->
            <div style="width: 260px; border-right: 2px solid var(--border-color); background: var(--bg-color); display: flex; flex-direction: column; gap: 20px; padding: 25px; overflow-y: auto;">
                
                <!-- Размер сетки -->
                <div>
                    <h4 style="margin: 0 0 10px 0; color: var(--text-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Размер сетки</h4>
                    <select id="asciiGridSize" onchange="changeAsciiGridSize(this.value)" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); font-weight: 500; cursor: pointer; margin-bottom: 8px;">
                        <option value="20x10">Маленький (20x10)</option>
                        <option value="40x15" selected>Средний (40x15)</option>
                        <option value="60x20">Большой (60x20)</option>
                        <option value="80x25">Огромный (80x25)</option>
                        <option value="custom">Свой размер...</option>
                    </select>
                    
                    <div id="asciiCustomSizeContainer" style="display: none; gap: 6px; align-items: center; margin-top: 8px;">
                        <input type="number" id="asciiCustomWidth" min="5" max="120" value="40" style="width: 60px; padding: 6px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: center;" title="Ширина (колонки)">
                        <span style="color: var(--text-color); opacity: 0.7;">×</span>
                        <input type="number" id="asciiCustomHeight" min="5" max="60" value="15" style="width: 60px; padding: 6px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); text-align: center;" title="Высота (строки)">
                        <button type="button" onclick="applyCustomAsciiGridSize()" style="flex: 1; padding: 6px; border-radius: 6px; border: none; background: var(--accent-color); color: #fff; font-size: 12px; cursor: pointer; font-weight: bold;">ОК</button>
                    </div>
                </div>
                
                <!-- Инструменты -->
                <div>
                    <h4 style="margin: 0 0 10px 0; color: var(--text-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Инструменты</h4>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                        <button type="button" class="ascii-tool-btn active" id="ascii-tool-draw" onclick="setAsciiTool('draw')">
                            ✏️ Рисовать
                        </button>
                        <button type="button" class="ascii-tool-btn" id="ascii-tool-erase" onclick="setAsciiTool('erase')">
                            🧼 Ластик
                        </button>
                        <button type="button" class="ascii-tool-btn" id="ascii-tool-fill" onclick="setAsciiTool('fill')" style="grid-column: span 2;">
                            🪣 Заливка
                        </button>
                    </div>
                </div>

                <!-- Выбор символа -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0; color: var(--text-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Символ для рисования</h4>
                        <div style="display: flex; gap: 4px; align-items: center;">
                            <button type="button" onclick="prevAsciiPage()" class="ascii-pager-btn" id="asciiPrevPageBtn" title="Предыдущая группа">◀</button>
                            <span id="asciiPageIndicator" style="color: var(--text-color); font-size: 11px; opacity: 0.8; font-weight: bold; min-width: 65px; text-align: center;">Блоки</span>
                            <button type="button" onclick="nextAsciiPage()" class="ascii-pager-btn" id="asciiNextPageBtn" title="Следующая группа">▶</button>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; margin-bottom: 12px; min-height: 108px;" id="asciiCharPresets">
                        <!-- Пресеты символов заполняются динамически -->
                    </div>
                    
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" id="asciiCustomChar" maxlength="1" placeholder="Свой" style="width: 50px; text-align: center; padding: 6px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); font-family: monospace; font-size: 16px;">
                        <button type="button" onclick="applyCustomAsciiChar()" style="flex: 1; padding: 6px; border-radius: 6px; border: none; background: var(--accent-color); color: #fff; font-size: 12px; cursor: pointer; font-weight: bold;">Применить</button>
                    </div>
                </div>

                <div style="margin-top: auto;">
                    <button type="button" onclick="clearAsciiGrid()" class="global-action-btn" style="width: 100%; justify-content: center; background: transparent; border: 1px solid rgba(244, 67, 54, 0.4); color: #f44336; padding: 10px; font-size: 13px; font-weight: 500; border-radius: 8px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        🗑️ Очистить холст
                    </button>
                </div>
            </div>
            
            <!-- Центральная область холста -->
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 30px; overflow: auto; position: relative;" id="asciiEditorCanvasContainer">
                <div id="asciiGrid" style="display: grid; border: 1px solid var(--border-color); background: var(--bg-color); box-shadow: 0 4px 30px rgba(0,0,0,0.15); max-width: 100%; cursor: crosshair; user-select: none; -webkit-user-select: none;">
                    <!-- Ячейки сетки генерируются динамически через JS -->
                </div>
            </div>
        </div>
    </div>
</div>



<script>
let imgEditorTargetImg = null;
let imgEditorCanvas = null;
let imgEditorCtx = null;
let imgEditorHistory = [];
let imgEditorIsDrawing = false;
let imgEditorCurrentTool = 'pencil';
let imgEditorCurrentColor = '#ff0000';
let imgEditorCurrentSize = 5;
let imgEditorCurrentFontSize = 30;
let imgEditorDragBaseState = null;
let imgEditorStartX = 0;
let imgEditorStartY = 0;

function openImageEditorModal(imgElement) {
    imgEditorTargetImg = imgElement;
    imgEditorCanvas = document.getElementById('imgEditorCanvas');
    imgEditorCtx = imgEditorCanvas.getContext('2d');
    
    const modal = document.getElementById('imageEditorModal');
    modal.style.display = 'flex';
    modal.classList.add('show');
    
    const tempImg = new Image();
    tempImg.crossOrigin = 'anonymous'; 
    tempImg.onload = function() {
        imgEditorCanvas.width = tempImg.naturalWidth;
        imgEditorCanvas.height = tempImg.naturalHeight;
        
        imgEditorCtx.clearRect(0, 0, imgEditorCanvas.width, imgEditorCanvas.height);
        imgEditorCtx.drawImage(tempImg, 0, 0);
        
        imgEditorHistory = [imgEditorCtx.getImageData(0, 0, imgEditorCanvas.width, imgEditorCanvas.height)];
        updateImgEditorUndoBtnState();
        
        setImgEditorTool('pencil');
    };
    tempImg.src = imgElement.src.split('?')[0];
}

function closeImgEditorModal() {
    const modal = document.getElementById('imageEditorModal');
    modal.style.display = 'none';
    modal.classList.remove('show');
    imgEditorTargetImg = null;
}

function updateImgEditorUndoBtnState() {
    const undoBtn = document.getElementById('imgEditorUndoBtn');
    if (imgEditorHistory.length > 1) {
        undoBtn.disabled = false;
        undoBtn.style.opacity = '1';
        undoBtn.style.cursor = 'pointer';
    } else {
        undoBtn.disabled = true;
        undoBtn.style.opacity = '0.5';
        undoBtn.style.cursor = 'not-allowed';
    }
}

function saveImgEditorState() {
    if (imgEditorHistory.length >= 30) {
        imgEditorHistory.shift();
    }
    imgEditorHistory.push(imgEditorCtx.getImageData(0, 0, imgEditorCanvas.width, imgEditorCanvas.height));
    updateImgEditorUndoBtnState();
}

function undoImgEditorState() {
    if (imgEditorHistory.length > 1) {
        imgEditorHistory.pop();
        const prevState = imgEditorHistory[imgEditorHistory.length - 1];
        imgEditorCtx.putImageData(prevState, 0, 0);
        updateImgEditorUndoBtnState();
    }
}

function setImgEditorTool(tool) {
    imgEditorCurrentTool = tool;
    
    document.querySelectorAll('.img-editor-tool-btn').forEach(btn => {
        if (btn.getAttribute('data-tool') === tool) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    const sizeLabel = document.getElementById('imgEditorSizeLabel');
    const colorSection = document.getElementById('imgEditorColorSection');
    const sizeSection = document.getElementById('imgEditorSizeSection');
    const fontSizeSection = document.getElementById('imgEditorFontSizeSection');
    const helpText = document.getElementById('imgEditorHelpText');
    
    if (tool === 'text') {
        colorSection.style.display = 'block';
        sizeSection.style.display = 'none';
        fontSizeSection.style.display = 'block';
        helpText.textContent = 'Кликните на изображение, чтобы добавить текст в эту точку.';
    } else if (tool === 'pixelate') {
        colorSection.style.display = 'none';
        sizeSection.style.display = 'block';
        fontSizeSection.style.display = 'none';
        sizeLabel.textContent = 'Размер кисти размытия';
        helpText.textContent = 'Зажмите кнопку мыши и водите по областям, которые хотите размыть пикселями.';
    } else {
        colorSection.style.display = 'block';
        sizeSection.style.display = 'block';
        fontSizeSection.style.display = 'none';
        sizeLabel.textContent = 'Толщина кисти';
        helpText.textContent = 'Зажмите кнопку мыши и рисуйте на изображении.';
    }
}

function getImgEditorCoordinates(e) {
    const rect = imgEditorCanvas.getBoundingClientRect();
    const scaleX = imgEditorCanvas.width / rect.width;
    const scaleY = imgEditorCanvas.height / rect.height;
    
    let clientX = e.clientX;
    let clientY = e.clientY;
    
    if (e.touches && e.touches.length > 0) {
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    }
    
    return {
        x: (clientX - rect.left) * scaleX,
        y: (clientY - rect.top) * scaleY
    };
}

function startImgEditorDrawing(e) {
    imgEditorIsDrawing = true;
    const coords = getImgEditorCoordinates(e);
    imgEditorStartX = coords.x;
    imgEditorStartY = coords.y;
    
    if (imgEditorCurrentTool === 'pencil') {
        imgEditorCtx.beginPath();
        imgEditorCtx.moveTo(imgEditorStartX, imgEditorStartY);
    } else if (imgEditorCurrentTool === 'line' || imgEditorCurrentTool === 'arrow') {
        imgEditorDragBaseState = imgEditorCtx.getImageData(0, 0, imgEditorCanvas.width, imgEditorCanvas.height);
    } else if (imgEditorCurrentTool === 'pixelate') {
        pixelateRegionAt(imgEditorStartX, imgEditorStartY);
    } else if (imgEditorCurrentTool === 'text') {
        imgEditorIsDrawing = false;
        addTextAt(imgEditorStartX, imgEditorStartY);
    }
}

function drawImgEditor(e) {
    if (!imgEditorIsDrawing) return;
    const coords = getImgEditorCoordinates(e);
    const currX = coords.x;
    const currY = coords.y;
    
    if (imgEditorCurrentTool === 'pencil') {
        imgEditorCtx.lineTo(currX, currY);
        imgEditorCtx.strokeStyle = imgEditorCurrentColor;
        imgEditorCtx.lineWidth = imgEditorCurrentSize;
        imgEditorCtx.lineCap = 'round';
        imgEditorCtx.lineJoin = 'round';
        imgEditorCtx.stroke();
    } else if (imgEditorCurrentTool === 'line') {
        imgEditorCtx.putImageData(imgEditorDragBaseState, 0, 0);
        imgEditorCtx.beginPath();
        imgEditorCtx.moveTo(imgEditorStartX, imgEditorStartY);
        imgEditorCtx.lineTo(currX, currY);
        imgEditorCtx.strokeStyle = imgEditorCurrentColor;
        imgEditorCtx.lineWidth = imgEditorCurrentSize;
        imgEditorCtx.lineCap = 'round';
        imgEditorCtx.stroke();
    } else if (imgEditorCurrentTool === 'arrow') {
        imgEditorCtx.putImageData(imgEditorDragBaseState, 0, 0);
        
        imgEditorCtx.beginPath();
        imgEditorCtx.moveTo(imgEditorStartX, imgEditorStartY);
        imgEditorCtx.lineTo(currX, currY);
        imgEditorCtx.strokeStyle = imgEditorCurrentColor;
        imgEditorCtx.lineWidth = imgEditorCurrentSize;
        imgEditorCtx.lineCap = 'round';
        imgEditorCtx.stroke();
        
        drawArrowheadHead(imgEditorStartX, imgEditorStartY, currX, currY);
    } else if (imgEditorCurrentTool === 'pixelate') {
        pixelateRegionAt(currX, currY);
    }
}

function stopImgEditorDrawing() {
    if (imgEditorIsDrawing) {
        imgEditorIsDrawing = false;
        saveImgEditorState();
    }
}

function drawArrowheadHead(fromX, fromY, toX, toY) {
    const headLength = imgEditorCurrentSize * 3 + 12;
    const angle = Math.atan2(toY - fromY, toX - fromX);
    
    imgEditorCtx.beginPath();
    imgEditorCtx.moveTo(toX, toY);
    imgEditorCtx.lineTo(toX - headLength * Math.cos(angle - Math.PI / 6), toY - headLength * Math.sin(angle - Math.PI / 6));
    imgEditorCtx.moveTo(toX, toY);
    imgEditorCtx.lineTo(toX - headLength * Math.cos(angle + Math.PI / 6), toY - headLength * Math.sin(angle + Math.PI / 6));
    
    imgEditorCtx.strokeStyle = imgEditorCurrentColor;
    imgEditorCtx.lineWidth = imgEditorCurrentSize;
    imgEditorCtx.lineCap = 'round';
    imgEditorCtx.lineJoin = 'round';
    imgEditorCtx.stroke();
}

function pixelateRegionAt(x, y) {
    const radius = imgEditorCurrentSize * 2 + 10;
    const pixelSize = Math.max(4, Math.round(imgEditorCurrentSize / 1.5) + 6);
    
    const startX = Math.max(0, Math.round(x - radius));
    const startY = Math.max(0, Math.round(y - radius));
    const width = Math.min(imgEditorCanvas.width - startX, radius * 2);
    const height = Math.min(imgEditorCanvas.height - startY, radius * 2);
    
    if (width <= 0 || height <= 0) return;
    
    const imgData = imgEditorCtx.getImageData(startX, startY, width, height);
    const data = imgData.data;
    
    for (let i = 0; i < height; i += pixelSize) {
        for (let j = 0; j < width; j += pixelSize) {
            let r = 0, g = 0, b = 0, a = 0, count = 0;
            
            for (let dy = 0; dy < pixelSize && (i + dy) < height; dy++) {
                for (let dx = 0; dx < pixelSize && (j + dx) < width; dx++) {
                    const idx = ((i + dy) * width + (j + dx)) * 4;
                    r += data[idx];
                    g += data[idx + 1];
                    b += data[idx + 2];
                    a += data[idx + 3];
                    count++;
                }
            }
            
            r = Math.round(r / count);
            g = Math.round(g / count);
            b = Math.round(b / count);
            a = Math.round(a / count);
            
            for (let dy = 0; dy < pixelSize && (i + dy) < height; dy++) {
                for (let dx = 0; dx < pixelSize && (j + dx) < width; dx++) {
                    const idx = ((i + dy) * width + (j + dx)) * 4;
                    data[idx] = r;
                    data[idx + 1] = g;
                    data[idx + 2] = b;
                    data[idx + 3] = a;
                }
            }
        }
    }
    
    imgEditorCtx.putImageData(imgData, startX, startY);
}

function addTextAt(x, y) {
    const text = prompt("Введите текст для нанесения на изображение:");
    if (!text || text.trim() === '') return;
    
    imgEditorCtx.font = `bold ${imgEditorCurrentFontSize}px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif`;
    imgEditorCtx.textBaseline = 'middle';
    
    imgEditorCtx.strokeStyle = imgEditorCurrentColor === '#000000' ? '#ffffff' : '#000000';
    imgEditorCtx.lineWidth = Math.max(3, imgEditorCurrentFontSize / 8);
    imgEditorCtx.strokeText(text, x, y);
    
    imgEditorCtx.fillStyle = imgEditorCurrentColor;
    imgEditorCtx.fillText(text, x, y);
    
    saveImgEditorState();
}

function saveImgEditorChanges() {
    if (!imgEditorTargetImg) return;
    
    const saveBtn = document.querySelector('[onclick="saveImgEditorChanges()"]');
    const oldText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span>⏳</span> Сохранение...';
    
    const dataUrl = imgEditorCanvas.toDataURL('image/png');
    
    const formData = new URLSearchParams();
    formData.append('image_data', dataUrl);
    
    fetch('save_edited_image.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = oldText;
        
        if (data.success) {
            imgEditorTargetImg.setAttribute('src', data.url);
            imgEditorTargetImg.src = data.url + '?t=' + Date.now();
            
            showNotification('Изображение успешно сохранено!', 'success');
            closeImgEditorModal();
            
            if (typeof triggerAutosave === 'function') {
                triggerAutosave();
            }
        } else {
            showNotification('Ошибка сохранения: ' + data.error, 'error');
        }
    })
    .catch(error => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = oldText;
        console.error('Save edited image error:', error);
        showNotification('Критическая ошибка сохранения изображения', 'error');
    });
}

// Инициализация обработчиков холста редактора и применение настроек
document.addEventListener('DOMContentLoaded', function() {
    loadAndApplyAllSettings();
    
    const canvas = document.getElementById('imgEditorCanvas');
    if (!canvas) return;
    
    canvas.addEventListener('mousedown', startImgEditorDrawing);
    canvas.addEventListener('mousemove', drawImgEditor);
    window.addEventListener('mouseup', stopImgEditorDrawing);
    
    canvas.addEventListener('touchstart', function(e) {
        e.preventDefault();
        startImgEditorDrawing(e);
    });
    canvas.addEventListener('touchmove', function(e) {
        e.preventDefault();
        drawImgEditor(e);
    });
    canvas.addEventListener('touchend', function(e) {
        e.preventDefault();
        stopImgEditorDrawing(e);
    });
    
    document.querySelectorAll('.img-editor-tool-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            setImgEditorTool(this.getAttribute('data-tool'));
        });
    });
    
    const colorPicker = document.getElementById('imgEditorColorPicker');
    colorPicker.addEventListener('input', function() {
        imgEditorCurrentColor = this.value;
        document.querySelectorAll('.color-preset').forEach(p => p.classList.remove('active'));
    });
    
    document.querySelectorAll('.color-preset').forEach(preset => {
        preset.addEventListener('click', function() {
            document.querySelectorAll('.color-preset').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            imgEditorCurrentColor = this.getAttribute('data-color');
            colorPicker.value = imgEditorCurrentColor;
        });
    });
    
    const sizeSlider = document.getElementById('imgEditorSizeSlider');
    const sizeValue = document.getElementById('imgEditorSizeValue');
    sizeSlider.addEventListener('input', function() {
        imgEditorCurrentSize = parseInt(this.value);
        sizeValue.textContent = imgEditorCurrentSize + ' px';
    });
    
    const fontSizeSlider = document.getElementById('imgEditorFontSizeSlider');
    const fontSizeValue = document.getElementById('imgEditorFontSizeValue');
    fontSizeSlider.addEventListener('input', function() {
        imgEditorCurrentFontSize = parseInt(this.value);
        fontSizeValue.textContent = imgEditorCurrentFontSize + ' px';
    });
});

    window.addEventListener('keydown', function(e) {
        const modal = document.getElementById('imageEditorModal');
        if (modal && modal.style.display === 'flex') {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
                e.preventDefault();
                undoImgEditorState();
            }
        }
});
</script>

</body>
</html>


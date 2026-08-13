<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель керування KeyCRM</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --primary-bg: #f4f6f9;
            --sidebar-bg: #343a40;
            --sidebar-hover: #495057;
            --sidebar-active: #0d6efd;
            --text-color: #333;
        }
        body {
            background-color: var(--primary-bg);
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--sidebar-bg);
            color: #fff;
            z-index: 1000;
            transition: all 0.3s;
        }
        .sidebar-header {
            padding: 20px;
            font-size: 1.25rem;
            font-weight: bold;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-nav li {
            width: 100%;
        }
        .sidebar-nav li a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: background 0.3s;
        }
        .sidebar-nav li a i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }
        .sidebar-nav li a:hover {
            background-color: var(--sidebar-hover);
            color: #fff;
        }
        .sidebar-nav li a.active {
            background-color: var(--sidebar-active);
            color: #fff;
        }
        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
        }
        .content-section {
            display: none;
            animation: fadeIn 0.4s;
        }
        .content-section.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #edf2f9;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        /* Tables */
        .table-responsive { border-radius: 10px; overflow: hidden; }
        .table { margin-bottom: 0; }
        .table thead th { border-bottom: none; background-color: #f8f9fa; color: #555; text-transform: uppercase; font-size: 0.85rem; }
        /* Utilities */
        .cursor-pointer { cursor: pointer; }
        .log-modal-content {
            background: #2d2d2d;
            color: #a9b7c6;
            font-family: monospace;
            padding: 15px;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            border-radius: 5px;
        }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 1055; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-cogs"></i> KeyCRM Panel
        </div>
        <ul class="sidebar-nav">
            <li><a href="#" class="nav-link active" data-target="dashboard"><i class="fas fa-home"></i> Головна</a></li>
            <li><a href="#" class="nav-link" data-target="scripts"><i class="fas fa-play-circle"></i> Скрипти</a></li>
            <li><a href="check_products.php" target="_blank" class="nav-link"><i class="fas fa-search"></i> Перевірка товарів</a></li>
            <li><a href="#" class="nav-link" data-target="logs" onclick="loadLogs()"><i class="fas fa-file-alt"></i> Логи</a></li>
            <li><a href="#" class="nav-link" data-target="refunds" onclick="loadRefundHistory()"><i class="fas fa-undo-alt"></i> Історія повернень</a></li>
            <li><a href="#" class="nav-link" data-target="uploads" onclick="loadUploads()"><i class="fas fa-folder-open"></i> Файли</a></li>
            <li><a href="#" class="nav-link" data-target="settings" onclick="loadSettings()"><i class="fas fa-sliders-h"></i> Налаштування</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Dashboard Section -->
        <div id="dashboard" class="content-section active">
            <h2 class="mb-4">Головна панель</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-primary text-white text-center p-4 cursor-pointer" onclick="document.querySelector('.nav-link[data-target=\'scripts\']').click()">
                        <i class="fas fa-code fa-3x mb-3"></i>
                        <h4>Скрипти</h4>
                        <p class="mb-0">Запуск кронів</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white text-center p-4 cursor-pointer" onclick="document.querySelector('.nav-link[data-target=\'uploads\']').click()">
                        <i class="fas fa-file-excel fa-3x mb-3"></i>
                        <h4>Файли</h4>
                        <p class="mb-0">CSV та XLSX</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white text-center p-4 cursor-pointer" onclick="window.open('check_products.php', '_blank')">
                        <i class="fas fa-search fa-3x mb-3"></i>
                        <h4>Перевірка</h4>
                        <p class="mb-0">Сайт vs 1C</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark text-center p-4 cursor-pointer" onclick="window.open('upload_discounts.php', '_blank')">
                        <i class="fas fa-upload fa-3x mb-3"></i>
                        <h4>Discounts</h4>
                        <p class="mb-0">Форма завантаження</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scripts Section -->
        <div id="scripts" class="content-section">
            <h2 class="mb-4">Керування скриптами</h2>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header text-primary"><i class="fas fa-clock"></i> Крон Задачі (Автоматичні)</div>
                        <ul class="list-group list-group-flush">
                            <?php
                            $cronScripts = ['cron_refund.php', 'cron_shipping_dates.php', 'cron_update_products.php', 'update_products_price_stock.php', 'update_stock.php'];
                            foreach($cronScripts as $sc) { ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo $sc; ?></strong><br>
                                    <small class="text-muted last-run-date" data-script="<?php echo $sc; ?>">Останній запуск: Невідомо</small>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <button class="btn btn-sm btn-outline-info" onclick="viewHistory('<?php echo $sc; ?>')" title="Історія запусків"><i class="fas fa-history"></i></button>
                                    <button class="btn btn-sm btn-outline-primary run-script-btn" onclick="runScript('<?php echo $sc; ?>', this)"><i class="fas fa-play"></i> Запустити</button>
                                    <button class="btn btn-sm btn-outline-danger stop-script-btn" style="display:none;" onclick="stopScript()" title="Зупинити"><i class="fas fa-stop"></i></button>
                                </div>
                            </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header text-success"><i class="fas fa-download"></i> Імпорт та Інтеграції</div>
                        <ul class="list-group list-group-flush">
                            <?php
                            $importScripts = ['presta_import.php', 'presta_update_price.php', 'import_products.php', 'import_products_intertop.php', 'import_products_kasta.php', 'import_products_prestashop.php'];
                            foreach($importScripts as $sc) { ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo $sc; ?></strong><br>
                                    <small class="text-muted last-run-date" data-script="<?php echo $sc; ?>">Останній запуск: Невідомо</small>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <button class="btn btn-sm btn-outline-info" onclick="viewHistory('<?php echo $sc; ?>')" title="Історія запусків"><i class="fas fa-history"></i></button>
                                    <button class="btn btn-sm btn-outline-success run-script-btn" onclick="runScript('<?php echo $sc; ?>', this)"><i class="fas fa-play"></i> Запустити</button>
                                    <button class="btn btn-sm btn-outline-danger stop-script-btn" style="display:none;" onclick="stopScript()" title="Зупинити"><i class="fas fa-stop"></i></button>
                                </div>
                            </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3" id="scriptOutputCard" style="display:none;">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-terminal"></i> Результат виконання</span>
                    <button class="btn btn-sm btn-outline-light" onclick="document.getElementById('scriptOutputCard').style.display='none'"><i class="fas fa-times"></i></button>
                </div>
                <div class="card-body bg-dark text-light">
                    <pre id="scriptOutput" class="mb-0" style="white-space: pre-wrap; font-size: 0.9rem;"></pre>
                </div>
            </div>
        </div>

        <!-- Logs Section -->
        <div id="logs" class="content-section">
            <h2 class="mb-4">Системні логи</h2>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Файли в директорії /logs</span>
                    <button class="btn btn-sm btn-primary" onclick="loadLogs()"><i class="fas fa-sync"></i> Оновити</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Назва файлу</th>
                                    <th>Розмір</th>
                                    <th>Дата зміни</th>
                                    <th class="text-end">Дії</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <!-- Logs will be loaded here via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Uploads Section -->
        <div id="uploads" class="content-section">
            <h2 class="mb-4">Завантажені файли</h2>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span id="currentUploadPathText">Файли в /uploads та /xlsx</span>
                    <button class="btn btn-sm btn-primary" onclick="loadUploads(currentUploadDir)"><i class="fas fa-sync"></i> Оновити</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Назва файлу</th>
                                    <th>Шлях</th>
                                    <th>Розмір</th>
                                    <th>Дата зміни</th>
                                    <th class="text-end">Дії</th>
                                </tr>
                            </thead>
                            <tbody id="uploadsTableBody">
                                <!-- Uploads will be loaded here via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Refund History Section -->
        <div id="refunds" class="content-section">
            <h2 class="mb-4">Історія повернень</h2>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Останні операції з поверненнями</span>
                    <button class="btn btn-sm btn-primary" onclick="loadRefundHistory()"><i class="fas fa-sync"></i> Оновити</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Останнє оновлення</th>
                                    <th>Замовлення</th>
                                    <th>Система</th>
                                    <th>Статус</th>
                                    <th>Повідомлення</th>
                                    <th>Деталі</th>
                                </tr>
                            </thead>
                            <tbody id="refundsTableBody">
                                <!-- Refund history will be loaded here via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Section -->
        <div id="settings" class="content-section">
            <h2 class="mb-4">Налаштування системи</h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">Налаштування повернень (ФОП)</h5>
                                <p class="text-muted mb-0 small">Керування реквізитами для автоповернень</p>
                            </div>
                            <a href="refund_settings.php" target="_blank" class="btn btn-outline-primary">Перейти</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">Налаштування ФОП (Загальні)</h5>
                                <p class="text-muted mb-0 small">fop_settings.php</p>
                            </div>
                            <a href="fop_settings.php" target="_blank" class="btn btn-outline-primary">Перейти</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">Ручне повернення</h5>
                                <p class="text-muted mb-0 small">manual_refund.php</p>
                            </div>
                            <a href="manual_refund.php" target="_blank" class="btn btn-outline-primary">Перейти</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">Форма завантаження Discounts</h5>
                                <p class="text-muted mb-0 small">upload_discounts.php</p>
                            </div>
                            <a href="upload_discounts.php" target="_blank" class="btn btn-outline-primary">Перейти</a>
                        </div>
                    </div>
                </div>
                <!-- Маркетплейси (Налаштування) -->
                <div class="col-md-12 mt-4">
                    <h2 class="mb-4">Налаштування маркетплейсів</h2>
                    <div class="card">
                        <div class="card-header bg-dark text-white"><i class="fas fa-cogs"></i> Виберіть, куди потрібно вивантажувати/оновлювати товари</div>
                        <div class="card-body">
                            <form id="settingsForm">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="setting_KASTA">
                                    <label class="form-check-label" for="setting_KASTA">Kasta (оновлення залишків і цін)</label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="setting_INTERTOP">
                                    <label class="form-check-label" for="setting_INTERTOP">Intertop (оновлення залишків і цін)</label>
                                </div>
                                <hr>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="setting_PRESTASHOP">
                                    <label class="form-check-label" for="setting_PRESTASHOP"><strong>PrestaShop (Глобальне увімкнення)</strong></label>
                                </div>
                                <div class="form-check form-switch mb-2 ms-4">
                                    <input class="form-check-input" type="checkbox" id="setting_PRESTASHOP_UPDATE_PRICE">
                                    <label class="form-check-label text-muted" for="setting_PRESTASHOP_UPDATE_PRICE">Оновлення цін</label>
                                </div>
                                <div class="form-check form-switch mb-4 ms-4">
                                    <input class="form-check-input" type="checkbox" id="setting_PRESTASHOP_IMPORT_PRODUCT">
                                    <label class="form-check-label text-muted" for="setting_PRESTASHOP_IMPORT_PRODUCT">Імпорт нових товарів</label>
                                </div>
                                <hr>
                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" id="setting_UPDATE_STOCK_PRICE_CHANGE_STATUS">
                                    <label class="form-check-label" for="setting_UPDATE_STOCK_PRICE_CHANGE_STATUS">Оновлювати статус при зміні цін/залишків (UPDATE_STOCK_PRICE_CHANGE_STATUS)</label>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="saveSettings()"><i class="fas fa-save"></i> Зберегти налаштування</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Log Viewer Modal -->
    <div class="modal fade" id="logModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logModalTitle">Перегляд логу</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="logModalContent" class="log-modal-content">Завантаження...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрити</button>
                </div>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalTitle">Історія запусків</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Дата та Час</th>
                                    <th>Статус</th>
                                    <th>Тривалість</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <!-- History loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрити</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div class="toast-container">
        <div id="liveToast" class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage">Повідомлення</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Application Logic -->
    <script>
        const API_URL = 'ajax_dashboard.php';
        let toastElList = [].slice.call(document.querySelectorAll('.toast'))
        let toastList = toastElList.map(function(toastEl) {
            return new bootstrap.Toast(toastEl)
        })

        function showToast(message, type = 'primary') {
            const toastEl = document.getElementById('liveToast');
            toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
            document.getElementById('toastMessage').innerText = message;
            toastList[0].show();
        }

        // Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('data-target')) {
                    e.preventDefault();
                    // Remove active class from all links
                    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Hide all sections
                    document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
                    // Show target section
                    document.getElementById(this.getAttribute('data-target')).classList.add('active');
                }
            });
        });

        // Logs Management
        async function loadLogs() {
            const tbody = document.getElementById('logsTableBody');
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>';
            try {
                const res = await fetch(`${API_URL}?action=get_logs`);
                const data = await res.json();
                if (data.success) {
                    tbody.innerHTML = '';
                    if (data.logs.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Логи відсутні</td></tr>';
                    } else {
                        data.logs.forEach(log => {
                            tbody.innerHTML += `
                                <tr>
                                    <td><i class="fas fa-file-alt text-secondary me-2"></i> ${log.name}</td>
                                    <td><span class="badge bg-light text-dark border">${log.size}</span></td>
                                    <td>${log.mtime}</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-info text-white me-1" onclick="viewLog('${log.name}')"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm btn-danger" onclick="clearLog('${log.name}')"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                }
            } catch (err) {
                showToast('Помилка завантаження логів', 'danger');
            }
        }

        async function viewLog(fileName) {
            const modal = new bootstrap.Modal(document.getElementById('logModal'));
            document.getElementById('logModalTitle').innerText = fileName;
            const contentDiv = document.getElementById('logModalContent');
            contentDiv.innerHTML = '<div class="text-center my-4"><div class="spinner-border text-light" role="status"></div></div>';
            modal.show();

            try {
                const res = await fetch(`${API_URL}?action=read_log&file=${encodeURIComponent(fileName)}`);
                const data = await res.json();
                if (data.success) {
                    contentDiv.innerHTML = data.content || '<em>Файл порожній</em>';
                    // Scroll to bottom
                    setTimeout(() => {
                        contentDiv.scrollTop = contentDiv.scrollHeight;
                    }, 100);
                } else {
                    contentDiv.innerHTML = `<span class="text-danger">${data.message}</span>`;
                }
            } catch (err) {
                contentDiv.innerHTML = '<span class="text-danger">Помилка мережі при завантаженні логу.</span>';
            }
        }

        async function clearLog(fileName) {
            if (confirm(`Ви впевнені, що хочете очистити ${fileName}?`)) {
                try {
                    const res = await fetch(`${API_URL}?action=clear_log&file=${encodeURIComponent(fileName)}`);
                    const data = await res.json();
                    if (data.success) {
                        showToast(`Файл ${fileName} очищено`, 'success');
                        loadLogs();
                    } else {
                        showToast(data.message, 'danger');
                    }
                } catch (err) {
                    showToast('Помилка при очищенні логу', 'danger');
                }
            }
        }
        
        function loadSettings() {
            fetch(`${API_URL}?action=get_settings`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.settings) {
                        for (const key in data.settings) {
                            const el = document.getElementById('setting_' + key);
                            if (el) el.checked = data.settings[key];
                        }
                    }
                });
        }
        
        async function saveSettings() {
            const keys = ['KASTA', 'INTERTOP', 'PRESTASHOP', 'PRESTASHOP_UPDATE_PRICE', 'PRESTASHOP_IMPORT_PRODUCT', 'UPDATE_STOCK_PRICE_CHANGE_STATUS'];
            let settings = {};
            keys.forEach(k => {
                const el = document.getElementById('setting_' + k);
                if (el) settings[k] = el.checked;
            });
            
            try {
                const res = await fetch(`${API_URL}?action=save_settings`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({settings})
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Налаштування успішно збережено!', 'success');
                } else {
                    showToast('Помилка: ' + data.message, 'danger');
                }
            } catch(e) {
                showToast('Помилка збереження', 'danger');
            }
        }

        // Refund History Management
        async function loadRefundHistory() {
            const tbody = document.getElementById('refundsTableBody');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>';
            try {
                const res = await fetch(`${API_URL}?action=get_refund_history`);
                const data = await res.json();
                if (data.success) {
                    tbody.innerHTML = '';
                    if (data.history.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">Історія порожня</td></tr>';
                    } else {
                        data.history.forEach(item => {
                            let statusBadge = `<span class="badge bg-secondary">${item.status}</span>`;
                            if (item.status === 'SUCCESS') statusBadge = '<span class="badge bg-success">Успішно</span>';
                            else if (item.status === 'ERROR') statusBadge = '<span class="badge bg-danger">Помилка</span>';
                            else if (item.status === 'INFO') statusBadge = '<span class="badge bg-info text-dark">Інфо</span>';
                            else if (item.status === 'WARNING') statusBadge = '<span class="badge bg-warning text-dark">Увага</span>';
                            
                            let sysLower = item.system.toLowerCase();
                            let sType = item.system_type || 'unknown';
                            
                            let systemBadge = `<span class="badge bg-light text-dark border"><i class="fas fa-question-circle text-muted"></i> ${item.system}</span>`;
                            
                            if (sType === 'liqpay' || sysLower.includes('liqpay')) {
                                systemBadge = `<span class="badge bg-light text-dark border"><i class="fas fa-credit-card text-success"></i> ${item.system}</span>`;
                            } else if (sType === 'privatbank' || sysLower.includes('приват')) {
                                systemBadge = `<span class="badge bg-light text-dark border"><i class="fas fa-university text-success"></i> ${item.system}</span>`;
                            } else if (sType === 'monobank' || sysLower.includes('моно') || sysLower.includes('monobank')) {
                                systemBadge = `<span class="badge bg-light text-dark border"><i class="fas fa-university text-dark"></i> ${item.system}</span>`;
                            } else if (sysLower.includes('фоп')) {
                                systemBadge = `<span class="badge bg-light text-dark border"><i class="fas fa-briefcase text-primary"></i> ${item.system}</span>`;
                            }
                            
                            // Generate detailed logs HTML
                            let detailsHtml = item.messages.map(msg => {
                                let badge = msg.status;
                                if (msg.status === 'SUCCESS') badge = '<span class="badge bg-success" style="width: 60px; display: inline-block; text-align: center;">SUCCESS</span>';
                                else if (msg.status === 'ERROR') badge = '<span class="badge bg-danger" style="width: 60px; display: inline-block; text-align: center;">ERROR</span>';
                                else if (msg.status === 'WARNING') badge = '<span class="badge bg-warning text-dark" style="width: 60px; display: inline-block; text-align: center;">WARN</span>';
                                else badge = '<span class="badge bg-info text-dark" style="width: 60px; display: inline-block; text-align: center;">INFO</span>';
                                return `<div class="mb-1"><small class="text-muted" style="font-family: monospace;">[${msg.date}]</small> ${badge} <small>${msg.text}</small></div>`;
                            }).join('');
                            
                            const detailsId = 'details_' + item.order_id;
                            
                            tbody.innerHTML += `
                                <tr>
                                    <td class="text-nowrap">${item.date}</td>
                                    <td><strong><a href="https://twice1.keycrm.app/app/orders/view/${item.order_id}" target="_blank" class="text-decoration-none">#${item.order_id} <i class="fas fa-external-link-alt small"></i></a></strong></td>
                                    <td>${systemBadge}</td>
                                    <td>${statusBadge}</td>
                                    <td class="text-break">${item.latest_msg}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#${detailsId}" title="Показати всю історію">
                                            <i class="fas fa-list"></i> Деталі
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="${detailsId}">
                                    <td colspan="6" class="bg-light p-3 border-bottom">
                                        <div class="mb-2"><strong>Усі логі по замовленню #${item.order_id}:</strong></div>
                                        ${detailsHtml}
                                    </td>
                                </tr>
                            `;
                        });
                    }
                }
            } catch (err) {
                showToast('Помилка завантаження історії повернень', 'danger');
            }
        }

        // Uploads Management
        let currentUploadDir = '';
        
        async function loadUploads(dir = null) {
            if (dir !== null) currentUploadDir = dir;
            
            const pathText = currentUploadDir ? '/' + currentUploadDir : 'Коренева папка';
            document.getElementById('currentUploadPathText').innerText = `Шлях: ${pathText}`;
            
            const tbody = document.getElementById('uploadsTableBody');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>';
            try {
                const res = await fetch(`${API_URL}?action=get_uploads&dir=${encodeURIComponent(currentUploadDir)}`);
                const data = await res.json();
                if (data.success) {
                    tbody.innerHTML = '';
                    if (data.items.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Папка порожня</td></tr>';
                    } else {
                        data.items.forEach(item => {
                            if (item.type === 'folder') {
                                let folderName = item.name;
                                if (folderName === '..') folderName = '⬆️ Вгору (Попередня папка)';
                                
                                tbody.innerHTML += `
                                    <tr class="cursor-pointer" onclick="loadUploads('${item.path}')">
                                        <td><i class="fas fa-folder text-warning me-2"></i> <strong>${folderName}</strong></td>
                                        <td class="text-muted small"></td>
                                        <td><span class="badge bg-light text-dark border">-</span></td>
                                        <td>${item.mtime}</td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation(); loadUploads('${item.path}')">Відкрити</button>
                                        </td>
                                    </tr>
                                `;
                            } else {
                                const ext = item.name.split('.').pop().toLowerCase();
                                let icon = 'fa-file';
                                if (ext === 'xlsx' || ext === 'csv') icon = 'fa-file-excel text-success';
                                if (ext === 'json') icon = 'fa-file-code text-warning';
                                
                                tbody.innerHTML += `
                                    <tr>
                                        <td><i class="fas ${icon} me-2"></i> ${item.name}</td>
                                        <td class="text-muted small">${item.path}</td>
                                        <td><span class="badge bg-light text-dark border">${item.size}</span></td>
                                        <td>${item.mtime}</td>
                                        <td class="text-end">
                                            <a href="${item.path}" download class="btn btn-sm btn-primary me-1"><i class="fas fa-download"></i></a>
                                            <button class="btn btn-sm btn-danger" onclick="deleteUpload('${item.path}')"><i class="fas fa-trash-alt"></i></button>
                                        </td>
                                    </tr>
                                `;
                            }
                        });
                    }
                }
            } catch (err) {
                showToast('Помилка завантаження файлів', 'danger');
            }
        }

        async function deleteUpload(path) {
            if (confirm(`Видалити файл ${path}? Це незворотня дія.`)) {
                try {
                    const res = await fetch(`${API_URL}?action=delete_upload&path=${encodeURIComponent(path)}`);
                    const data = await res.json();
                    if (data.success) {
                        showToast(`Файл успішно видалено`, 'success');
                        loadUploads(currentUploadDir);
                    } else {
                        showToast(data.message, 'danger');
                    }
                } catch (err) {
                    showToast('Помилка видалення файлу', 'danger');
                }
            }
        }

        // Scripts Management
        let progressInterval = null;
        let timerInterval = null;
        let currentAbortController = null;
        let globalScriptHistory = [];

        async function updateLastRunDates() {
            try {
                const res = await fetch(`${API_URL}?action=get_script_history`);
                const data = await res.json();
                if (data.success) {
                    globalScriptHistory = data.history;
                    
                    // Group by script to find the latest run
                    const latestRuns = {};
                    globalScriptHistory.forEach(log => {
                        if (!latestRuns[log.script]) {
                            latestRuns[log.script] = log;
                        }
                    });
                    
                    // Update DOM
                    document.querySelectorAll('.last-run-date').forEach(el => {
                        const scriptName = el.getAttribute('data-script');
                        if (latestRuns[scriptName]) {
                            const log = latestRuns[scriptName];
                            let statusIcon = '';
                            if (log.status === 'success') statusIcon = '<i class="fas fa-check-circle text-success"></i>';
                            else if (log.status === 'error') statusIcon = '<i class="fas fa-times-circle text-danger"></i>';
                            else if (log.status === 'aborted') statusIcon = '<i class="fas fa-ban text-warning"></i>';
                            
                            el.innerHTML = `Останній запуск: ${log.date} ${statusIcon}`;
                        } else {
                            el.innerHTML = 'Останній запуск: Ніколи';
                        }
                    });
                }
            } catch(e) {
                console.error("Помилка завантаження історії", e);
            }
        }
        
        // Initial load
        updateLastRunDates();

        function viewHistory(scriptName) {
            const modal = new bootstrap.Modal(document.getElementById('historyModal'));
            document.getElementById('historyModalTitle').innerText = `Історія запусків: ${scriptName}`;
            const tbody = document.getElementById('historyTableBody');
            
            const logs = globalScriptHistory.filter(l => l.script === scriptName);
            
            tbody.innerHTML = '';
            if (logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center py-3 text-muted">Історія порожня</td></tr>';
            } else {
                logs.forEach(log => {
                    let statusBadge = '';
                    if (log.status === 'success') statusBadge = '<span class="badge bg-success">Успішно</span>';
                    else if (log.status === 'error') statusBadge = '<span class="badge bg-danger">Помилка</span>';
                    else if (log.status === 'aborted') statusBadge = '<span class="badge bg-warning text-dark">Зупинено</span>';
                    
                    const m = Math.floor(log.duration / 60).toString().padStart(2, '0');
                    const s = (log.duration % 60).toString().padStart(2, '0');
                    const durStr = log.duration > 0 ? `${m}:${s}` : '-';
                    
                    tbody.innerHTML += `
                        <tr>
                            <td>${log.date}</td>
                            <td>${statusBadge}</td>
                            <td>${durStr}</td>
                        </tr>
                    `;
                });
            }
            modal.show();
        }

        async function stopScript() {
            if (!confirm('Ви впевнені, що хочете перервати виконання?')) return;
            
            if (currentAbortController) {
                currentAbortController.abort();
            }
            
            try {
                await fetch(`${API_URL}?action=set_stop_flag`);
                showToast('Надіслано команду на зупинку...', 'warning');
            } catch(e) {}
        }

        async function runScript(scriptName, btnElement) {
            if (confirm(`Ви впевнені, що хочете запустити скрипт ${scriptName}?`)) {
                
                await fetch(`${API_URL}?action=clear_stop_flag`);
                
                const originalHtml = btnElement.innerHTML;
                let seconds = 0;
                let currentProgressText = '';
                let currentBaseText = '';
                btnElement.disabled = true;
                
                const stopBtn = btnElement.parentElement.querySelector('.stop-script-btn');
                if (stopBtn) stopBtn.style.display = 'inline-block';
                
                document.querySelectorAll('.run-script-btn').forEach(b => {
                    if (b !== btnElement) b.disabled = true;
                });
                
                const outputCard = document.getElementById('scriptOutputCard');
                const outputEl = document.getElementById('scriptOutput');
                outputCard.style.display = 'block';
                
                outputEl.innerHTML = `
                    <div class="progress mb-3" style="height: 15px;">
                        <div id="scriptProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                    </div>
                    <div id="scriptStages" class="mb-3" style="min-height: 50px; max-height: 250px; overflow-y: auto; font-family: monospace;">
                        <div class="text-info mb-2"><i class="fas fa-spinner fa-spin me-2"></i>Запуск ${scriptName}... Очікуйте...</div>
                    </div>
                    <div id="scriptFinalOutput" class="mt-3 text-muted" style="border-top: 1px dashed #555; padding-top: 10px; display: none;"></div>
                `;
                outputCard.scrollIntoView({ behavior: 'smooth' });

                const stagesDiv = document.getElementById('scriptStages');
                const progressBar = document.getElementById('scriptProgressBar');
                const finalOutput = document.getElementById('scriptFinalOutput');

                timerInterval = setInterval(() => {
                    seconds++;
                    const m = Math.floor(seconds / 60).toString().padStart(2, '0');
                    const s = (seconds % 60).toString().padStart(2, '0');
                    const timeStr = `${m}:${s}`;
                    
                    fetch('ajax_get_progress.php')
                        .then(res => res.json())
                        .then(data => {
                            let pctStr = '';
                            if (data.percent > 0) {
                                pctStr = ` ${data.percent}%`;
                                progressBar.style.width = data.percent + '%';
                                
                                if (data.text && data.text !== currentProgressText) {
                                    currentProgressText = data.text;
                                    const time = new Date().toLocaleTimeString('uk-UA');
                                    const baseText = data.text.replace(/[0-9]+/g, '');
                                    
                                    if (baseText === currentBaseText && stagesDiv.lastElementChild) {
                                        // Update the existing last element
                                        stagesDiv.lastElementChild.innerHTML = `<span class="text-secondary">[${time}]</span> <span class="text-light">${data.text}</span> <span class="badge bg-secondary ms-1">${data.percent}%</span>`;
                                    } else {
                                        // Append a new element
                                        currentBaseText = baseText;
                                        stagesDiv.innerHTML += `<div><span class="text-secondary">[${time}]</span> <span class="text-light">${data.text}</span> <span class="badge bg-secondary ms-1">${data.percent}%</span></div>`;
                                        stagesDiv.scrollTop = stagesDiv.scrollHeight;
                                    }
                                }
                            }
                            btnElement.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${timeStr}${pctStr}`;
                        }).catch(e => {
                            btnElement.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${timeStr}`;
                        });
                }, 1000);

                currentAbortController = new AbortController();
                const signal = currentAbortController.signal;

                try {
                    const res = await fetch(`${API_URL}?action=run_script&script=${encodeURIComponent(scriptName)}`, { signal });
                    const data = await res.json();
                    
                    clearInterval(timerInterval);
                    progressBar.classList.remove('progress-bar-animated');
                    progressBar.style.width = '100%';
                    progressBar.classList.add('bg-success');
                    
                    if (data.success) {
                        finalOutput.style.display = 'block';
                        finalOutput.innerHTML = `<strong class="text-success">Скрипт завершив роботу.</strong><br>${data.output || 'Виводу немає.'}`;
                        showToast(`Скрипт ${scriptName} завершив роботу`, 'success');
                    } else {
                        finalOutput.style.display = 'block';
                        finalOutput.innerHTML = `<strong class="text-danger">Помилка:</strong><br>${data.message}`;
                        showToast(data.message, 'danger');
                    }
                } catch (err) {
                    clearInterval(timerInterval);
                    progressBar.classList.remove('progress-bar-animated');
                    finalOutput.style.display = 'block';
                    if (err.name === 'AbortError') {
                        finalOutput.innerHTML = `<strong class="text-warning">Скрипт перервано користувачем.</strong>`;
                        progressBar.classList.add('bg-warning');
                        showToast('Виконання зупинено', 'warning');
                    } else {
                        finalOutput.innerHTML = `<span class="text-danger">Помилка мережі або таймаут виконання.</span>`;
                        progressBar.classList.add('bg-danger');
                        showToast('Сталася помилка при запиті', 'danger');
                    }
                } finally {
                    currentAbortController = null;
                    if (stopBtn) stopBtn.style.display = 'none';
                    document.querySelectorAll('.run-script-btn').forEach(b => b.disabled = false);
                    btnElement.innerHTML = originalHtml;
                    updateLastRunDates(); // Refresh history
                }
            }
        }
    </script>
</body>
</html>

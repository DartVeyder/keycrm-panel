<?php
$configFile = __DIR__ . '/refund_config.php';

// Завантаження старих налаштувань для збереження токенів
$oldConfig = [];
if (file_exists($configFile)) {
    require($configFile);
    $oldConfig = $config ?? [];
}

// Обробка AJAX POST запиту для збереження конфігурації
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (is_array($data)) {
        // Перезбираємо масив для безпеки та правильного форматування
        $newConfig = [];
        foreach ($data as $item) {
            $key = trim($item['key'] ?? '');
            if (empty($key)) continue;
            
            $token = trim($item['token'] ?? '');
            // Якщо токен замаскований або порожній, залишаємо старий
            if ($token === '********' || $token === '') {
                $token = $oldConfig[$key]['token'] ?? '';
            }
            
            $newConfig[$key] = [
                'token' => $token,
                'my_iban' => trim($item['my_iban'] ?? ''),
                'my_name' => trim($item['my_name'] ?? ''),
                'type' => trim($item['type'] ?? 'privatbank')
            ];
        }
        
        // Генеруємо PHP код
        $phpCode = "<?php\n\n\$config = " . var_export($newConfig, true) . ";\n";
        
        // Зберігаємо у файл
        if (file_put_contents($configFile, $phpCode) !== false) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Не вдалося зберегти файл. Перевірте права доступу до файлу refund_config.php.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Невірний формат даних.']);
    }
    exit;
}

// Завантаження поточних налаштувань для відображення на сторінці
if (file_exists($configFile)) {
    require_once($configFile);
}
if (!isset($config) || !is_array($config)) {
    $config = [];
}

// Маскуємо токени для безпеки на фронтенді
$safeConfig = $config;
foreach ($safeConfig as &$c) {
    if (!empty($c['token'])) {
        $c['token'] = '********';
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Налаштування повернень (ФОП)</title>
    <!-- Підключення Bootstrap 5 для стилізації -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .fop-card { margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e9ecef; }
        .fop-card .card-header { background-color: #fff; border-bottom: 1px solid #e9ecef; }
    </style>
</head>
<body>

<div class="container py-5" id="app">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Налаштування ФОП</h2>
            <p class="text-muted mb-0">Керування реквізитами для автоматичних повернень</p>
        </div>
        <div>
            <button class="btn btn-outline-success me-2" id="addBtn">+ Додати ФОП</button>
            <button class="btn btn-primary" id="saveBtn">Зберегти зміни</button>
        </div>
    </div>
    
    <div id="alertContainer"></div>

    <div id="fopList">
        <!-- Сюди будуть додаватися картки ФОП -->
    </div>
</div>

<!-- Шаблон для однієї картки ФОП -->
<template id="fopTemplate">
    <div class="card fop-card">
        <div class="card-header d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 text-primary fop-title">Новий ФОП</h5>
            <button class="btn btn-sm btn-outline-danger remove-btn">Видалити</button>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Ключ ФОП (Точна назва в KeyCRM)</label>
                    <input type="text" class="form-control fop-key" placeholder='напр. "ФОП Райша Приват"' required>
                    <div class="form-text">Ця назва має точно співпадати з назвою рахунку в KeyCRM (поле OR_1041).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Тип банку</label>
                    <select class="form-select fop-type">
                        <option value="privatbank">ПриватБанк</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">Токен API (Автоклієнт ПриватБанку)</label>
                    <input type="password" class="form-control fop-token" placeholder="Введіть новий токен для зміни">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Ваш IBAN (Рахунок ФОП)</label>
                    <input type="text" class="form-control fop-iban" placeholder="UA..." required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Офіційна назва ФОП (my_name)</label>
                    <input type="text" class="form-control fop-name" required>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    // Завантажуємо безпечні (замасковані) конфіги з PHP
    const currentConfig = <?php echo json_encode($safeConfig); ?>;
    
    const fopList = document.getElementById('fopList');
    const template = document.getElementById('fopTemplate');
    const addBtn = document.getElementById('addBtn');
    const saveBtn = document.getElementById('saveBtn');
    const alertContainer = document.getElementById('alertContainer');

    // Функція створення нової картки на основі шаблону
    function createFopCard(key = '', data = {token: '', my_iban: '', my_name: '', type: 'privatbank'}) {
        const clone = template.content.cloneNode(true);
        const card = clone.querySelector('.card');
        
        const keyInput = clone.querySelector('.fop-key');
        keyInput.value = key;
        clone.querySelector('.fop-token').value = data.token || '';
        clone.querySelector('.fop-iban').value = data.my_iban || '';
        clone.querySelector('.fop-name').value = data.my_name || '';
        clone.querySelector('.fop-type').value = data.type || 'privatbank';
        
        // Оновлюємо заголовок картки при введенні ключа
        const titleElement = clone.querySelector('.fop-title');
        titleElement.textContent = key || 'Новий ФОП';
        keyInput.addEventListener('input', (e) => {
            titleElement.textContent = e.target.value || 'Новий ФОП';
        });

        // Обробник видалення
        clone.querySelector('.remove-btn').addEventListener('click', function() {
            if(confirm('Ви впевнені, що хочете видалити цей запис?')) {
                card.remove();
            }
        });
        
        fopList.appendChild(clone);
    }

    // Ініціалізація даних при завантаженні сторінки
    function init() {
        if (Object.keys(currentConfig).length === 0) {
            createFopCard(); // Створюємо одну порожню форму якщо даних немає
        } else {
            for (const [key, data] of Object.entries(currentConfig)) {
                createFopCard(key, data);
            }
        }
    }

    // Збереження даних (відправка на сервер)
    async function saveConfig() {
        const cards = document.querySelectorAll('.fop-card');
        const dataToSave = [];
        let hasErrors = false;
        
        // Збираємо дані з усіх карток
        cards.forEach(card => {
            const key = card.querySelector('.fop-key').value.trim();
            const token = card.querySelector('.fop-token').value.trim();
            const my_iban = card.querySelector('.fop-iban').value.trim();
            const my_name = card.querySelector('.fop-name').value.trim();
            const type = card.querySelector('.fop-type').value;
            
            if(!key) {
                hasErrors = true;
                card.querySelector('.fop-key').classList.add('is-invalid');
            } else {
                card.querySelector('.fop-key').classList.remove('is-invalid');
            }
            
            if(key) {
                dataToSave.push({ key, token, my_iban, my_name, type });
            }
        });
        
        if (hasErrors) {
            showAlert('Будь ласка, заповніть Ключ для всіх записів.', 'danger');
            return;
        }

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Збереження...';
        
        try {
            const response = await fetch('?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dataToSave)
            });
            
            const result = await response.json();
            if (result.success) {
                showAlert('✅ Налаштування успішно збережено!', 'success');
            } else {
                showAlert(result.message || 'Помилка збереження', 'danger');
            }
        } catch (error) {
            showAlert('Помилка з\'єднання з сервером. Перевірте консоль.', 'danger');
            console.error(error);
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = 'Зберегти зміни';
        }
    }

    // Допоміжна функція для повідомлень
    function showAlert(message, type) {
        alertContainer.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show shadow-sm">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => alertContainer.innerHTML = '', 4000); // Приховати через 4 секунди
    }

    addBtn.addEventListener('click', () => createFopCard());
    saveBtn.addEventListener('click', saveConfig);

    init();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

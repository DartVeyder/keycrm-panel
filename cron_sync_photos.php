<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class/Base.php';
require_once __DIR__ . '/class/Prestashop.php';
require_once __DIR__ . '/class/MySQLDB.php';
require_once __DIR__ . '/class/SyncLogger.php';

// --- НАЛАШТУВАННЯ ---
$credentialsPath = GOOGLE_DRIVE_CREDENTIALS_PATH;
$rootFolderId = GOOGLE_DRIVE_ROOT_FOLDER_ID;

// Логер
$log = new SyncLogger(__DIR__ . '/logs');
$log->separator('СТАРТ СИНХРОНІЗАЦІЇ ФОТО');
$log->info('Лог-файл: ' . $log->getLogFile());
$log->info('Сайт призначення: ' . PRESTASHOP_SITE_URL);
$log->info('Етап 1: Підключення до бази даних та Prestashop API...');

// Підключення до БД та Prestashop
$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);
$prestashop = new Prestashop();

// Створюємо таблицю-трекер завантажених фото (якщо ще не існує)
$db->query("
    CREATE TABLE IF NOT EXISTS synced_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_product INT NOT NULL,
        google_file_id VARCHAR(255) NOT NULL,
        id_image INT NOT NULL,
        synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_product_file (id_product, google_file_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if (!file_exists($credentialsPath)) {
    $log->error('Файл ключів не знайдено: ' . $credentialsPath);
    die();
}

// Лічильники для підсумку
$stats = [
    'products'   => 0,
    'uploaded'   => 0,
    'skipped'    => 0,
    'linked'     => 0,
    'deleted'    => 0,
    'errors'     => 0,
];

try {
    $log->info('Етап 2: Ініціалізація та підключення до Google Drive API...');
    // Ініціалізація Google Drive
    $client = new \Google\Client();
    if (pathinfo($credentialsPath, PATHINFO_EXTENSION) === 'php') {
        $client->setAuthConfig(require $credentialsPath);
    } else {
        $client->setAuthConfig($credentialsPath);
    }
    $client->addScope(\Google\Service\Drive::DRIVE_READONLY);
    $service = new \Google\Service\Drive($client);

    $log->info('Google Drive підключено.');
    $log->info('Етап 3: Отримання списку товарів з Google Drive...');

    // 1. Отримуємо всі папки (Артикули)
    $query = "'" . $rootFolderId . "' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";
    $skuFolders = $service->files->listFiles(['q' => $query, 'fields' => 'files(id, name)'])->getFiles();

    foreach ($skuFolders as $skuFolder) {
        $parentSku = trim($skuFolder->getName());
        $log->separator("Етап 4: Обробка товару $parentSku");

        // Знаходимо товар в Prestashop
        $productData = $prestashop->getProductByReference($parentSku);
        if (empty($productData['products'])) {
            $log->warning("Товар $parentSku не знайдено в Prestashop. Пропускаємо.", $parentSku);
            continue;
        }
        $id_product = (int)$productData['products'][0]['id'];
        $stats['products']++;
        $log->info("Prestashop ID: $id_product", $parentSku);
        $seenGoogleFileIds = [];

        // Отримуємо актуальні комбінації безпосередньо з API Prestashop (це вирішує проблему з неактивними або новими товарами, яких ще немає в кеші)
        $apiProducts = $prestashop->getApiProducts($parentSku);
        $apiCombinations = is_array($apiProducts) ? $apiProducts : [];

        // 2. Отримуємо папки з кольорами всередині товару
        $colorQuery = "'" . $skuFolder->getId() . "' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";
        $colorFolders = $service->files->listFiles(['q' => $colorQuery, 'fields' => 'files(id, name)'])->getFiles();

        foreach ($colorFolders as $colorFolder) {
            $colorName = trim($colorFolder->getName());
            $colorCtx  = "$parentSku / $colorName";
            $log->info("Етап 5: Завантаження фото для кольору '$colorName'", $colorCtx);

            $combinationIdsToLink = [];
            
            // Спочатку шукаємо серед свіжих комбінацій з Prestashop
            foreach ($apiCombinations as $comb) {
                if (mb_strtolower(trim($comb['color'] ?? '')) === mb_strtolower($colorName)) {
                    if (!empty($comb['combination_id'])) {
                        $combinationIdsToLink[] = (int)$comb['combination_id'];
                    }
                }
            }

            // Якщо пусто, пробуємо старий метод (check_products_cache) як fallback
            if (empty($combinationIdsToLink)) {
                $dbRows = $db->query("SELECT sku FROM check_products_cache WHERE product_ref = ? AND color = ?", [$parentSku, $colorName]);
                if ($dbRows && count($dbRows) > 0) {
                    foreach ($dbRows as $row) {
                        $combSku  = $row['sku'];
                        $combData = $prestashop->getProductImagesByReference($combSku);
                        if (!empty($combData['combinations'])) {
                            $combinationIdsToLink[] = (int)$combData['combinations'][0]['id'];
                        }
                    }
                }
            }
            
            $combinationIdsToLink = array_unique($combinationIdsToLink);

            if (empty($combinationIdsToLink)) {
                $log->warning("Не знайдено комбінацій для кольору '$colorName' в базі.", $colorCtx);
            } else {
                $log->info("Знайдено комбінацій: " . implode(', ', $combinationIdsToLink), $colorCtx);
            }

            // 3. Завантажуємо фотографії з папки цього кольору
            $imageQuery = "'" . $colorFolder->getId() . "' in parents and mimeType contains 'image/' and trashed=false";
            $images = $service->files->listFiles(['q' => $imageQuery, 'fields' => 'files(id, name)', 'orderBy' => 'name'])->getFiles();

            foreach ($images as $image) {
                $googleFileId = $image->getId();
                $imageName    = $image->getName();
                $imageCtx     = "$colorCtx / $imageName";
                
                $seenGoogleFileIds[] = $googleFileId;

                // --- Перевірка дублікату ---
                $existing = $db->query(
                    "SELECT id_image FROM synced_photos WHERE id_product = ? AND google_file_id = ? LIMIT 1",
                    [$id_product, $googleFileId]
                );
                if (!empty($existing)) {
                    $id_image = (int)$existing[0]['id_image'];
                    $log->info("Вже завантажено (ID Image: $id_image). Пропускаємо.", $imageCtx);
                    $stats['skipped']++;

                    // Все одно прив'язуємо до комбінацій (якщо потрібно)
                    foreach ($combinationIdsToLink as $id_combination) {
                        $linked = $prestashop->linkImageToCombination($id_combination, $id_image);
                        if ($linked) {
                            $log->success("Прив'язано до комбінації ID: $id_combination", $imageCtx);
                            $stats['linked']++;
                        } else {
                            $log->error("Помилка прив'язки до комбінації ID: $id_combination", $imageCtx);
                            $stats['errors']++;
                        }
                    }
                    continue;
                }

                $log->info("Завантаження фото з Google Drive...", $imageCtx);

                // Зберігаємо файл тимчасово
                $content = $service->files->get($googleFileId, ['alt' => 'media']);
                $tmpPath = __DIR__ . '/public/tmp/' . $imageName;

                // Переконуємось, що папка існує
                if (!is_dir(__DIR__ . '/public/tmp')) {
                    mkdir(__DIR__ . '/public/tmp', 0777, true);
                }

                file_put_contents($tmpPath, $content->getBody()->getContents());
                $log->debug("Файл збережено тимчасово: $tmpPath", $imageCtx);

                // --- Конвертація та стиснення в стандартний JPG ---
                $tmpPathJpg = __DIR__ . '/public/tmp/' . uniqid() . '_' . pathinfo($imageName, PATHINFO_FILENAME) . '.jpg';
                $imageInfo = @getimagesize($tmpPath);
                $converted = false;
                if ($imageInfo) {
                    $maxWidth = 1200;
                    $maxHeight = 1200;
                    $origWidth = $imageInfo[0];
                    $origHeight = $imageInfo[1];
                    
                    // Обчислюємо нові розміри зі збереженням пропорцій
                    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
                    $newWidth = (int)($origWidth * $ratio);
                    $newHeight = (int)($origHeight * $ratio);

                    if ($imageInfo['mime'] == 'image/png') {
                        $im = @imagecreatefrompng($tmpPath);
                        if ($im) {
                            $bg = imagecreatetruecolor($newWidth, $newHeight);
                            $white = imagecolorallocate($bg, 255, 255, 255);
                            imagefill($bg, 0, 0, $white);
                            // Масштабуємо та зберігаємо прозорість як білий фон
                            imagecopyresampled($bg, $im, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                            imagejpeg($bg, $tmpPathJpg, 80); // Стиснення 80
                            imagedestroy($im);
                            imagedestroy($bg);
                            $converted = true;
                        }
                    } elseif ($imageInfo['mime'] == 'image/jpeg') {
                        $im = @imagecreatefromjpeg($tmpPath);
                        if ($im) {
                            $bg = imagecreatetruecolor($newWidth, $newHeight);
                            imagecopyresampled($bg, $im, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                            imagejpeg($bg, $tmpPathJpg, 80); // Стиснення 80
                            imagedestroy($im);
                            imagedestroy($bg);
                            $converted = true;
                        }
                    }
                }

                if ($converted) {
                    unlink($tmpPath);
                    $tmpPath = $tmpPathJpg;
                    $log->debug("Зображення стиснуто (якість 80, до {$newWidth}x{$newHeight}) та конвертовано: $tmpPath", $imageCtx);
                }

                // 4. Відправляємо фото в Prestashop (Товар)
                $id_image = $prestashop->uploadProductImage($id_product, $tmpPath);

                if ($id_image) {
                    $log->success("Фото завантажено до Prestashop (ID Image: $id_image)", $imageCtx);
                    $stats['uploaded']++;

                    // Зберігаємо в трекер щоб не завантажувати повторно
                    $db->query(
                        "INSERT IGNORE INTO synced_photos (id_product, google_file_id, id_image) VALUES (?, ?, ?)",
                        [$id_product, $googleFileId, $id_image]
                    );

                    // 5. Прив'язуємо фото до всіх знайдених комбінацій
                    foreach ($combinationIdsToLink as $id_combination) {
                        $linked = $prestashop->linkImageToCombination($id_combination, $id_image);
                        if ($linked) {
                            $log->success("Прив'язано до комбінації ID: $id_combination", $imageCtx);
                            $stats['linked']++;
                        } else {
                            $log->error("Помилка прив'язки до комбінації ID: $id_combination", $imageCtx);
                            $stats['errors']++;
                        }
                    }
                } else {
                    $log->error("Не вдалося завантажити фото в Prestashop.", $imageCtx);
                    $stats['errors']++;
                }

                // Видаляємо тимчасовий файл
                unlink($tmpPath);
                $log->debug("Тимчасовий файл видалено.", $imageCtx);
            }
        }
        
        // --- Перевірка видалених фотографій ---
        $log->info("Етап 6: Перевірка та видалення неактуальних фото...", $parentSku);
        $savedPhotos = $db->query("SELECT id, google_file_id, id_image FROM synced_photos WHERE id_product = ?", [$id_product]);
        if ($savedPhotos) {
            foreach ($savedPhotos as $photo) {
                if (!in_array($photo['google_file_id'], $seenGoogleFileIds)) {
                    $log->warning("Фото видалено з Google Drive. Видаляємо з Prestashop (ID Image: {$photo['id_image']})...", $parentSku);
                    if ($prestashop->deleteProductImage($id_product, $photo['id_image'])) {
                        $db->query("DELETE FROM synced_photos WHERE id = ?", [$photo['id']]);
                        $log->success("Фото успішно видалено.", $parentSku);
                        $stats['deleted']++;
                    } else {
                        $log->error("Помилка видалення фото з Prestashop.", $parentSku);
                        $stats['errors']++;
                    }
                }
            }
        }
    }

    // Підсумок
    $log->separator('ПІДСУМОК');
    $log->info("Товарів оброблено:      {$stats['products']}");
    $log->success("Фото завантажено:       {$stats['uploaded']}");
    $log->info("Фото пропущено (дублі):  {$stats['skipped']}");
    $log->success("Прив'язок до комбінацій: {$stats['linked']}");
    if ($stats['deleted'] > 0) {
        $log->warning("Фото видалено:           {$stats['deleted']}");
    }
    if ($stats['errors'] > 0) {
        $log->error("Помилок:                {$stats['errors']}");
    }
    $log->separator('СИНХРОНІЗАЦІЯ ЗАВЕРШЕНА');

} catch (\Exception $e) {
    $log->error("Критична помилка: " . $e->getMessage());
    $log->debug($e->getTraceAsString());
}

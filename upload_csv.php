<?php

// Встановлення заголовків для CORS та JSON
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// Перевірка методу
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Only POST method allowed."]);
    exit;
}

// Перевірка, чи файл передано
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["error" => "No file uploaded or upload error."]);
    exit;
}

// Перевірка MIME типу
$allowedMimeTypes = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
if (!in_array($_FILES['file']['type'], $allowedMimeTypes)) {
    http_response_code(415);
    echo json_encode(["error" => "Invalid file type. Only CSV allowed."]);
    exit;
}

// Створення унікального імені файлу
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$originalName = basename($_FILES['file']['name']);
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$uniqueName =  'products_1c.' . $extension;
$destination = $uploadDir . $uniqueName;

// Переміщення файлу
if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
    echo json_encode([
        "success" => true,
        "message" => "File uploaded successfully.",
        "file" => $uniqueName,
        "path" => "/uploads/" . $uniqueName
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to move uploaded file."]);
}

<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

const MAX_PHOTO_SIZE = 2 * 1024 * 1024;  

$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? '';
$action   = $_GET['action'] ?? null;
$id       = isset($_GET['id']) ? (int) $_GET['id'] : null;

function readJsonBody(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function respond(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($resource === 'types' && $method === 'GET') {
    respond(array_map(fn(CapacitorType $t) => $t->toArray(), array_values($repository->getTypes())));
}

if ($resource === 'types' && $method === 'POST') {
    $data = readJsonBody();
    $name = trim((string) ($data['name'] ?? ''));

    if ($name === '' || mb_strlen($name) > 50) {
        respond(['error' => 'Поле name обов’язкове (до 50 символів)'], 400);
    }
    if ($repository->typeNameExists($name)) {
        respond(['error' => 'Такий тип уже існує'], 409);
    }
    respond($repository->createType($name)->toArray(), 201);
}

if ($resource === 'capacitors' && $method === 'GET') {
    respond(array_map(fn(Capacitor $c) => $c->toArray(), array_values($repository->findAll())));
}

if ($resource === 'capacitors' && $method === 'POST' && $action === null) {
    $data = readJsonBody();
    $designation = trim((string) ($data['designation'] ?? ''));
    $type        = $repository->findTypeById((int) ($data['type_id'] ?? 0));
    $capacitance = (float) ($data['capacitance'] ?? 0);
    $voltage     = (float) ($data['voltage'] ?? 0);

    if ($designation === '' || mb_strlen($designation) > 50 || $type === null
        || $capacitance <= 0 || $voltage <= 0) {
        respond(['error' => 'Некоректні дані конденсатора'], 400);
    }
    respond($repository->create($designation, $type, $capacitance, $voltage)->toArray(), 201);
}

if ($resource === 'capacitors' && $action === 'photo' && $method === 'POST') {
    if ($id === null || $repository->findById($id) === null) {
        respond(['error' => 'Конденсатор не знайдено'], 404);
    }
    $file = $_FILES['photo'] ?? null;
    if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
        respond(['error' => 'Файл не надіслано'], 400);
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
        || $file['size'] > MAX_PHOTO_SIZE) {
        respond(['error' => 'Файл завеликий (максимум 2 МБ)'], 413);
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond(['error' => 'Помилка завантаження файлу'], 400);
    }

    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mimeType = mime_content_type($file['tmp_name']);   // тип за фактичним вмістом
    if (!isset($allowedTypes[$mimeType])) {
        respond(['error' => 'Дозволені лише зображення JPEG, PNG чи WEBP'], 400);
    }

    $filename = uniqid('capacitor_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $filename)) {
        respond(['error' => 'Не вдалося зберегти файл'], 500);
    }

    $repository->attachPhoto($id, $filename);
    respond($repository->findById($id)->toArray());
}

if ($resource === 'parameters' && $method === 'POST') {
    $data = readJsonBody();
    $capacitorId = (int) ($data['capacitor_id'] ?? 0);
    $name  = trim((string) ($data['name'] ?? ''));
    $value = trim((string) ($data['value'] ?? ''));

    if ($name === '' || $value === '' || $repository->findById($capacitorId) === null) {
        respond(['error' => 'Некоректні дані параметра'], 400);
    }
    if (preg_match('/[|:]/', $name) || str_contains($value, '|')) {
        respond(['error' => 'Назва не може містити символи «|» і «:», значення — символ «|»'], 400);
    }
    if (mb_strlen($name) > 50 || mb_strlen($value) > 100) {
        respond(['error' => 'Занадто довгий текст (назва — до 50, значення — до 100 символів)'], 400);
    }
    $repository->addParameterToCapacitor($capacitorId, $name, $value);
    respond($repository->findById($capacitorId)->toArray(), 201);
}

respond(['error' => 'Невідомий маршрут'], 404);

<?php


// Устанавливаем куки ДО любого вывода
function set_sort_cookie($value) {
    return setcookie('sortten', $value, time() + 86400 * 30, '/', '', false, true);
}

// Устанавливаем куки ДО любого вывода
function set_type_sort_cookie($value) {
    return setcookie('sorttype', $value, time() + 86400 * 30, '/', '', false, true);
}

// Основная логика
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sortten'])) {
    $sortValue = $_POST['sortten'];

    $type = $_POST['type'];

    if($type){
        set_type_sort_cookie($type);
    }

    if (set_sort_cookie($sortValue)) {

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// Ошибка
header('Content-Type: application/json');
http_response_code(400);
echo json_encode(['status' => 'error']);
exit;
<?php
require 'app/common.php';

// получаем идентификатор лота
$id = $_GET['id'];
if (! $lots_list[$id]) {
    http_response_code(404);
    exit();
}

// описание лота
$result = mysqli_query($link, 'SELECT description, price FROM lots WHERE id = ' . $id);
if (! $result) {
    $query_errors[] = 'Нет доступа к описанию лота.';
}
else {
    $lots_list[$id] = array_merge($lots_list[$id], mysqli_fetch_assoc($result));
}

$bet_step = $lots_list[$id]['step'] ?? 500; // шаг ставки
$price = $lots_list[$id]['price']; // начальная цена

// получаем историю ставок для лота
$bets = [];
$result = mysqli_query($link, 'SELECT bets.id, create_ts, price, user_id, name '
    . 'FROM users LEFT JOIN bets on users.id = bets.user_id '
    . 'WHERE lot_id = ' . $id . ' AND bets.id IS NOT NULL '
    . 'ORDER BY create_ts DESC');
if (! $result) {
    $query_errors[] = 'Нет доступа к ставкам.';
}
else {
    while ($row = mysqli_fetch_assoc($result)) {
        $bets[] = [
            'name' => $row['name'],
            'price' => $row['price'],
            'ts' => $row['create_ts']
        ];
        // автор лота не может делать ставку
        if ($row['user_id'] == $_SESSION['user']['id']) {
            $_SESSION['bet_done'][$row['user_id']] = true;
        }
    }
    $count = mysqli_num_rows($result);
}

// максимальная цена
foreach ($bets as $k => $val) {
    if ($val['price'] > $price) {
        $price = $val['price'];
    }
}

// добавление ставки
if (isset($_POST['cost'])) {
    $cost_min = $price + $bet_step;
    if (is_numeric($_POST['cost']) && $_POST['cost'] > $cost_min) {
        $cost = floor($_POST['cost']);
    }
    else { // если ставка не целочисленна и меньше минимума
        $cost = $cost_min;
    }
    // пишем новую ставку в базу
    $result = mysqli_query($link, 'INSERT INTO bets SET create_ts = ' . $time
        . ', price = ' . $cost . ', lot_id = ' . $id . ', user_id = ' . $user_id);
    if (! $result) {
        $query_errors[] = 'Невозможно записать ставку.';
    }
    else {
        // запрет на повторную ставку для лота
        if (! isset($_SESSION['bet_done'][$id])) {
            $_SESSION['bet_done'][$id] = true;
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
    }
}


// получаем HTML-код тела страницы
$layout_data['content'] = include_template('lot', [
    'id' => $id,
    'categories_list' => $categories_list,
    'lots_list' => $lots_list,
    'bets' => $bets,
    'count' => $count,
    'price' => $price,
    'expire' => $lots_list[$id]['expire_ts'],
    'expired' => ($lots_list[$id]['expire_ts'] - $time > 0) ? false : true,
    'bet_min' => $price + $bet_step,
    'img' => true,
    'real' => true,
    'empty' => isset($_SESSION['bet_done'][$id]) ? false : true,
    'self' => $lots_list[$id]['user_id'] == $_SESSION['user']['id'] ? true : false
]);

// получаем итоговый HTML-код
$layout_data['title'] = $lots_list[$id]['name'];
print(layout($query_errors, $layout_data));

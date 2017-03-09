<?php

/**
 * ДАННЫЕ ДЛЯ ПОДКЛЮЧЕНИЯ К ПЛАТЕЖНОМУ ШЛЮЗУ
 *
 * USERNAME        Логин магазина, полученный при подключении.
 * PASSWORD        Пароль магазина, полученный при подключении.
 * GATEWAY_URL    Адрес платежного шлюза.
 * RETURN_URL    Адрес, на который надо перенаправить пользователя
 *                в случае успешной оплаты.
 */
define('USERNAME', 'USERNAME');
define('PASSWORD', 'PASSWORD');
define('GATEWAY_URL', 'https://server/payment/rest/'); #необходимо заменить!  #Should be replaced!
define('RETURN_URL', 'http://your.site/rest.php');          #необходимо заменить!  #Should be replaced!

/**
 * ФУНКЦИЯ ДЛЯ ВЗАИМОДЕЙСТВИЯ С ПЛАТЕЖНЫМ ШЛЮЗОМ
 *
 * Для отправки POST запросов на платежный шлюз используется
 * стандартная библиотека cURL.
 *
 * ПАРАМЕТРЫ
 *        method        Метод из API.
 *        data        Массив данных.
 *
 * ОТВЕТ
 *        response    Ответ.
 */
function gateway($method, $data)
{
    $curl = curl_init(); // Инициализируем запрос
    curl_setopt_array($curl, array(
        CURLOPT_URL => GATEWAY_URL . $method, // Полный адрес метода
        CURLOPT_RETURNTRANSFER => true, // Возвращать ответ
        CURLOPT_POST => true, // Метод POST
        CURLOPT_POSTFIELDS => http_build_query($data) // Данные в запросе
    ));
    $response = curl_exec($curl); // Выполненяем запрос

    $response = json_decode($response, true); // Декодируем из JSON в массив
    curl_close($curl); // Закрываем соединение
    return $response; // Возвращаем ответ
}

/**
 * ВЫВОД ФОРМЫ НА ЭКРАН
 */
if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['orderId'])) {
    echo '
		<form method="post" action="/rest.php">
			<label>Order number</label><br />
			<input type="text" name="orderNumber" /><br />
			<label>Amount</label><br />
			<input type="text" name="amount" /><br />
			<button type="submit">Submit</button>
		</form>
	';
} /**
 * ОБРАБОТКА ДАННЫХ ИЗ ФОРМЫ
 */
else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = array(
        'userName' => USERNAME,
        'password' => PASSWORD,
        'orderNumber' => urlencode($_POST['orderNumber']),
        'amount' => urlencode($_POST['amount']),
        'returnUrl' => RETURN_URL
    );

    /**
     * ЗАПРОС РЕГИСТРАЦИИ ОДНОСТАДИЙНОГО ПЛАТЕЖА В ПЛАТЕЖНОМ ШЛЮЗЕ
     *        register.do
     *
     * ПАРАМЕТРЫ
     *        userName            Логин магазина.
     *        password            Пароль магазина.
     *        orderNumber            Уникальный идентификатор заказа в магазине.
     *        amount                Сумма заказа.
     *        returnUrl            Адрес, на который надо перенаправить пользователя в случае успешной оплаты.
     *
     * ОТВЕТ
     *        В случае ошибки:
     *            errorCode        Код ошибки. Список возможных значений приведен в таблице ниже.
     *            errorMessage    Описание ошибки.
     *
     *        В случае успешной регистрации:
     *            orderId            Номер заказа в платежной системе. Уникален в пределах системы.
     *            formUrl            URL платежной формы, на который надо перенаправить браузер клиента.
     *
     *    Код ошибки        Описание
     *        0            Обработка запроса прошла без системных ошибок.
     *        1            Заказ с таким номером уже зарегистрирован в системе.
     *        3            Неизвестная (запрещенная) валюта.
     *        4            Отсутствует обязательный параметр запроса.
     *        5            Ошибка значения параметра запроса.
     *        7            Системная ошибка.
     */
    $response = gateway('register.do', $data);

    /**
     * ЗАПРОС РЕГИСТРАЦИИ ДВУХСТАДИЙНОГО ПЛАТЕЖА В ПЛАТЕЖНОМ ШЛЮЗЕ
     *        registerPreAuth.do
     *
     * Параметры и ответ точно такие же, как и в предыдущем методе.
     * Необходимо вызывать либо register.do, либо registerPreAuth.do.
     */
//	$response = gateway('registerPreAuth.do', $data);

    if (isset($response['errorCode'])) { // В случае ошибки вывести ее
        echo 'Ошибка #' . $response['errorCode'] . ': ' . $response['errorMessage'];
    } else { // В случае успеха перенаправить пользователя на плетжную форму
        header('Location: ' . $response['formUrl']);
        die();
    }
} /**
 * ОБРАБОТКА ДАННЫХ ПОСЛЕ ПЛАТЕЖНОЙ ФОРМЫ
 */
else if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['orderId'])) {
    $data = array(
        'userName' => USERNAME,
        'password' => PASSWORD,
        'orderId' => $_GET['orderId']
    );

    /**
     * ЗАПРОС СОСТОЯНИЯ ЗАКАЗА
     *        getOrderStatus
     *
     * ПАРАМЕТРЫ
     *        userName            Логин магазина.
     *        password            Пароль магазина.
     *        orderId                Номер заказа в платежной системе. Уникален в пределах системы.
     *
     * ОТВЕТ
     *        ErrorCode            Код ошибки. Список возможных значений приведен в таблице ниже.
     *        OrderStatus            По значению этого параметра определяется состояние заказа в платежной системе.
     *                            Список возможных значений приведен в таблице ниже. Отсутствует, если заказ не был найден.
     *
     *    Код ошибки        Описание
     *        0            Обработка запроса прошла без системных ошибок.
     *        2            Заказ отклонен по причине ошибки в реквизитах платежа.
     *        5            Доступ запрещён;
     *                    Пользователь должен сменить свой пароль;
     *                    Номер заказа не указан.
     *        6            Неизвестный номер заказа.
     *        7            Системная ошибка.
     *
     *    Статус заказа    Описание
     *        0            Заказ зарегистрирован, но не оплачен.
     *        1            Предавторизованная сумма захолдирована (для двухстадийных платежей).
     *        2            Проведена полная авторизация суммы заказа.
     *        3            Авторизация отменена.
     *        4            По транзакции была проведена операция возврата.
     *        5            Инициирована авторизация через ACS банка-эмитента.
     *        6            Авторизация отклонена.
     */
    $response = gateway('getOrderStatus.do', $data);

    // Вывод кода ошибки и статус заказа
    echo '
		<b>Error code:</b> ' . $response['ErrorCode'] . '<br />
		<b>Order status:</b> ' . $response['OrderStatus'] . '<br />
	';
}

?>
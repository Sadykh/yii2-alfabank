<?php
namespace sadykh\alfabank;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Object;

class Merchant extends Object
{
    const PAGE_VIEW_DESKTOP = 'DESKTOP';
    const PAGE_VIEW_MOBILE = 'MOBILE';

    const GATEWAY_REGISTER = 'register.do';
    const GATEWAY_REGISTER_PRE_AUTH = 'registerPreAuth.do';
    const GATEWAY_GET_ORDER_STATUS = 'getOrderStatus.do';
    const ORDER_STATUS_ERROR_CODE_SUCCESS = 0;

    public $username;
    public $password;
    public $gatewayUrl;
    public $returnUrl = 'https://auth.robokassa.ru/Merchant/Index.aspx';
    public $failUrl = 'https://auth.robokassa.ru/Merchant/Index.aspx';
    public $currency = 810;                                                // Код валюты платежа ISO 4217. Если не указан, считается равным 810 (российские рубли).

    public function init()
    {
        if (!$this->username || !$this->password || !$this->gatewayUrl || !$this->returnUrl) {
            throw new InvalidConfigException("Invalid config found.");
        }
    }

    /**
     * Запрос к шлюзу
     * @param $method
     * @param $data
     * @return mixed
     */
    public function gateway($method, $data)
    {
        $curl = curl_init();                                // Инициализируем запрос
        curl_setopt_array($curl, [
            CURLOPT_URL => GATEWAY_URL . $method,           // Полный адрес метода
            CURLOPT_RETURNTRANSFER => true,                 // Возвращать ответ
            CURLOPT_POST => true,                           // Метод POST
            CURLOPT_POSTFIELDS => http_build_query($data)   // Данные в запросе
        ]);
        $response = curl_exec($curl);                       // Выполненяем запрос

        $response = json_decode($response, true);           // Декодируем из JSON в массив
        curl_close($curl);                                  // Закрываем соединение
        return $response;                                   // Возвращаем ответ
    }

    /**
     * @param $orderNumber int  Номер (идентификатор) заказа в системе магазина, уникален для каждого магазина в пределах системы
     * @param $amount   int Сумма платежа в копейках (или центах)
     * @param null $bindingId string Идентификатор связки, созданной ранее. Может использоваться, только если у магазина есть
     * разрешение на работу со связками. Если этот параметр передаётся в данном запросе, то это
     * означает:
     * 1. Данный заказ может быть оплачен только с помощью связки;
     * 2. Плательщик будет перенаправлен на платёжную страницу, где требуется только ввод CVC.
     * @param null $clientId Номер (идентификатор) клиента в системе магазина. Используется для реализации функционала
     * связок. Может присутствовать, если магазину разрешено создание связок.
     * @param null $description string Описание заказа в свободной форме
     * @param null $returnUrl string Адрес, на который требуется перенаправить пользователя в случае успешной оплаты.
     * @param null $failUrl string  Адрес, на который требуется перенаправить пользователя в случае неуспешной оплаты.
     * @param string $pageView string  какие страницы платёжного интерфейса должны загружаться для клиента
     * @return $this|string
     * @throws InvalidConfigException
     */
    public function payment($orderNumber, $amount, $clientId = null, $bindingId = null, $description = null, $returnUrl = null,
                            $failUrl = null, $pageView = self::PAGE_VIEW_DESKTOP)
    {
        if ($returnUrl == null) {
            $returnUrl = $this->returnUrl;
        }
        $data = [
            'userName' => $this->username,
            'password' => $this->password,
            'orderNumber' => urlencode($orderNumber),
            'amount' => urlencode($amount),
            'failUrl' => $failUrl,
            'returnUrl' => $returnUrl,
            'pageView' => $pageView,
            'clientId' => $clientId,
            'bindingId' => $bindingId,
        ];
        if ($bindingId && $clientId == null) {
            throw new InvalidConfigException("Required argument - clientId.");
        }
        if ($bindingId) {
            $data['bindingId'] = $bindingId;
        }
        if ($description) {
            $data['description'] = $description;
        }

        return $this->gateway(self::GATEWAY_REGISTER, $data);
    }


    /**
     * Получить список вариантов ответа для OrderStatus
     * @return array
     */
    public static function getOrderStatusList()
    {
        return [
            0 => 'Заказ зарегистрирован, но не оплачен',
            1 => 'Предавторизованная сумма захолдирована (для двухстадийных платежей)',
            2 => 'Проведена полная авторизация суммы заказа',
            3 => 'Авторизация отменена',
            4 => 'По транзакции была проведена операция возврата',
            5 => 'Инициирована авторизация через ACS банка-эмитента',
            6 => 'Авторизация отклонена',
        ];
    }

    /**
     * Получиь список вариантов ответа для ErrorCode
     * @return array
     */
    public static function getOrderErrorCodesList()
    {
        return [
            0 => 'Обработка запроса прошла без системных ошибок',
            2 => 'Заказ отклонен по причине ошибки в реквизитах платежа',
            5 => 'Доступ запрещён / Пользователь должен сменить свой пароль / [orderId] не указан',
            6 => 'Неверный номер заказа',
            7 => 'Системная ошибка'
        ];
    }

    /**
     * Получить статус оплаты
     * @param $orderID
     * @return mixed
     */
    public function getStatusByOrderId($orderID)
    {
        $data = array(
            'userName' => $this->username,
            'password' => $this->password,
            'orderId' => $orderID,
        );

        return $this->gateway(self::GATEWAY_GET_ORDER_STATUS, $data);
    }
}
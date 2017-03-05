<?php
namespace sadykh\alfabank;

use yii\base\Action;
use yii\base\InvalidConfigException;

class BaseAction extends Action
{
    public $merchant = 'alfabank';

    public $callback;

    /**
     * @param Merchant $merchant Merchant.
     * @param $orderId
     * @return mixed
     * @throws \yii\base\InvalidConfigException
     */
    protected function callback($merchant, $orderId)
    {
        if (!is_callable($this->callback)) {
            throw new InvalidConfigException('"' . get_class($this) . '::callback" should be a valid callback.');
        }
        $response = call_user_func($this->callback, $merchant, $orderId);
        return $response;
    }
}

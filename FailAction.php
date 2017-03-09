<?php
namespace sadykh\alfabank;

use Yii;
use yii\web\BadRequestHttpException;

class FailAction extends BaseAction
{
    /**
     * Runs the action.
     */
    public function run()
    {
        if (!isset($_REQUEST['orderId'])) {
            throw new BadRequestHttpException;
        }
        /** @var Merchant $merchant */
        $merchant = Yii::$app->get($this->merchant);

        return $this->callback($merchant, $_REQUEST['orderId']);
    }
}

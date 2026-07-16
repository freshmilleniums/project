<?php
namespace backend\controllers;

use Yii;
use yii\web\Controller;

class BaseController extends Controller
{
    /**
     * This method is invoked right before an action is executed.
     * Updates last_activity timestamp for authenticated users.
     *
     * @param \yii\base\Action $action the action to be executed.
     * @return bool whether the action should continue to run.
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            // Update last_activity for authenticated users
            if (!Yii::$app->user->isGuest) {
                $user = Yii::$app->user->identity;
                if ($user) {
                    $user->updateAttributes(['last_activity' => time()]);
                }
            }
            return true;
        }
        return false;
    }
}
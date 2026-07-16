<?php
namespace backend\models;

use Yii;
use yii\helpers\ArrayHelper;
use yii\base\InvalidArgumentException;

class MultipleModel extends \yii\base\Model
{
    /**
     * Creates and populates a set of models.
     *
     * @param string $modelClass The model class name
     * @param array $multipleModels Existing models array
     * @return array Array of model instances
     * @throws InvalidArgumentException If model class doesn't exist
     */
    public static function createMultiple($modelClass, $multipleModels = [])
    {
        // Check if model class exists
        if (!class_exists($modelClass)) {
            throw new InvalidArgumentException("Model class {$modelClass} does not exist");
        }

        $model = new $modelClass();
        $formName = $model->formName();
        $post = Yii::$app->request->post($formName);
        $models = [];

        // Prepare existing models
        if (!empty($multipleModels) && is_array($multipleModels)) {
            $keys = array_keys(ArrayHelper::map($multipleModels, 'id', 'id'));
            $multipleModels = array_combine($keys, $multipleModels);
        }

        // Process POST data
        if ($post && is_array($post)) {
            foreach ($post as $i => $item) {
                if (is_array($item) &&
                    isset($item['id']) &&
                    !empty($item['id']) &&
                    isset($multipleModels[$item['id']])) {
                    // Use existing model
                    $models[$i] = $multipleModels[$item['id']];
                } else {
                    // Create new model
                    $models[$i] = new $modelClass();
                }
            }
        }

        return $models;
    }
}
<?php
use yii\helpers\Html;
use Yii;
use yii\widgets\DetailView;

$this->title = 'Registration Successful';
?>
<div class="container">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>The user has been successfully registered. Below are the login credentials:</p>

    <div class="alert alert-success">
        <p><strong>Email:</strong> <?= Html::encode($email) ?></p>
        <p><strong>Password:</strong> <?= Html::encode($password) ?></p>
    </div>

    <p>Please share this information with the user securely.</p>
    <p><?= Html::a('Back to Users List', ['/users'], ['class' => 'btn btn-primary']) ?></p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'sequential_number',
            'email:email',
            'first_name',
            'last_name',
            'phone_number',
            'home_phone',
            'address',
            'city',
            'state',
            'country',
            'zip_code',
            'position_title',
            'hr_source',
            [
                'attribute' => 'created_at',
                'format' => ['datetime', 'php:Y-m-d H:i:s'],
                'label' => 'Created At',
            ],
            [
                'attribute' => 'updated_at',
                'format' => ['datetime', 'php:Y-m-d H:i:s'],
                'label' => 'Updated At',
            ],
            [
                'attribute' => 'role',
                'value' => function ($model) {
                    $roles = Yii::$app->authManager->getRolesByUser($model->id);
                    if (!$roles) {
                        return 'No roles assigned';
                    }

                    $roleDescriptions = [];
                    foreach ($roles as $role) {
                        $roleDescriptions[] = $role->description ?: $role->name;
                    }

                    return implode(', ', $roleDescriptions);
                },
                'label' => 'Assigned Roles',
            ],
        ],
    ]) ?>
</div>
<?php

use yii\helpers\Html;
use yii\helpers\Url;
use backend\models\TestItem;
use yii\web\JqueryAsset;

/* @var $this yii\web\View */
/* @var $testItems backend\models\TestItem[] */

$this->title = 'Test Management';

JqueryAsset::register($this);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js', [
    'depends' => [JqueryAsset::class]
]);

$js = "
var questionsList = document.getElementById('questions-list');

if (questionsList && questionsList.children.length > 0) {
    var sortable = Sortable.create(questionsList, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd: function(evt) {
            var items = $('#questions-list .question-item');
            var ids = [];

            items.each(function() {
                ids.push($(this).data('id'));
            });

            // Send new order to server using jQuery AJAX
            $.ajax({
                url: '" . Url::to(['update-sort']) . "',
                type: 'POST',
                data: {
                    ids: ids,
                    '" . Yii::$app->request->csrfParam . "': '" . Yii::$app->request->csrfToken . "'
                },
                dataType: 'json',
                success: function(data) {
                    if (!data.success) {
                        console.error('Error updating sort order');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                }
            });
        }
    });
}

// Confirm delete with jQuery
$(document).on('click', 'a[data-confirm]', function(e) {
    var message = $(this).data('confirm');
    if (!confirm(message)) {
        e.preventDefault();
        return false;
    }
});
";

$this->registerJs($js, \yii\web\View::POS_READY);
?>

<div class="test-settings">

    <div class="questions-list" id="questions-list">
        <?php if (empty($testItems)): ?>
            <div class="alert alert-info">
                <p>No questions found. Add your first question.</p>
            </div>
        <?php else: ?>
            <?php foreach ($testItems as $item): ?>
                <div class="question-item" data-id="<?= $item->id ?>">
                    <div class="question-header">
                        <span class="drag-handle">⋮⋮</span>
                        <strong><?= Html::encode($item->question_name) ?></strong>
                        <span class="question-type badge"><?= $item->getTypeName() ?></span>
                        <div class="question-actions">
                            <?= Html::a('Edit', ['update-question', 'id' => $item->id], [
                                'class' => 'btn btn-primary btn-sm'
                            ]) ?>
                            <?= Html::a('Delete', ['delete-question', 'id' => $item->id], [
                                'class' => 'btn btn-danger btn-sm',
                                'data-confirm' => 'Are you sure you want to delete this question?',
                                'data-method' => 'post'
                            ]) ?>
                        </div>
                    </div>

                    <?php if (in_array($item->type, [TestItem::TYPE_RADIO, TestItem::TYPE_CHECKBOX])): ?>
                        <div class="question-options">
                            <?php foreach ($item->options as $option): ?>
                                <div class="option-item">
                                    <?php if ($item->type == TestItem::TYPE_RADIO): ?>
                                        <input type="radio" disabled> <?= Html::encode($option->option_text) ?>
                                    <?php else: ?>
                                        <input type="checkbox" disabled> <?= Html::encode($option->option_text) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="add-question-section">
        <?= Html::a('Add Question', ['create-question'], ['class' => 'btn btn-primary']) ?>
    </div>
</div>

<?php
$css = "
.question-item {
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 10px;
    background: #fff;
    cursor: move;
}

.question-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.drag-handle {
    cursor: grab;
    font-size: 16px;
    color: #999;
}

.drag-handle:active {
    cursor: grabbing;
}

.question-type {
    background: #007bff;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.question-actions {
    margin-left: auto;
}

.question-options {
    margin-left: 30px;
    padding: 10px 0;
}

.option-item {
    margin: 5px 0;
    padding: 5px;
    background: #f8f9fa;
    border-radius: 3px;
}

.add-question-section {
    margin-top: 20px;
    text-align: center;
}

.btn {
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    display: inline-block;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
}

.alert {
    padding: 12px;
    border-radius: 4px;
    margin: 20px 0;
}

.alert-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.sortable-ghost {
    opacity: 0.4;
}

.sortable-chosen {
    background: #f0f8ff;
}
";

$this->registerCss($css);
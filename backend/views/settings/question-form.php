<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use backend\models\TestItem;
use yii\web\JqueryAsset;

/* @var $this yii\web\View */
/* @var $model backend\models\TestItem */

$isUpdate = !$model->isNewRecord;
$this->title = $isUpdate ? 'Update Question' : 'Create Question';
JqueryAsset::register($this);

$js = "
var \$questionType = $('#question-type');
var \$optionsSection = $('#options-section');
var \$optionsList = $('#options-list');
var \$addOptionBtn = $('#add-option');
var isUpdate = " . json_encode($isUpdate) . ";
var currentType = " . json_encode($model->type) . ";

// Initialize options section visibility
function initializeOptionsSection() {
    var selectedType = parseInt(\$questionType.val());
    if (selectedType === 2 || selectedType === 3) { // Radio or Checkbox
        \$optionsSection.show();
        updateRemoveButtons();
    } else {
        \$optionsSection.hide();
    }
}

// Initialize on page load
if (isUpdate && (currentType === 2 || currentType === 3)) {
    \$optionsSection.show();
    updateRemoveButtons();
}

// Show/hide options section based on question type
\$questionType.on('change', function() {
    var selectedType = parseInt(\$(this).val());
    if (selectedType === 2 || selectedType === 3) { // Radio or Checkbox
        \$optionsSection.show();

        // If switching from text to radio/checkbox and no options exist, add one
        if (\$optionsList.find('.option-row').length === 0) {
            addNewOption();
        }
        updateRemoveButtons();
    } else {
        \$optionsSection.hide();
        // Clear options when switching to text type
        \$optionsList.empty();
    }
});

// Add new option function
function addNewOption() {
    var newOptionRow = \$(
        '<div class=\"option-row\">' +
            '<input type=\"text\" name=\"options[]\" class=\"form-control option-input\" placeholder=\"Enter answer option\">' +
            '<button type=\"button\" class=\"btn btn-danger remove-option\">Remove</button>' +
        '</div>'
    );

    \$optionsList.append(newOptionRow);
    return newOptionRow;
}

// Add new option button click
\$addOptionBtn.on('click', function() {
    addNewOption();
    updateRemoveButtons();
});

// Remove option
\$optionsList.on('click', '.remove-option', function() {
    \$(this).closest('.option-row').remove();
    updateRemoveButtons();
});

// Update remove buttons visibility
function updateRemoveButtons() {
    var \$optionRows = \$optionsList.find('.option-row');
    var \$removeButtons = \$optionsList.find('.remove-option');

    if (\$optionRows.length > 1) {
        \$removeButtons.show();
    } else {
        \$removeButtons.hide();
    }
}

// Form validation
$('#question-form').on('submit', function(e) {
    var selectedType = parseInt(\$questionType.val());

    if (selectedType === 2 || selectedType === 3) { // Radio or Checkbox
        var \$options = \$optionsList.find('.option-input');
        var hasValidOption = false;

        \$options.each(function() {
            if (\$.trim(\$(this).val()) !== '') {
                hasValidOption = true;
                return false; // break
            }
        });

        if (!hasValidOption) {
            e.preventDefault();
            alert('For Radio and Checkbox types, you must add at least one answer option');
            return false;
        }
    }
});

// Handle dynamic option changes when type changes
\$questionType.on('change', function() {
    var selectedType = parseInt(\$(this).val());
    var previousType = parseInt(\$(this).data('previous-type') || 0);

    // Clear options when switching from radio/checkbox to text
    if ((previousType === 2 || previousType === 3) && selectedType === 1) {
        \$optionsList.empty();
    }

    // Add default option when switching from text to radio/checkbox
    if (previousType === 1 && (selectedType === 2 || selectedType === 3)) {
        if (\$optionsList.find('.option-row').length === 0) {
            addNewOption();
            updateRemoveButtons();
        }
    }

    \$(this).data('previous-type', selectedType);
});

// Set initial previous type
\$questionType.data('previous-type', currentType);
";

$this->registerJs($js, \yii\web\View::POS_READY);
?>

    <div class="question-form-page">

        <?php $form = ActiveForm::begin(['id' => 'question-form']); ?>

        <div class="form-group">
            <?= $form->field($model, 'question_name')->textInput(['maxlength' => true]) ?>
        </div>

        <div class="form-group">
            <?= $form->field($model, 'type')->dropDownList(TestItem::getTypesList(), [
                'prompt' => 'Select question type',
                'id' => 'question-type'
            ]) ?>
        </div>

        <div class="options-section" id="options-section" style="display: none;">
            <h3>Answer Options</h3>
            <div id="options-list">
                <?php if ($isUpdate && in_array($model->type, [TestItem::TYPE_RADIO, TestItem::TYPE_CHECKBOX])): ?>
                    <?php foreach ($model->options as $option): ?>
                        <div class="option-row">
                            <input type="text" name="options[]" class="form-control option-input"
                                   value="<?= Html::encode($option->option_text) ?>" placeholder="Enter answer option">
                            <button type="button" class="btn btn-danger remove-option">Remove</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="option-row">
                        <input type="text" name="options[]" class="form-control option-input" placeholder="Enter answer option">
                        <button type="button" class="btn btn-danger remove-option" style="display: none;">Remove</button>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" id="add-option" class="btn btn-secondary">Add Option</button>
        </div>

        <div class="form-group">
            <?= Html::submitButton($isUpdate ? 'Update Question' : 'Create Question', ['class' => 'btn btn-success']) ?>
            <?= Html::a('Cancel', ['test'], ['class' => 'btn btn-default']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>

<?php
$css = "
.question-form-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.options-section {
    border: 1px solid #ddd;
    padding: 20px;
    border-radius: 5px;
    background: #f8f9fa;
    margin: 20px 0;
}

.option-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.option-input {
    flex: 1;
}

.btn {
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    display: inline-block;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-default {
    background: #f8f9fa;
    color: #212529;
    border: 1px solid #ddd;
}

.btn-primary {
    background: #007bff;
    color: white;
}

label {
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
}

select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.field-testitem-question_name label,
.field-testitem-type label {
    font-weight: bold;
    margin-bottom: 5px;
}

.help-block {
    color: #dc3545;
    font-size: 12px;
    margin-top: 5px;
}
";

$this->registerCss($css);
?>
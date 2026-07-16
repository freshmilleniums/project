<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use backend\models\TestItem;

/* @var $this yii\web\View */
/* @var $testItems backend\models\TestItem[] */
/* @var $existingAnswers common\models\TestUserAnswer[] */

$this->title = 'Test';
$this->params['breadcrumbs'][] = $this->title;
?>

    <div class="personal-test">
        <div class="row">
            <div class="col-lg-8 col-md-10 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?= Html::encode($this->title) ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($testItems)): ?>
                            <div class="alert alert-info">
                                <h4>No Test Questions Available</h4>
                                <p>There are currently no test questions configured. Please check back later.</p>
                            </div>
                        <?php else: ?>
                            <?php $form = ActiveForm::begin([
                                'id' => 'test-form',
                                'options' => ['class' => 'test-form'],
                            ]); ?>

                            <?php foreach ($testItems as $index => $testItem): ?>
                                <div class="question-block mb-4" data-question-id="<?= $testItem->id ?>">
                                    <div class="question-header mb-3">
                                        <h5 class="question-title">
                                            <span class="question-number"><?= $index + 1 ?>.</span>
                                            <?= Html::encode($testItem->question_name) ?>
                                            <span class="required-mark" style="color: #d9534f; display: none;">*</span>
                                        </h5>
                                        <div class="required-message alert alert-danger" style="display: none; margin-top: 10px; padding: 8px 12px;">
                                            This question is required to complete the test
                                        </div>
                                    </div>

                                    <div class="question-content">
                                        <?php
                                        $existingAnswer = isset($existingAnswers[$testItem->id])
                                            ? $existingAnswers[$testItem->id]->getAnswerData()
                                            : null;
                                        ?>

                                        <?php if ($testItem->type == TestItem::TYPE_TEXT): ?>
                                            <!-- Text Input -->
                                            <?php
                                            $textValue = '';
                                            if ($existingAnswer && isset($existingAnswer['text'])) {
                                                $textValue = $existingAnswer['text'];
                                            }
                                            ?>
                                            <div class="form-group">
                                                <?= Html::textarea(
                                                    "answers[{$testItem->id}]",
                                                    $textValue,
                                                    [
                                                        'class' => 'form-control question-input',
                                                        'rows' => 4,
                                                        'placeholder' => 'Enter your answer here...',
                                                        'id' => "answer_{$testItem->id}",
                                                        'data-question-id' => $testItem->id,
                                                        'data-question-type' => 'text'
                                                    ]
                                                ) ?>
                                            </div>

                                        <?php elseif ($testItem->type == TestItem::TYPE_RADIO): ?>
                                            <!-- Radio Options -->
                                            <?php
                                            $selectedRadio = null;
                                            if ($existingAnswer && isset($existingAnswer['selected']) && $existingAnswer['selected']) {
                                                // Check if the selected option still exists
                                                $selectedOptionExists = false;
                                                foreach ($testItem->options as $option) {
                                                    if ($option->id == $existingAnswer['selected']['id']) {
                                                        $selectedRadio = $option->id;
                                                        $selectedOptionExists = true;
                                                        break;
                                                    }
                                                }
                                                // If selected option doesn't exist anymore, don't select anything
                                                if (!$selectedOptionExists) {
                                                    $selectedRadio = null;
                                                }
                                            }
                                            ?>

                                            <?php if (empty($testItem->options)): ?>
                                                <div class="alert alert-warning">
                                                    <small>No options available for this question.</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="radio-options" data-question-id="<?= $testItem->id ?>" data-question-type="radio">
                                                    <?php foreach ($testItem->options as $option): ?>
                                                        <div class="form-check mb-2">
                                                            <?= Html::radio(
                                                                "answers[{$testItem->id}]",
                                                                $selectedRadio == $option->id,
                                                                [
                                                                    'value' => $option->id,
                                                                    'class' => 'form-check-input question-input',
                                                                    'id' => "radio_{$testItem->id}_{$option->id}",
                                                                    'data-question-id' => $testItem->id
                                                                ]
                                                            ) ?>
                                                            <?= Html::label(
                                                                Html::encode($option->option_text),
                                                                "radio_{$testItem->id}_{$option->id}",
                                                                ['class' => 'form-check-label']
                                                            ) ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                        <?php elseif ($testItem->type == TestItem::TYPE_CHECKBOX): ?>
                                            <!-- Checkbox Options -->
                                            <?php
                                            $selectedCheckboxes = [];
                                            if ($existingAnswer && isset($existingAnswer['selected']) && is_array($existingAnswer['selected'])) {
                                                // Check which selected options still exist
                                                foreach ($existingAnswer['selected'] as $selectedItem) {
                                                    foreach ($testItem->options as $option) {
                                                        if ($option->id == $selectedItem['id']) {
                                                            $selectedCheckboxes[] = $option->id;
                                                            break;
                                                        }
                                                    }
                                                }
                                            }
                                            ?>

                                            <?php if (empty($testItem->options)): ?>
                                                <div class="alert alert-warning">
                                                    <small>No options available for this question.</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="checkbox-options" data-question-id="<?= $testItem->id ?>" data-question-type="checkbox">
                                                    <?php foreach ($testItem->options as $option): ?>
                                                        <div class="form-check mb-2">
                                                            <?= Html::checkbox(
                                                                "answers[{$testItem->id}][]",
                                                                in_array($option->id, $selectedCheckboxes),
                                                                [
                                                                    'value' => $option->id,
                                                                    'class' => 'form-check-input question-input',
                                                                    'id' => "checkbox_{$testItem->id}_{$option->id}",
                                                                    'data-question-id' => $testItem->id
                                                                ]
                                                            ) ?>
                                                            <?= Html::label(
                                                                Html::encode($option->option_text),
                                                                "checkbox_{$testItem->id}_{$option->id}",
                                                                ['class' => 'form-check-label']
                                                            ) ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($index < count($testItems) - 1): ?>
                                    <hr class="question-separator">
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <div class="form-actions mt-4 pt-3 border-top">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="btn-group">
                                            <?= Html::submitButton('Save Answers', [
                                                'class' => 'btn btn-primary btn-lg',
                                                'id' => 'save-answers-btn'
                                            ]) ?>
                                            <?= Html::submitButton('Complete Test', [
                                                'class' => 'btn btn-success btn-lg ml-3',
                                                'id' => 'complete-test-btn',
                                                'name' => 'complete_test',
                                                'value' => '1'
                                            ]) ?>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <strong>Save Answers:</strong> Your answers will be saved and can be modified later.<br>
                                                <strong>Complete Test:</strong> Your answers will be submitted and cannot be changed.
                                            </small>
                                        </div>
                                        <!-- Validation Summary -->
                                        <div id="validation-summary" class="alert alert-danger mt-3" style="display: none;">
                                            <h6>Please fill in all required fields:</h6>
                                            <ul id="validation-list"></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php ActiveForm::end(); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .personal-test {
            padding: 20px 0;
        }

        .question-block {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }

        .question-block.has-error {
            border-left-color: #d9534f;
            background: #fdf2f2;
        }

        .question-block.has-error .form-control,
        .question-block.has-error .form-check-input {
            border-color: #d9534f;
        }

        .question-title {
            color: #333;
            margin-bottom: 0;
        }

        .question-number {
            color: #007bff;
            font-weight: bold;
            margin-right: 8px;
        }

        .question-content {
            margin-top: 15px;
        }

        .form-check {
            padding-left: 1.5rem;
        }

        .form-check-input {
            margin-top: 0.25rem;
        }

        .form-check-label {
            margin-left: 0.5rem;
            cursor: pointer;
        }

        .question-separator {
            margin: 30px 0;
            border-color: #dee2e6;
        }

        .form-actions {
            background: #f8f9fa;
            margin: -20px -20px 0 -20px;
            padding: 20px;
            border-radius: 0 0 8px 8px;
        }

        #save-answers-btn, #complete-test-btn {
            min-width: 150px;
        }

        .card {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: none;
        }

        .card-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border-bottom: none;
        }

        .alert {
            border-radius: 6px;
        }

        .required-mark {
            font-size: 1.2em;
            margin-left: 5px;
        }

        .required-message {
            font-size: 0.875em;
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .personal-test {
                padding: 10px 0;
            }

            .question-block {
                padding: 15px;
                margin-bottom: 20px;
            }

            .question-title {
                font-size: 1.1rem;
            }

            .btn-group {
                display: flex;
                flex-direction: column;
                width: 100%;
            }

            .btn-group .btn {
                margin: 0 0 10px 0 !important;
            }
        }
    </style>

<?php
$js = "
var clickedButton = null;
var isCompleting = false;
var validationActive = false;

function isQuestionAnswered(questionId) {
    var questionBlock = $('[data-question-id=\"' + questionId + '\"]');
    var questionType = questionBlock.find('[data-question-type]').data('question-type');
    
    switch(questionType) {
        case 'text':
            var textValue = $('#answer_' + questionId).val();
            return textValue && textValue.trim().length > 0;
            
        case 'radio':
            return $('input[name=\"answers[' + questionId + ']\"]:checked').length > 0;
            
        case 'checkbox':
            return $('input[name=\"answers[' + questionId + '][]\"]:checked').length > 0;
            
        default:
            return false;
    }
}

function updateQuestionValidation(questionId) {
    var questionBlock = $('.question-block[data-question-id=\"' + questionId + '\"]');
    var isAnswered = isQuestionAnswered(questionId);    
   
    if (validationActive && !isAnswered) {
        questionBlock.addClass('has-error');
        questionBlock.find('.required-mark').show();
        questionBlock.find('.required-message').show();
    } else {
       
        questionBlock.removeClass('has-error');
        questionBlock.find('.required-mark').hide();
        questionBlock.find('.required-message').hide();
    }
}

function validateAllQuestions() {
    var errors = [];
    
    $('.question-block').each(function(index) {
        var questionId = $(this).data('question-id');
        var questionTitle = $(this).find('.question-title').clone();        
       
        questionTitle.find('.question-number, .required-mark').remove();
        var cleanTitle = questionTitle.text().trim();
        
        updateQuestionValidation(questionId);
        
        if (!isQuestionAnswered(questionId)) {
            errors.push('Question ' + (index + 1) + ': ' + cleanTitle);
        }
    });
    
    return errors;
}

// Show/hide validation summary
function updateValidationSummary(errors, shouldScroll) {
    var summary = $('#validation-summary');
    var list = $('#validation-list');
    
    if (errors.length > 0 && validationActive) {
        list.empty();
        errors.forEach(function(error) {
            list.append('<li>' + error + '</li>');
        });
        summary.show();        
      
        if (shouldScroll === true) {
            var firstError = $('.question-block.has-error').first();
            if (firstError.length > 0) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
            }
        }
    } else {
        summary.hide();
    }
}

// Track which button was clicked
$('#save-answers-btn, #complete-test-btn').on('click', function(e) {
    clickedButton = $(this);
    isCompleting = $(this).attr('id') === 'complete-test-btn'; 
});

// Form submission handler
$('#test-form').on('submit', function(e) {
    var submitBtn;
    
    // Determine which button was clicked
    if (e.originalEvent && e.originalEvent.submitter) {
        submitBtn = $(e.originalEvent.submitter);
    } else {
        submitBtn = clickedButton;
    }
    
    isCompleting = submitBtn && submitBtn.attr('id') === 'complete-test-btn';
    
    // If completing test, validate all questions
    if (isCompleting) {
        validationActive = true; 
        var errors = validateAllQuestions();
        
        if (errors.length > 0) {
            updateValidationSummary(errors, true); 
            e.preventDefault();
            return false;
        } else {
            // Show confirmation dialog
            if (!confirm('Are you sure you want to complete the test? After this, you will not be able to change your answers.')) {               
                validationActive = false;
                // Remove all errors
                $('.question-block').each(function() {
                    var questionId = $(this).data('question-id');
                    updateQuestionValidation(questionId);
                });
                $('#validation-summary').hide();
                e.preventDefault();
                return false;
            }
        }
    } else {      
        validationActive = false;
        isCompleting = false;
        $('[data-question-id]').each(function() {
            updateQuestionValidation($(this).data('question-id'));
        });
        $('#validation-summary').hide();
    }
    
    // Disable buttons and show loading state
    var saveBtn = $('#save-answers-btn');
    var completeBtn = $('#complete-test-btn');
    
    saveBtn.prop('disabled', true);
    completeBtn.prop('disabled', true);
    
    if (isCompleting) {
        completeBtn.html('<span class=\"spinner-border spinner-border-sm\" role=\"status\" aria-hidden=\"true\"></span> Completing...');
    } else {
        saveBtn.html('<span class=\"spinner-border spinner-border-sm\" role=\"status\" aria-hidden=\"true\"></span> Saving...');
    }
});

// Real-time validation on input change
$(document).on('input change', '.question-input', function() {          
        var questionId = $(this).data('question-id');
        var questionBlock = $('[data-question-id=\"' + questionId + '\"]');
        questionBlock.removeClass('has-error');
        questionBlock.find('.required-mark').hide();
        questionBlock.find('.required-message').hide();   
        $('#validation-summary').hide();
});

// Initialize validation state on page load
$(document).ready(function() {
    isCompleting = false;
    validationActive = false; 
});
";

$this->registerJs($js, \yii\web\View::POS_READY);

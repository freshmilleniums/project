<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $userAnswers backend\models\TestUserAnswer[] */
/* @var $user common\models\User */

$this->title = 'Test Results';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="test-results test-results-page">
    <div class="row">
        <div class="col-lg-10 col-md-12 mx-auto">
            <div class="card test-results-card">
                <div class="card-header test-results-header">
                    <h3 class="card-title"><?= Html::encode($this->title) ?></h3>
                </div>
                <div class="card-body test-results-body">
                    <?php if (empty($userAnswers)): ?>
                        <div class="alert alert-secondary test-results-alert">
                            <h4>No Test Results Available</h4>
                            <p>You haven't submitted any test answers yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="answers-list">
                            <?php foreach ($userAnswers as $index => $userAnswer): ?>
                                <div class="answer-block mb-4">
                                    <div class="answer-header mb-3">
                                        <h5 class="question-title">
                                            <span class="question-number"><?= $index + 1 ?>.</span>
                                            <?= Html::encode($userAnswer->question_name) ?>
                                        </h5>
                                    </div>

                                    <div class="answer-content">
                                        <div class="answer-text">
                                            <?php
                                            $answerText = $userAnswer->getAnswerText();
                                            if (!empty($answerText)):
                                                ?>
                                                <div class="user-answer">
                                                    <?= Html::encode($answerText) ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="no-answer text-muted">
                                                    <em>No answer provided</em>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($index < count($userAnswers) - 1): ?>
                                    <hr class="answer-separator">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
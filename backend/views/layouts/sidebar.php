<?php
use backend\models\User;
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <button class="sidebar-close-btn" aria-label="Close sidebar">
        <span>&times;</span>
    </button>

    <a href="<?= \yii\helpers\Url::to(['/site/index']) ?>" class="brand-link">
        <img src="<?=$assetDir?>/img/AdminLTELogo.png" alt="Employers CRM Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light">Employers CRM</span>
    </a>

    <div class="sidebar">
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="info">
                <a href="#" class="d-block">
                    <?= Yii::$app->user->identity->first_name ?? '' ?>
                    <?= Yii::$app->user->identity->last_name ?? '' ?>
                </a>
            </div>
        </div>

        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="<?= \yii\helpers\Url::to(['/users']) ?>" class="nav-link">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Users</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= \yii\helpers\Url::to(['/companies']) ?>" class="nav-link">
                        <i class="nav-icon fas fa-building"></i>
                        <p>Companies</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= \yii\helpers\Url::to(['/logs-admin']) ?>" class="nav-link">
                        <i class="nav-icon fas fa-clipboard-list"></i>
                        <p>Admin Logs</p>
                    </a>
                </li>

            </ul>

            <ul class="nav nav-pills nav-sidebar flex-column sidebar-menu-mobile" data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-item">
                    <a href="<?= \yii\helpers\Url::to(['/users']) ?>" class="nav-link">
                        <p>Users</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= \yii\helpers\Url::to(['/companies']) ?>" class="nav-link">
                        <p>Companies</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= \yii\helpers\Url::to(['/logs-admin']) ?>" class="nav-link">
                        <p>Admin Logs</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>
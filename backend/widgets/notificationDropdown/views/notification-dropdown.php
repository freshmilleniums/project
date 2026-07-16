<?php

use yii\helpers\Url;

/** @var int $notificationCount */
/** @var int $reminderCount */

$totalCount = $notificationCount + $reminderCount;
?>

<a class="nav-link" data-toggle="dropdown" href="#">
    <i class="far fa-bell"></i>
    <span class="badge badge-warning navbar-badge"><?= $totalCount ?></span>
</a>
<div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
    <span class="dropdown-header"><?= $totalCount ?> Notifications/Reminders</span>
    <div class="dropdown-divider"></div>

    <a href="<?= Url::to(['/notification']) ?>" class="dropdown-item">
        <i class="fas fa-info-circle mr-2"></i>
        <?= $notificationCount ?> new Notifications
        <span class="float-right text-muted text-sm"></span>
    </a>

    <div class="dropdown-divider"></div>

    <a href="#" class="dropdown-item">
        <i class="fas fa-bell mr-2"></i>
        <?= $reminderCount ?> new Reminders
        <span class="float-right text-muted text-sm"></span>
    </a>
</div>

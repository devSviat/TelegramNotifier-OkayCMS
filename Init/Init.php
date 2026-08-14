<?php

namespace Okay\Modules\Sviat\TelegramNotifier\Init;

use Okay\Core\Modules\AbstractInit;
use Okay\Core\Scheduler\Schedule;
use Okay\Entities\CallbacksEntity;
use Okay\Entities\FeedbacksEntity;
use Okay\Entities\OrdersEntity;
use Okay\Helpers\CommentsHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Modules\Sviat\TelegramNotifier\Extenders\FrontExtender;
use Okay\Modules\Sviat\TelegramNotifier\Helpers\TelegramCronHelper;

class Init extends AbstractInit
{
    public function install()
    {
        $this->setBackendMainController('TelegramAdmin');
    }

    public function init()
    {
        $this->registerBackendController('TelegramAdmin');
        $this->addBackendControllerPermission('TelegramAdmin', 'settings');

        $this->registerQueueExtension(
            [OrdersHelper::class, 'finalCreateOrderProcedure'],
            [FrontExtender::class, 'finalCreateOrderProcedure']
        );

        $this->registerQueueExtension(
            [CommentsHelper::class, 'addCommentProcedure'],
            [FrontExtender::class, 'addCommentProcedure']
        );

        $this->registerQueueExtension(
            [FeedbacksEntity::class, 'add'],
            [FrontExtender::class, 'addFeedbackProcedure']
        );

        $this->registerQueueExtension(
            [CallbacksEntity::class, 'add'],
            [FrontExtender::class, 'addCallbackProcedure']
        );

        $this->registerQueueExtension(
            [OrdersEntity::class, 'afterMarkedPaidUpdate'],
            [FrontExtender::class, 'afterMarkedPaidUpdate']
        );

        // Реєстрація extender для модуля PartsSelection (якщо встановлений)
        try {
            $partsSelectionEntityClass = \Okay\Modules\Sviat\PartsSelection\Entities\PartsSelectionEntity::class;
            if (class_exists($partsSelectionEntityClass)) {
                $this->registerQueueExtension(
                    [$partsSelectionEntityClass, 'add'],
                    [FrontExtender::class, 'addPartsSelectionProcedure']
                );
            }
        } catch (\Throwable $e) {
            // Якщо модуль PartsSelection не встановлений, просто ігноруємо помилку
        }

        $this->registerSchedule(
            (new Schedule([TelegramCronHelper::class, 'sendMonthlyOrderStats']))
                ->name('Telegram: monthly order stats')
                ->time('0 9 1 * *')
                ->overlap(false)
                ->timeout(300)
        );
    }
}

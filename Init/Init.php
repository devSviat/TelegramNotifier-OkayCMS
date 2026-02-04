<?php

namespace Okay\Modules\Sviat\TelegramNotifier\Init;

use Okay\Core\Modules\AbstractInit;
use Okay\Entities\CallbacksEntity;
use Okay\Entities\FeedbacksEntity;
use Okay\Helpers\CommentsHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Modules\Sviat\TelegramNotifier\Extenders\FrontExtender;

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
    }
}

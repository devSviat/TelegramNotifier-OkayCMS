<?php

namespace Okay\Modules\Sviat\TelegramNotifier\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\EntityFactory;
use Okay\Entities\ModulesEntity;
use Okay\Modules\Sviat\TelegramNotifier\Helpers\ExampleMessageHelper;

/**
 * Контролер адмін-панелі для налаштування Telegram-сповіщень
 */
class TelegramAdmin extends IndexAdmin
{
    /**
     * Обробляє запити налаштувань модуля та відображає сторінку конфігурації
     *
     * @param ExampleMessageHelper $exampleMessageHelper Хелпер для генерації прикладів повідомлень
     * @param EntityFactory $entityFactory Фабрика сутностей
     * @return void
     */
    public function fetch(ExampleMessageHelper $exampleMessageHelper, EntityFactory $entityFactory)
    {
        if ($this->request->method('post')) {
            $this->settings->set('sviat__telegram_notifier__order_notify_enabled', $this->request->post('order_notify_enabled', 'boolean') ? 1 : 0);
            $this->settings->set('sviat__telegram_notifier__comment_notify_enabled', $this->request->post('comment_notify_enabled', 'boolean') ? 1 : 0);
            $this->settings->set('sviat__telegram_notifier__feedback_notify_enabled', $this->request->post('feedback_notify_enabled', 'boolean') ? 1 : 0);
            $this->settings->set('sviat__telegram_notifier__callback_notify_enabled', $this->request->post('callback_notify_enabled', 'boolean') ? 1 : 0);
            $this->settings->set('sviat__telegram_notifier__paid_order_notify_enabled', $this->request->post('paid_order_notify_enabled', 'boolean') ? 1 : 0);
            $paidOrderMessageType = $this->request->post('paid_order_message_type');
            $this->settings->set('sviat__telegram_notifier__paid_order_message_type', in_array($paidOrderMessageType, ['short', 'full'], true) ? $paidOrderMessageType : 'full');
            $this->settings->set('sviat__telegram_notifier__order_stats_enabled', $this->request->post('order_stats_enabled', 'boolean') ? 1 : 0);
            $this->settings->set('sviat__telegram_notifier__parts_selection_notify_enabled', $this->request->post('parts_selection_notify_enabled', 'boolean') ? 1 : 0);
            $this->settings->set('sviat__telegram_notifier__bot_token', $this->request->post('bot_token'));
            $this->settings->set('sviat__telegram_notifier__chat_id', $this->request->post('chat_id'));
            $this->settings->set('sviat__telegram_notifier__product_format', $this->request->post('product_format'));

            $this->postRedirectGet->storeMessageSuccess('updated');
            $this->postRedirectGet->redirect();
        }

        $this->design->assign('order_notify_enabled', $this->settings->get('sviat__telegram_notifier__order_notify_enabled'));
        $this->design->assign('comment_notify_enabled', $this->settings->get('sviat__telegram_notifier__comment_notify_enabled'));
        $this->design->assign('feedback_notify_enabled', $this->settings->get('sviat__telegram_notifier__feedback_notify_enabled'));
        $this->design->assign('callback_notify_enabled', $this->settings->get('sviat__telegram_notifier__callback_notify_enabled'));
        $this->design->assign('paid_order_notify_enabled', $this->settings->get('sviat__telegram_notifier__paid_order_notify_enabled'));
        $paidOrderMessageType = $this->settings->get('sviat__telegram_notifier__paid_order_message_type') ?: 'full';
        $this->design->assign('paid_order_message_type', $paidOrderMessageType);
        $this->design->assign('order_stats_enabled', $this->settings->get('sviat__telegram_notifier__order_stats_enabled'));
        $this->design->assign('bot_token', $this->settings->get('sviat__telegram_notifier__bot_token'));
        $this->design->assign('chat_id', $this->settings->get('sviat__telegram_notifier__chat_id'));
        $this->design->assign('product_format', $this->settings->get('sviat__telegram_notifier__product_format'));
        $this->design->assign('example_order_message', $exampleMessageHelper->getExampleOrderMessageHtml());
        $this->design->assign('example_comment_message', $exampleMessageHelper->getExampleCommentMessageHtml());
        $this->design->assign('example_feedback_message', $exampleMessageHelper->getExampleFeedbackMessageHtml());
        $this->design->assign('example_callback_message', $exampleMessageHelper->getExampleCallbackMessageHtml());
        $this->design->assign('example_paid_order_message_short', $exampleMessageHelper->getExamplePaidOrderMessageHtml('short'));
        $this->design->assign('example_paid_order_message_full', $exampleMessageHelper->getExamplePaidOrderMessageHtml('full'));
        $this->design->assign('example_order_stats_message', $exampleMessageHelper->getExampleOrderStatsMessageHtml());

        // Перевірка наявності модуля PartsSelection
        /** @var ModulesEntity $modulesEntity */
        $modulesEntity = $entityFactory->get(ModulesEntity::class);
        $partsSelectionModule = $modulesEntity->getByVendorModuleName('Sviat', 'PartsSelection');
        $isPartsSelectionInstalled = !empty($partsSelectionModule) && $partsSelectionModule->enabled;
        $this->design->assign('is_parts_selection_installed', $isPartsSelectionInstalled);
        
        if ($isPartsSelectionInstalled) {
            $this->design->assign('parts_selection_notify_enabled', $this->settings->get('sviat__telegram_notifier__parts_selection_notify_enabled'));
            $this->design->assign('example_parts_selection_message', $exampleMessageHelper->getExamplePartsSelectionMessageHtml());
        }

        $this->response->setContent($this->design->fetch('telegram_admin.tpl'));
    }
}

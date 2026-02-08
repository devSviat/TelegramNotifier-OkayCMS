<?php

namespace Okay\Modules\Sviat\TelegramNotifier\Extenders;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Entities\CallbacksEntity;
use Okay\Entities\CommentsEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\FeedbacksEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Helpers\CommentsHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Modules\Sviat\TelegramNotifier\Helpers\TelegramHelper;

/**
 * Розширення для відправки повідомлень про нові замовлення в Telegram
 */
class FrontExtender implements ExtensionInterface
{
    private $telegramHelper;
    private $entityFactory;
    private $ordersHelper;
    private $commentsHelper;

    public function __construct(
        TelegramHelper $telegramHelper,
        EntityFactory $entityFactory,
        OrdersHelper $ordersHelper,
        CommentsHelper $commentsHelper
    ) {
        $this->telegramHelper = $telegramHelper;
        $this->entityFactory = $entityFactory;
        $this->ordersHelper = $ordersHelper;
        $this->commentsHelper = $commentsHelper;
    }

    /**
     * Обробляє замовлення після його створення: завантажує дані та відправляє повідомлення в Telegram
     *
     * @param mixed $result Результат операції створення замовлення
     * @param object $order Об'єкт замовлення
     */
    public function finalCreateOrderProcedure($result, $order)
    {
        $order->purchases = $this->ordersHelper->getOrderPurchasesList($order->id);

        if (!empty($order->payment_method_id)) {
            /** @var PaymentsEntity $paymentsEntity */
            $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
            $order->payment_method_name = $paymentsEntity->findOne(['id' => $order->payment_method_id]);
        }

        if (!empty($order->delivery_id)) {
            /** @var DeliveriesEntity $deliveriesEntity */
            $deliveriesEntity = $this->entityFactory->get(DeliveriesEntity::class);
            $delivery = $deliveriesEntity->get((int) $order->delivery_id);
            if ($delivery && !empty($delivery->name)) {
                $order->delivery_name = $delivery->name;
            }
        }

        try {
            $this->telegramHelper->sendNewOrderNotification($order);
        } catch (\Throwable $e) {
            error_log('TelegramNotifier: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * Обробляє коментар після його додавання: завантажує дані та відправляє повідомлення в Telegram
     *
     * @param int $commentId ID коментаря
     */
    public function addCommentProcedure($commentId)
    {
        /** @var CommentsEntity $commentsEntity */
        $commentsEntity = $this->entityFactory->get(CommentsEntity::class);
        if ($comment = $commentsEntity->findOne(['id' => $commentId])) {
            // Завантажуємо пов'язані об'єкти (товар або пост)
            $comments = $this->commentsHelper->attachTargetEntitiesToComments([$comment]);
            $comment = reset($comments);

            try {
                $this->telegramHelper->sendNewCommentNotification($comment);
            } catch (\Throwable $e) {
                error_log('TelegramNotifier: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }
    }

    /**
     * Обробляє зворотний зв'язок після його додавання: завантажує дані та відправляє повідомлення в Telegram
     *
     * @param int $feedbackId ID зворотного зв'язку
     * @param object $feedback Об'єкт зворотного зв'язку
     * @return int ID зворотного зв'язку
     */
    public function addFeedbackProcedure($feedbackId, $feedback)
    {
        // Перевіряємо, чи це не відповідь адміністратора (is_admin = 1)
        if (!empty($feedback->is_admin) && $feedback->is_admin == 1) {
            return $feedbackId;
        }

        /** @var FeedbacksEntity $feedbacksEntity */
        $feedbacksEntity = $this->entityFactory->get(FeedbacksEntity::class);
        if ($feedback = $feedbacksEntity->findOne(['id' => $feedbackId])) {
            try {
                $this->telegramHelper->sendNewFeedbackNotification($feedback);
            } catch (\Throwable $e) {
                error_log('TelegramNotifier: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }

        return $feedbackId;
    }

    /**
     * Обробляє заявку на дзвінок після її додавання: завантажує дані та відправляє повідомлення в Telegram
     *
     * @param int $callbackId ID заявки на дзвінок
     * @param object $callback Об'єкт заявки на дзвінок
     * @return int ID заявки на дзвінок
     */
    public function addCallbackProcedure($callbackId, $callback)
    {
        /** @var CallbacksEntity $callbacksEntity */
        $callbacksEntity = $this->entityFactory->get(CallbacksEntity::class);
        if ($callback = $callbacksEntity->findOne(['id' => $callbackId])) {
            try {
                $this->telegramHelper->sendNewCallbackNotification($callback);
            } catch (\Throwable $e) {
                error_log('TelegramNotifier: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }

        return $callbackId;
    }

    /**
     * Після зміни статусу оплати замовлень (OrdersEntity::afterMarkedPaidUpdate): відправляє повідомлення в Telegram
     * лише коли замовлення саме переведено в «сплачено» ($state === true). Викликається тільки для тих замовлень,
     * у яких реально змінився paid (без дублікатів при повторній позначці).
     *
     * @param mixed $result Результат (null)
     * @param array $ids ID замовлень, у яких змінився статус оплати
     * @param bool $state true = переведено в «сплачено», false = знято позначку «сплачено»
     */
    public function afterMarkedPaidUpdate($result, array $ids, $state)
    {
        if (empty($ids) || !$state) {
            return $result;
        }

        /** @var OrdersEntity $ordersEntity */
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);

        foreach ($ids as $orderId) {
            $orderObj = $ordersEntity->get((int) $orderId);
            if (empty($orderObj)) {
                continue;
            }

            $orderObj->purchases = $this->ordersHelper->getOrderPurchasesList($orderObj->id);

            if (!empty($orderObj->payment_method_id)) {
                /** @var PaymentsEntity $paymentsEntity */
                $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
                $orderObj->payment_method_name = $paymentsEntity->findOne(['id' => $orderObj->payment_method_id]);
            }

            if (!empty($orderObj->delivery_id)) {
                /** @var DeliveriesEntity $deliveriesEntity */
                $deliveriesEntity = $this->entityFactory->get(DeliveriesEntity::class);
                $delivery = $deliveriesEntity->get((int) $orderObj->delivery_id);
                if ($delivery && !empty($delivery->name)) {
                    $orderObj->delivery_name = $delivery->name;
                }
            }

            try {
                $this->telegramHelper->sendPaidOrderNotification($orderObj);
            } catch (\Throwable $e) {
                error_log('TelegramNotifier: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }

        return $result;
    }
}

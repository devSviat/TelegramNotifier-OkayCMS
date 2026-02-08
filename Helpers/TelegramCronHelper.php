<?php

namespace Okay\Modules\Sviat\TelegramNotifier\Helpers;

use Okay\Core\EntityFactory;
use Okay\Core\Languages;
use Okay\Entities\OrderStatusEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PurchasesEntity;

/**
 * Крон-завдання модуля Telegram Notifier.
 * Містить методи для відправки різних типів повідомлень по розкладу (наприклад щомісячна статистика замовлень).
 */
class TelegramCronHelper
{
    private EntityFactory $entityFactory;
    private TelegramHelper $telegramHelper;
    private Languages $languages;

    public function __construct(
        EntityFactory $entityFactory,
        TelegramHelper $telegramHelper,
        Languages $languages
    ) {
        $this->entityFactory = $entityFactory;
        $this->telegramHelper = $telegramHelper;
        $this->languages = $languages;
    }

    /**
     * Збирає статистику за попередній місяць та відправляє повідомлення в Telegram.
     * Відправка лише якщо увімкнено «Статистика замовлень» в налаштуваннях модуля.
     * Запуск: 1-го числа кожного місяця о 9:00.
     */
    public function sendMonthlyOrderStats(): void
    {
        $this->telegramHelper->sendOrderStatsNotification(
            $this->getOrdersCount(),
            $this->getOrdersTotalSum(),
            $this->getOrdersByStatus(),
            $this->getTopProducts(3),
            $this->getPreviousMonthLabel()
        );
    }

    private function getPreviousMonthLabel(): string
    {
        $months = [
            1 => 'січень', 2 => 'лютий', 3 => 'березень', 4 => 'квітень',
            5 => 'травень', 6 => 'червень', 7 => 'липень', 8 => 'серпень',
            9 => 'вересень', 10 => 'жовтень', 11 => 'листопад', 12 => 'грудень',
        ];
        $prev = (int) date('n', strtotime('first day of previous month'));
        $year = (int) date('Y', strtotime('first day of previous month'));
        return $months[$prev] . ' ' . $year;
    }

    private function getOrdersCount(): int
    {
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        return $ordersEntity->count($this->getPreviousMonthFilter());
    }

    /**
     * Кількість замовлень за попередній місяць з розбивкою по статусах.
     * Повертає всі статуси з назвами та кількістю замовлень.
     *
     * @return array<int, array{name: string, count: int}>
     */
    private function getOrdersByStatus(): array
    {
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        // Потрібно ≥2 колонок, інакше Entity повертає масив скалярів, а не об'єктів — тоді $order->status_id не працює
        $orders = $ordersEntity->cols(['id', 'status_id'])->noLimit()->find($this->getPreviousMonthFilter());
        $byStatusId = [];
        foreach ($orders as $order) {
            $id = (int) ($order->status_id ?? 0);
            $byStatusId[$id] = ($byStatusId[$id] ?? 0) + 1;
        }
        if (empty($byStatusId)) {
            return [];
        }

        $statusIds = array_keys($byStatusId);
        $statusIdsWithoutZero = array_values(array_filter($statusIds, fn($id) => $id !== 0));

        $statusNames = [];
        if (!empty($statusIdsWithoutZero)) {
            // У кроні може не бути мови з сесії — встановлюємо основну, щоб отримати назви статусів
            $mainLang = $this->languages->getMainLanguage();
            if ($mainLang) {
                $this->languages->setLangId($mainLang->id);
            }
            $statusEntity = $this->entityFactory->get(OrderStatusEntity::class);
            $statuses = $statusEntity->mappedBy('id')->find(['id' => $statusIdsWithoutZero]);
            foreach ($statuses as $id => $status) {
                $statusNames[$id] = isset($status->name) && trim((string) $status->name) !== ''
                    ? trim((string) $status->name)
                    : 'ID ' . $id;
            }
        }
        $statusNames[0] = 'Без статусу';

        $result = [];
        foreach ($statusIds as $id) {
            $result[] = [
                'name' => $statusNames[$id] ?? 'ID ' . $id,
                'count' => $byStatusId[$id],
            ];
        }
        usort($result, fn($a, $b) => $b['count'] <=> $a['count']);
        return $result;
    }

    private function getOrdersTotalSum(): float
    {
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $orders = $ordersEntity->find($this->getPreviousMonthFilter());
        $sum = 0.0;
        foreach ($orders as $order) {
            $sum += (float) ($order->total_price ?? 0);
        }
        return $sum;
    }

    /**
     * Топ N товарів за кількістю проданих одиниць за попередній місяць.
     * Джерело даних — PurchasesEntity (покупки замовлень за період).
     * Якщо товарів немає — повертає [], блок «Топ товарів» у повідомленні не виводиться.
     *
     * @return array<int, array{name: string, amount: int}>
     */
    private function getTopProducts(int $limit): array
    {
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        // ≥2 колонок, щоб отримати об'єкти; noLimit — щоб врахувати всі замовлення місяця
        $orders = $ordersEntity->cols(['id', 'total_price'])->noLimit()->find($this->getPreviousMonthFilter());
        $orderIds = array_map(fn($o) => $o->id, $orders);

        if (empty($orderIds)) {
            return [];
        }

        $purchasesEntity = $this->entityFactory->get(PurchasesEntity::class);
        $purchases = $purchasesEntity->noLimit()->find(['order_id' => $orderIds]);

        $byProduct = [];
        foreach ($purchases as $p) {
            $key = (int) ($p->product_id ?? 0);
            $name = trim($p->product_name ?? '');
            if ($key === 0 && $name === '') {
                continue;
            }
            if (!isset($byProduct[$key])) {
                $byProduct[$key] = ['name' => $name ?: 'ID ' . $key, 'amount' => 0];
            }
            $byProduct[$key]['amount'] += (int) ($p->amount ?? 1);
        }

        if (empty($byProduct)) {
            return [];
        }

        uasort($byProduct, fn($a, $b) => $b['amount'] <=> $a['amount']);
        return array_slice(array_values($byProduct), 0, $limit);
    }

    private function getPreviousMonthFilter(): array
    {
        $from = date('Y-m-01', strtotime('first day of previous month'));
        $to = date('Y-m-t', strtotime('first day of previous month'));
        return [
            'from_date' => $from,
            'to_date' => $to,
        ];
    }
}

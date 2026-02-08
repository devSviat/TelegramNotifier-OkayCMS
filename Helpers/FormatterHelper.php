<?php

namespace Okay\Modules\Sviat\TelegramNotifier\Helpers;

use Okay\Core\EntityFactory;
use Okay\Core\Router;
use Okay\Core\Settings;
use Okay\Helpers\MainHelper;

/**
 * Форматування повідомлень про замовлення в HTML для Telegram
 */
class FormatterHelper
{
    private Settings $settings;
    private MainHelper $mainHelper;
    private EntityFactory $entityFactory;

    public function __construct(Settings $settings, MainHelper $mainHelper, EntityFactory $entityFactory)
    {
        $this->settings = $settings;
        $this->mainHelper = $mainHelper;
        $this->entityFactory = $entityFactory;
    }

    /**
     * Форматує повідомлення про замовлення в HTML для Telegram
     *
     * @param object $order Об'єкт замовлення
     * @return string Повідомлення у форматі HTML
     */
    public function formatOrderMessage($order): string
    {
        $currency = $this->mainHelper->getCurrentCurrency();
        $currencySign = ($currency && isset($currency->sign)) ? $currency->sign : '₴';

        $message = [
            "🟢 Замовлення №" . $this->escapeHtml((string)$order->id),
            "",
            "Сума: " . $this->formatTotalPrice($order->total_price ?? 0, $currencySign),
            "",
            "Клієнт: " . $this->escapeHtml($this->getClientName($order)),
        ];

        $phone = trim($order->phone ?? '');
        if ($phone) {
            $message[] = "Телефон: " . $this->escapeHtml($phone);
        }
        $email = trim($order->email ?? '');
        if ($email) {
            $message[] = "Пошта: " . $this->escapeHtml($email);
        }

        $message[] = "Спосіб оплати: " . $this->escapeHtml($this->getPaymentMethod($order));

        if ($deliveryName = trim($order->delivery_name ?? '')) {
            $message[] = "Доставка: " . $this->escapeHtml($deliveryName);
        }

        $message[] = "";

        if ($productsList = $this->formatProductsList($order->purchases ?? [], $currencySign, $this->getProductFormat())) {
            $message[] = "Товари:";
            $message = array_merge($message, $productsList);
        }

        return implode("\n", $message);
    }

    /**
     * Форматує коротке повідомлення про оплачене замовлення (номер + сума).
     *
     * @param object $order Об'єкт замовлення
     * @return string Повідомлення у форматі HTML
     */
    public function formatPaidOrderMessageShort($order): string
    {
        $currency = $this->mainHelper->getCurrentCurrency();
        $currencySign = ($currency && isset($currency->sign)) ? $currency->sign : '₴';

        $message = [
            "💰 Замовлення №" . $this->escapeHtml((string)$order->id) . " сплачено",
            "",
            "Сума: " . $this->formatTotalPrice($order->total_price ?? 0, $currencySign),
        ];

        return implode("\n", $message);
    }

    /**
     * Форматує повне повідомлення про оплачене замовлення в HTML для Telegram (з клієнтом, доставкою, товарами).
     *
     * @param object $order Об'єкт замовлення
     * @return string Повідомлення у форматі HTML
     */
    public function formatPaidOrderMessage($order): string
    {
        $currency = $this->mainHelper->getCurrentCurrency();
        $currencySign = ($currency && isset($currency->sign)) ? $currency->sign : '₴';

        $message = [
            "💰 Замовлення №" . $this->escapeHtml((string)$order->id) . " сплачено",
            "",
            "Сума: " . $this->formatTotalPrice($order->total_price ?? 0, $currencySign),
            "",
            "Клієнт: " . $this->escapeHtml($this->getClientName($order)),
        ];

        $phone = trim($order->phone ?? '');
        if ($phone) {
            $message[] = "Телефон: " . $this->escapeHtml($phone);
        }
        $email = trim($order->email ?? '');
        if ($email) {
            $message[] = "Пошта: " . $this->escapeHtml($email);
        }

        $message[] = "Спосіб оплати: " . $this->escapeHtml($this->getPaymentMethod($order));

        if ($deliveryName = trim($order->delivery_name ?? '')) {
            $message[] = "Доставка: " . $this->escapeHtml($deliveryName);
        }

        $message[] = "";

        if ($productsList = $this->formatProductsList($order->purchases ?? [], $currencySign, $this->getProductFormat())) {
            $message[] = "Товари:";
            $message = array_merge($message, $productsList);
        }

        return implode("\n", $message);
    }

    /**
     * Форматує повідомлення щомісячної статистики замовлень для Telegram
     *
     * @param int $ordersCount Кількість замовлень
     * @param float $totalSum Загальна сума
     * @param array<int, array{name: string, count: int}> $ordersByStatus Розбивка за статусами
     * @param array<int, array{name: string, amount: int}> $topProducts Топ товарів (назва, кількість)
     * @param string $monthLabel Назва місяця (наприклад "січень 2026")
     * @return string Повідомлення у форматі HTML
     */
    public function formatOrderStatsMessage(int $ordersCount, float $totalSum, array $ordersByStatus, array $topProducts, string $monthLabel): string
    {
        $currency = $this->mainHelper->getCurrentCurrency();
        $currencySign = ($currency && isset($currency->sign)) ? $currency->sign : '₴';

        $message = [
            "📊 Статистика замовлень за " . $this->escapeHtml($monthLabel),
            "",
            "Кількість замовлень: " . "<b>" . $this->escapeHtml((string) $ordersCount) . "</b>",
            "",
        ];

        // За статусами: виводимо всі статуси з замовленнями — скільки є, стільки й рядків
        if (!empty($ordersByStatus)) {
            $message[] = "За статусами:";
            foreach ($ordersByStatus as $item) {
                $name = $item['name'] ?? '';
                $count = (int) ($item['count'] ?? 0);
                $message[] = "• " . $this->escapeHtml($name) . ": " . $this->escapeHtml((string) $count);
            }
            $message[] = "";
        }

        $message[] = "Сума: " . $this->formatTotalPrice($totalSum, $currencySign);
        $message[] = "";

        if (!empty($topProducts)) {
            $message[] = "Топ " . count($topProducts) . " товарів:";
            foreach ($topProducts as $i => $item) {
                $name = $item['name'] ?? '';
                $amount = (int) ($item['amount'] ?? 0);
                $num = $i + 1;
                $message[] = "- <b>" . $num . ".</b> " . $this->escapeHtml($name) . " — " . $this->escapeHtml((string) $amount) . " шт.";
            }
        }

        $message[] = "";
        $message[] = "#order_stats";

        return implode("\n", $message);
    }

    /**
     * Отримує ім'я клієнта з об'єкта замовлення
     *
     * @param object $order Об'єкт замовлення
     * @return string Ім'я клієнта або "Не вказано"
     */
    private function getClientName($order): string
    {
        $name = trim(($order->name ?? '') . ' ' . ($order->last_name ?? ''));
        return $name ?: 'Не вказано';
    }

    /**
     * Отримує назву способу оплати
     *
     * @param object $order Об'єкт замовлення
     * @return string Назва способу оплати або "Не вказано"
     */
    private function getPaymentMethod($order): string
    {
        if (empty($order->payment_method_name)) {
            return 'Не вказано';
        }

        if (is_object($order->payment_method_name)) {
            return $order->payment_method_name->name ?? 'Не вказано';
        }

        return $order->payment_method_name;
    }

    /**
     * Отримує формат відображення товару з налаштувань
     *
     * @return string Формат: name_only|name_variant|name_sku|name_variant_sku
     */
    private function getProductFormat(): string
    {
        $format = $this->settings->get('sviat__telegram_notifier__product_format');
        $allowed = ['name_only', 'name_variant', 'name_sku', 'name_variant_sku'];
        return in_array($format, $allowed, true) ? $format : 'name_variant_sku';
    }

    /**
     * Форматує список товарів замовлення
     *
     * @param array $purchases Масив товарів замовлення
     * @param string $currencySign Знак валюти
     * @param string $productFormat Формат відображення товару
     * @return array Масив рядків з відформатованими товарами
     */
    private function formatProductsList(array $purchases, string $currencySign, string $productFormat): array
    {
        if (empty($purchases)) {
            return [];
        }

        $productsList = [];
        foreach ($purchases as $purchase) {
            $productName = $this->formatProductName(
                $purchase->product_name ?? '',
                $purchase->variant_name ?? '',
                $purchase->sku ?? '',
                $productFormat
            );
            $price = $purchase->price ?? $purchase->undiscounted_price ?? 0;
            $amount = $purchase->amount ?? 1;
            $formattedPrice = $this->formatPrice($price, $currencySign);

            $productsList[] = sprintf(
                "- %s (%s x %s)",
                $this->escapeHtml($productName),
                $this->escapeHtml($formattedPrice),
                $this->escapeHtml((string)$amount)
            );
        }

        return $productsList;
    }

    /**
     * Форматує назву товару згідно з обраним форматом
     *
     * @param string $productName Назва товару
     * @param string $variantName Назва варіанту
     * @param string $sku Артикул
     * @param string $format Формат: name_only|name_variant|name_sku|name_variant_sku
     * @return string Відформатована назва товару
     */
    private function formatProductName(string $productName, string $variantName, string $sku, string $format): string
    {
        switch ($format) {
            case 'name_only':
                return $productName;
            case 'name_variant':
                return $variantName ? "{$productName}({$variantName})" : $productName;
            case 'name_sku':
                return $sku ? "{$productName}, {$sku}" : $productName;
            default:
                return $this->formatProductNameWithVariantAndSku($productName, $variantName, $sku);
        }
    }

    /**
     * Форматує назву товару з варіантом та артикулом
     *
     * @param string $productName Назва товару
     * @param string $variantName Назва варіанту
     * @param string $sku Артикул
     * @return string Відформатована назва товару
     */
    private function formatProductNameWithVariantAndSku(string $productName, string $variantName, string $sku): string
    {
        $parts = [$productName];
        if ($variantName) {
            $parts[] = '(' . $variantName . ')';
        }
        if ($sku) {
            $parts[] = ', ' . $sku;
        }
        return implode('', $parts);
    }

    /**
     * Форматує ціну товару з двома знаками після коми
     *
     * @param float $price Ціна
     * @param string $currencySign Знак валюти
     * @return string Відформатована ціна
     */
    private function formatPrice(float $price, string $currencySign): string
    {
        return number_format($price, 2, '.', '') . ' ' . $currencySign;
    }

    /**
     * Форматує загальну суму в грошовий формат з жирним виділенням для HTML.
     * З копійками — "125 430.50 ₴", без копійок — "125 430 ₴" (пробіл як роздільник тисяч).
     * Використовується в замовленнях та в статистиці замовлень (formatOrderStatsMessage).
     *
     * @param float $price Сума
     * @param string $currencySign Знак валюти
     * @return string Відформатована сума з жирним виділенням
     */
    private function formatTotalPrice(float $price, string $currencySign): string
    {
        $hasCents = (abs($price - (int) $price) > 0.0001);

        $formatted = $hasCents
            ? number_format($price, 2, '.', ' ') . ' ' . $currencySign
            : number_format((int) $price, 0, '', ' ') . ' ' . $currencySign;

        return '<b>' . $this->escapeHtml($formatted) . '</b>';
    }

    /**
     * Екранує спеціальні символи для HTML (<, >, &)
     *
     * @param string $text Текст для екранування
     * @return string Екранований текст
     */
    public function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Форматує повідомлення про новий коментар в HTML для Telegram
     *
     * @param object $comment Об'єкт коментаря
     * @return string Повідомлення у форматі HTML
     */
    public function formatCommentMessage($comment): string
    {
        $message = [
            "💬 Додано коментар",
            "",
            "Від: " . $this->escapeHtml($comment->name ?? 'Анонім'),
            $this->formatEmailField($comment->email ?? ''),
        ];

        $pageUrl = $this->getCommentPageUrl($comment);
        $message[] = $this->formatPageLink($pageUrl);

        $message[] = "";
        $message[] = "Коментар:";
        $message[] = "<i>" . $this->escapeHtml($comment->text ?? '') . "</i>";

        return implode("\n", $message);
    }

    /**
     * Отримує URL сторінки з коментаря
     *
     * @param object $comment Об'єкт коментаря
     * @return string URL сторінки або порожній рядок
     */
    private function getCommentPageUrl($comment): string
    {
        if ($comment->type === 'product' && isset($comment->product) && !empty($comment->product->url)) {
            return Router::generateUrl('product', ['url' => $comment->product->url], true);
        }
        
        if ($comment->type === 'post' && isset($comment->post) && !empty($comment->post->url)) {
            return Router::generateUrl('post', ['url' => $comment->post->url], true);
        }
        
        return '';
    }

    /**
     * Форматує повідомлення про новий зворотний зв'язок в HTML для Telegram
     *
     * @param object $feedback Об'єкт зворотного зв'язку
     * @return string Повідомлення у форматі HTML
     */
    public function formatFeedbackMessage($feedback): string
    {
        $message = [
            "✉️ Зворотний зв'язок",
            "",
            "Від: " . $this->escapeHtml($feedback->name ?? 'Анонім'),
        ];

        $message[] = $this->formatEmailField($feedback->email ?? '');

        $message[] = "";
        $message[] = "Повідомлення:";
        $message[] = "<i>" . $this->escapeHtml($feedback->message ?? '') . "</i>";

        return implode("\n", $message);
    }

    /**
     * Форматує повідомлення про нову заявку на зворотний дзвінок в HTML для Telegram
     *
     * @param object $callback Об'єкт заявки на дзвінок
     * @return string Повідомлення у форматі HTML
     */
    public function formatCallbackMessage($callback): string
    {
        $message = [
            "📞 Заявка на зворотний дзвінок",
            "",
            "Ім'я: " . $this->escapeHtml($callback->name ?? 'Не вказано'),
            $this->formatPhoneField($callback->phone ?? ''),
        ];

        $url = trim($callback->url ?? '');
        if ($url) {
            $message[] = $this->formatPageLink($url);
        }

        $messageText = trim($callback->message ?? '');
        if ($messageText) {
            $message[] = "";
            $message[] = "Повідомлення:";
            $message[] = "<i>" . $this->escapeHtml($messageText) . "</i>";
        }

        return implode("\n", $message);
    }

    /**
     * Форматує посилання на сторінку для повідомлення
     *
     * @param string $url URL сторінки
     * @return string Відформатований рядок з посиланням або текстом
     */
    private function formatPageLink(string $url): string
    {
        if (empty($url)) {
            return "Сторінка: -";
        }

        $pageTitle = $this->getPageTitleFromUrl($url);
        if ($pageTitle) {
            return sprintf(
                'Сторінка: <a href="%s">%s</a>',
                $this->escapeHtml($url),
                $this->escapeHtml($pageTitle)
            );
        }

        return "Сторінка: " . $url;
    }

    /**
     * Отримує назву сторінки з URL
     * 
     * @param string $url URL сторінки
     * @return string|null Назва сторінки або null
     */
    private function getPageTitleFromUrl(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $urlParts = parse_url($url);
        $path = trim($urlParts['path'] ?? '', '/');
        
        if (empty($path)) {
            return null;
        }
        
        try {
            $routerCacheEntity = $this->entityFactory->get(\Okay\Entities\RouterCacheEntity::class);
            $cache = $routerCacheEntity->findOne(['slug_url' => $path]);
            
            if ($cache && !empty($cache->type) && !empty($cache->url)) {
                $title = $this->getTitleByCacheType($cache->type, $cache->url);
                if ($title) {
                    return $title;
                }
            }
            
            // Якщо не знайдено в RouterCacheEntity, пробуємо знайти статичну сторінку через PagesEntity
            $pagesEntity = $this->entityFactory->get(\Okay\Entities\PagesEntity::class);
            $page = $pagesEntity->cols(['name'])->get($path);
            if ($page && !empty($page->name)) {
                return $page->name;
            }
        } catch (\Exception $e) {
            // Якщо не вдалося отримати назву, повертаємо null
        }

        return null;
    }

    /**
     * Отримує назву сторінки за типом з кешу роутера
     *
     * @param string $type Тип сторінки (product, category, post, blog_category)
     * @param string $url URL сторінки
     * @return string|null Назва сторінки або null
     */
    private function getTitleByCacheType(string $type, string $url): ?string
    {
        switch ($type) {
            case 'product':
                return $this->getProductTitle($url);
            case 'category':
                return $this->getCategoryTitle($url);
            case 'post':
                return $this->getPostTitle($url);
            case 'blog_category':
                return $this->getBlogCategoryTitle($url);
            default:
                return null;
        }
    }

    /**
     * Отримує назву товару
     *
     * @param string $url URL товару
     * @return string|null Назва товару або null
     */
    private function getProductTitle(string $url): ?string
    {
        return $this->getEntityTitle(\Okay\Entities\ProductsEntity::class, $url);
    }

    /**
     * Отримує назву категорії
     *
     * @param string $url URL категорії
     * @return string|null Назва категорії або null
     */
    private function getCategoryTitle(string $url): ?string
    {
        return $this->getEntityTitle(\Okay\Entities\CategoriesEntity::class, $url);
    }

    /**
     * Отримує назву поста блогу
     *
     * @param string $url URL поста
     * @return string|null Назва поста або null
     */
    private function getPostTitle(string $url): ?string
    {
        return $this->getEntityTitle(\Okay\Entities\BlogEntity::class, $url);
    }

    /**
     * Отримує назву категорії блогу
     *
     * @param string $url URL категорії блогу
     * @return string|null Назва категорії блогу або null
     */
    private function getBlogCategoryTitle(string $url): ?string
    {
        return $this->getEntityTitle(\Okay\Entities\BlogCategoriesEntity::class, $url);
    }

    /**
     * Отримує назву сутності за URL
     *
     * @param string $entityClass Клас сутності
     * @param string $url URL сутності
     * @return string|null Назва сутності або null
     */
    private function getEntityTitle(string $entityClass, string $url): ?string
    {
        try {
            $entity = $this->entityFactory->get($entityClass);
            $item = $entity->cols(['name'])->get($url);
            return $item->name ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Форматує поле Email для повідомлення
     *
     * @param string $email Email адреса
     * @return string Відформатований рядок з Email
     */
    private function formatEmailField(string $email): string
    {
        $email = trim($email);
        return $email ? "Email: " . $this->escapeHtml($email) : "Email: -";
    }

    /**
     * Форматує поле Телефон для повідомлення
     *
     * @param string $phone Номер телефону
     * @return string Відформатований рядок з телефоном
     */
    private function formatPhoneField(string $phone): string
    {
        $phone = trim($phone);
        return $phone ? "Телефон: " . $this->escapeHtml($phone) : "Телефон: -";
    }
}

<?php

namespace Okay\Modules\Sviat\TelegramNotifier\Helpers;

use Okay\Core\Request;
use Okay\Entities\CommentsEntity;

/**
 * Генерація прикладів повідомлень для відображення в адмін-панелі
 */
class ExampleMessageHelper
{
    private FormatterHelper $formatterHelper;

    public function __construct(FormatterHelper $formatterHelper)
    {
        $this->formatterHelper = $formatterHelper;
    }

    /**
     * Генерує приклад повідомлення про замовлення для перегляду в адмін-панелі
     *
     * @return string Повідомлення у форматі HTML
     */
    public function getExampleOrderMessage()
    {
        return $this->formatterHelper->formatOrderMessage($this->getExampleOrderObject());
    }

    /**
     * Генерує приклад повідомлення про замовлення в HTML форматі для відображення в адмін-панелі
     *
     * @return string HTML-код повідомлення з переносами рядків
     */
    public function getExampleOrderMessageHtml(): string
    {
        return nl2br($this->getExampleOrderMessage());
    }

    /**
     * Генерує приклад повідомлення про оплачене замовлення в HTML форматі для адмін-панелі
     *
     * @param string $type 'short' — коротке (номер + сума), 'full' — повне (з клієнтом, товарами тощо)
     * @return string HTML-код повідомлення з переносами рядків
     */
    public function getExamplePaidOrderMessageHtml(string $type = 'full'): string
    {
        $order = $this->getExampleOrderObject();
        $text = ($type === 'short')
            ? $this->formatterHelper->formatPaidOrderMessageShort($order)
            : $this->formatterHelper->formatPaidOrderMessage($order);
        return nl2br($text);
    }

    private function getExampleOrderObject(): object
    {
        return (object)[
            'id' => 12345,
            'name' => 'Тарас',
            'last_name' => 'Шевченко',
            'phone' => '380501234567',
            'email' => 'example@email.com',
            'total_price' => 1500.00,
            'payment_method_name' => (object)['name' => 'Онлайн'],
            'delivery_name' => 'Нова Пошта',
            'purchases' => [
                (object)[
                    'product_name' => 'Назва товару',
                    'variant_name' => 'Варіант товару',
                    'sku' => 'SKU123456',
                    'price' => 500.00,
                    'amount' => 2,
                ],
                (object)[
                    'product_name' => 'Інший товар',
                    'variant_name' => '',
                    'sku' => 'SKU789012',
                    'price' => 500.00,
                    'amount' => 1,
                ],
            ],
        ];
    }

    /**
     * Генерує приклад повідомлення про коментар для перегляду в адмін-панелі
     *
     * @return string Повідомлення у форматі HTML
     */
    public function getExampleCommentMessage(): string
    {
        $exampleUrl = $this->buildExampleUrl('/products/samsung-galaxy-s23');
        
        $message = [
            "💬 Додано коментар",
            "",
            "Від: Леся Українка",
            "Email: lesya.ukrainka@example.com",
            "Сторінка: <a href=\"" . htmlspecialchars($exampleUrl, ENT_QUOTES, 'UTF-8') . "\">Смартфон Samsung Galaxy S23</a>",
        ];

        // Прев'ю має збігатися з тим, що прийде насправді: у стоковій OkayCMS
        // колонки `rating` немає, тож і рядка з оцінкою в повідомленні не буде.
        if (in_array('rating', CommentsEntity::getFields(), true)) {
            $message[] = "Оцінка: ★★★★★ (5/5)";
        }

        $message = array_merge($message, [
            "",
            "Коментар:",
            "<i>Чудовий товар! Дуже задоволена якістю та швидкою доставкою. Рекомендую!</i>",
        ]);

        return implode("\n", $message);
    }

    /**
     * Генерує приклад повідомлення про коментар в HTML форматі для відображення в адмін-панелі
     *
     * @return string HTML-код повідомлення з переносами рядків
     */
    public function getExampleCommentMessageHtml(): string
    {
        return nl2br($this->getExampleCommentMessage());
    }

    /**
     * Генерує приклад повідомлення про зворотний зв'язок для перегляду в адмін-панелі
     *
     * @return string Повідомлення у форматі HTML
     */
    public function getExampleFeedbackMessage()
    {
        $exampleFeedback = (object)[
            'name' => 'Іван Франко',
            'email' => 'ivan.franko@example.com',
            'message' => 'Доброго дня! Хочу запитати про наявність товару та умови доставки. Дякую!',
        ];

        return $this->formatterHelper->formatFeedbackMessage($exampleFeedback);
    }

    /**
     * Генерує приклад повідомлення про зворотний зв'язок в HTML форматі для відображення в адмін-панелі
     *
     * @return string HTML-код повідомлення з переносами рядків
     */
    public function getExampleFeedbackMessageHtml(): string
    {
        return nl2br($this->getExampleFeedbackMessage());
    }

    /**
     * Генерує приклад повідомлення про заявку на дзвінок для перегляду в адмін-панелі
     *
     * @return string Повідомлення у форматі HTML
     */
    public function getExampleCallbackMessage(): string
    {
        $exampleUrl = $this->buildExampleUrl('/products/iphone-15');
        
        $exampleCallback = (object)[
            'name' => 'Михайло Грушевський',
            'phone' => '+380501234567',
            'url' => $exampleUrl,
            'message' => 'Цікавить цей товар, потрібна консультація',
        ];

        $formattedMessage = $this->formatterHelper->formatCallbackMessage($exampleCallback);
        
        // Якщо метод не знайшов назву товару, додамо її вручну для прикладу
        $escapedUrl = htmlspecialchars($exampleUrl, ENT_QUOTES, 'UTF-8');
        if (strpos($formattedMessage, 'iPhone 15') === false && strpos($formattedMessage, $exampleUrl) !== false) {
            $formattedMessage = str_replace(
                "Сторінка: {$exampleUrl}",
                "Сторінка: <a href=\"{$escapedUrl}\">iPhone 15</a>",
                $formattedMessage
            );
        }
        
        return $formattedMessage;
    }

    /**
     * Генерує приклад повідомлення про заявку на дзвінок в HTML форматі для відображення в адмін-панелі
     *
     * @return string HTML-код повідомлення з переносами рядків
     */
    public function getExampleCallbackMessageHtml(): string
    {
        return nl2br($this->getExampleCallbackMessage());
    }

    /**
     * Генерує приклад повідомлення щомісячної статистики замовлень для адмін-панелі
     *
     * @return string Повідомлення у форматі HTML
     */
    public function getExampleOrderStatsMessageHtml(): string
    {
        $exampleOrdersByStatus = [
            ['name' => 'Виконано', 'count' => 35],
            ['name' => 'Скасовано', 'count' => 8],
            ['name' => 'У курьєра', 'count' => 4],
        ];
        $exampleTopProducts = [
            ['name' => 'Смартфон Samsung Galaxy S23', 'amount' => 12],
            ['name' => 'Навушники Apple AirPods Pro', 'amount' => 8],
            ['name' => 'Чохол для телефону', 'amount' => 15],
        ];
        $text = $this->formatterHelper->formatOrderStatsMessage(
            47,
            125430.50,
            $exampleOrdersByStatus,
            $exampleTopProducts,
            'січень 2026'
        );
        return nl2br($text);
    }

    /**
     * Формує приклад URL для тестових повідомлень
     *
     * @param string $path Шлях сторінки
     * @return string Повний URL
     */
    private function buildExampleUrl(string $path): string
    {
        return rtrim(Request::getRootUrl(), '/') . $path;
    }

    /**
     * Генерує приклад повідомлення про запит на підбір запчастин для перегляду в адмін-панелі
     *
     * @return string Повідомлення у форматі HTML
     */
    public function getExamplePartsSelectionMessage(): string
    {
        $exampleUrl = $this->buildExampleUrl('/products/samsung-galaxy-s23');
        
        $examplePartsSelection = (object)[
            'name' => 'Олександр Коваленко',
            'phone' => '+380501234567',
            'brand' => 'Samsung',
            'model' => 'Galaxy S23',
            'serial_number' => 'SN123456789',
            'url' => $exampleUrl,
            'message' => 'Потрібен екран для цього телефону',
            'upload' => '1234567890.jpg',
        ];

        return $this->formatterHelper->formatPartsSelectionMessage($examplePartsSelection);
    }

    /**
     * Генерує приклад повідомлення про запит на підбір запчастин в HTML форматі для відображення в адмін-панелі
     *
     * @return string HTML-код повідомлення з переносами рядків
     */
    public function getExamplePartsSelectionMessageHtml(): string
    {
        return nl2br($this->getExamplePartsSelectionMessage());
    }
}

<?php

namespace Okay\Modules\Sviat\TelegramNotifier\Helpers;

use Okay\Core\Settings;

/**
 * Основний клас для відправки повідомлень в Telegram
 */
class TelegramHelper
{
    private Settings $settings;
    private FormatterHelper $formatterHelper;
    
    private const MAX_MESSAGE_LENGTH = 4096;
    private const CURL_TIMEOUT = 10;
    private const CURL_CONNECT_TIMEOUT = 5;
    
    private const SETTING_ORDER_NOTIFY = 'sviat__telegram_notifier__order_notify_enabled';
    private const SETTING_COMMENT_NOTIFY = 'sviat__telegram_notifier__comment_notify_enabled';
    private const SETTING_FEEDBACK_NOTIFY = 'sviat__telegram_notifier__feedback_notify_enabled';
    private const SETTING_CALLBACK_NOTIFY = 'sviat__telegram_notifier__callback_notify_enabled';
    private const SETTING_BOT_TOKEN = 'sviat__telegram_notifier__bot_token';
    private const SETTING_CHAT_ID = 'sviat__telegram_notifier__chat_id';

    public function __construct(
        Settings $settings,
        FormatterHelper $formatterHelper
    ) {
        $this->settings = $settings;
        $this->formatterHelper = $formatterHelper;
    }

    /**
     * Відправляє повідомлення про нове замовлення в Telegram
     *
     * @param object $order Об'єкт замовлення
     * @return bool Успішність відправки
     */
    public function sendNewOrderNotification($order): bool
    {
        return $this->sendNotification(
            self::SETTING_ORDER_NOTIFY,
            fn() => $this->formatterHelper->formatOrderMessage($order)
        );
    }

    /**
     * Відправляє повідомлення про новий коментар в Telegram
     *
     * @param object $comment Об'єкт коментаря
     * @return bool Успішність відправки
     */
    public function sendNewCommentNotification($comment): bool
    {
        return $this->sendNotification(
            self::SETTING_COMMENT_NOTIFY,
            fn() => $this->formatterHelper->formatCommentMessage($comment)
        );
    }

    /**
     * Надсилає повідомлення у Telegram (POST, cURL, HTML)
     *
     * @param string $text Текст повідомлення у форматі HTML
     * @return bool Успішність відправки
     */
    private function sendMessage(string $text): bool
    {
        $botToken = trim($this->settings->get(self::SETTING_BOT_TOKEN));
        $chatId = trim($this->settings->get(self::SETTING_CHAT_ID));

        if (empty($botToken) || empty($chatId)) {
            error_log('TelegramNotifier: Missing bot_token or chat_id');
            return false;
        }

        $text = $this->truncateMessageIfNeeded($text);
        $url = "https://api.telegram.org/{$botToken}/sendMessage";
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CURL_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::CURL_TIMEOUT,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log('TelegramNotifier: cURL error - ' . $error);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $this->validateResponse($response, $httpCode);
    }

    /**
     * Обрізає повідомлення якщо воно перевищує максимальну довжину
     *
     * @param string $text Текст повідомлення
     * @return string Обрізаний текст якщо потрібно
     */
    private function truncateMessageIfNeeded(string $text): string
    {
        $textLength = mb_strlen($text, 'UTF-8');
        if ($textLength > self::MAX_MESSAGE_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_MESSAGE_LENGTH - 1, 'UTF-8') . '…';
            error_log('TelegramNotifier: Message truncated from ' . $textLength . ' to ' . self::MAX_MESSAGE_LENGTH . ' characters');
        }
        return $text;
    }

    /**
     * Валідує відповідь від Telegram API
     *
     * @param string $response Відповідь від API
     * @param int $httpCode HTTP код відповіді
     * @return bool Успішність операції
     */
    private function validateResponse(string $response, int $httpCode): bool
    {
        $data = json_decode($response, true);

        if ($httpCode !== 200 || empty($data['ok'])) {
            $errorMessage = $data['description'] ?? 'Unknown error';
            $errorCode = $data['error_code'] ?? 'N/A';
            error_log('TelegramNotifier: API error (HTTP ' . $httpCode . ', Error Code: ' . $errorCode . ') - ' . $errorMessage);
            return false;
        }

        return true;
    }

    /**
     * Відправляє повідомлення про новий зворотний зв'язок в Telegram
     *
     * @param object $feedback Об'єкт зворотного зв'язку
     * @return bool Успішність відправки
     */
    public function sendNewFeedbackNotification($feedback): bool
    {
        return $this->sendNotification(
            self::SETTING_FEEDBACK_NOTIFY,
            fn() => $this->formatterHelper->formatFeedbackMessage($feedback)
        );
    }

    /**
     * Відправляє повідомлення про нову заявку на зворотний дзвінок в Telegram
     *
     * @param object $callback Об'єкт заявки на дзвінок
     * @return bool Успішність відправки
     */
    public function sendNewCallbackNotification($callback): bool
    {
        return $this->sendNotification(
            self::SETTING_CALLBACK_NOTIFY,
            fn() => $this->formatterHelper->formatCallbackMessage($callback)
        );
    }

    /**
     * Загальний метод для відправки сповіщень
     *
     * @param string $settingKey Ключ налаштування для перевірки
     * @param callable $formatter Callback для форматування повідомлення
     * @return bool Успішність відправки
     */
    private function sendNotification(string $settingKey, callable $formatter): bool
    {
        if (!$this->isNotificationEnabled($settingKey)) {
            return false;
        }

        $message = $formatter();
        return $this->sendMessage($message);
    }

    /**
     * Перевіряє чи увімкнено конкретний тип сповіщень та чи налаштовані необхідні параметри
     *
     * @param string $settingKey Ключ налаштування для перевірки
     * @return bool Стан сповіщень
     */
    private function isNotificationEnabled(string $settingKey): bool
    {
        return $this->settings->get($settingKey)
            && $this->settings->get(self::SETTING_BOT_TOKEN)
            && $this->settings->get(self::SETTING_CHAT_ID);
    }
}

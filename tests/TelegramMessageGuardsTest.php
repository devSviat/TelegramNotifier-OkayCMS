<?php

namespace Modules\Sviat\TelegramNotifier;

use Okay\Core\Settings;
use Okay\Modules\Sviat\TelegramNotifier\Helpers\FormatterHelper;
use Okay\Modules\Sviat\TelegramNotifier\Helpers\TelegramHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Запобіжники перед відправкою в Telegram Bot API: ліміт довжини повідомлення
 * і перевірка, що сповіщення взагалі налаштоване. Перевищення 4096 символів —
 * це помилка 400 від API, тобто повністю втрачене сповіщення про замовлення.
 */
class TelegramMessageGuardsTest extends TestCase
{
    private const MAX = 4096;

    private function buildHelper(array $settings = []): TelegramHelper
    {
        $settingsStub = $this->createStub(Settings::class);
        $settingsStub->method('get')->willReturnCallback(
            static fn (string $key) => $settings[$key] ?? null
        );

        return new TelegramHelper($settingsStub, $this->createStub(FormatterHelper::class));
    }

    private function callPrivate(TelegramHelper $helper, string $method, mixed ...$args): mixed
    {
        $reflected = new \ReflectionMethod($helper, $method);
        if (PHP_VERSION_ID < 80100) { $reflected->setAccessible(true); }
        return $reflected->invokeArgs($helper, $args);
    }

    private function truncate(string $text): string
    {
        return $this->callPrivate($this->buildHelper(), 'truncateMessageIfNeeded', $text);
    }

    // --- ліміт довжини ------------------------------------------------------

    public function testMessageAtTheLimitIsUntouched(): void
    {
        $text = str_repeat('я', self::MAX);

        self::assertSame($text, $this->truncate($text));
    }

    public function testMessageBelowTheLimitIsUntouched(): void
    {
        $text = str_repeat('я', self::MAX - 1);

        self::assertSame($text, $this->truncate($text));
    }

    /**
     * Один символ понад ліміт уже обрізає: 4095 символів плюс трикрапка, тобто
     * рівно 4096 — не 4097, інакше API все одно відмовило б.
     */
    public function testMessageOverTheLimitIsTruncatedToExactlyTheLimit(): void
    {
        $result = $this->truncate(str_repeat('я', self::MAX + 1));

        self::assertSame(self::MAX, mb_strlen($result, 'UTF-8'));
        self::assertStringEndsWith('…', $result);
    }

    /**
     * Рахунок ведеться в символах, а не в байтах: кирилиця в UTF-8 займає два
     * байти, тож strlen() обрізав би вдвічі раніше й порізав символ навпіл.
     */
    public function testLengthIsCountedInCharactersNotBytes(): void
    {
        $cyrillic = str_repeat('я', self::MAX);

        self::assertGreaterThan(self::MAX, strlen($cyrillic));
        self::assertSame($cyrillic, $this->truncate($cyrillic));
    }

    public function testTruncationDoesNotSplitAMultibyteCharacter(): void
    {
        $result = $this->truncate(str_repeat('я', self::MAX * 2));

        self::assertSame($result, mb_convert_encoding($result, 'UTF-8', 'UTF-8'));
        self::assertSame(self::MAX, mb_strlen($result, 'UTF-8'));
    }

    // --- валідація відповіді ------------------------------------------------

    public function testSuccessfulResponseIsAccepted(): void
    {
        self::assertTrue($this->callPrivate($this->buildHelper(), 'validateResponse', '{"ok":true}', 200));
    }

    /**
     * HTTP 200 із ok:false — типова відповідь Telegram на «бота заблоковано в
     * цьому чаті». Її не можна рахувати успіхом лише через код 200.
     */
    /** @dataProvider badResponseProvider */
    #[DataProvider('badResponseProvider')]
    public function testFailedResponseIsRejected(string $body, int $httpCode): void
    {
        self::assertFalse($this->callPrivate($this->buildHelper(), 'validateResponse', $body, $httpCode));
    }

    public static function badResponseProvider(): array
    {
        return [
            'ok=false при 200'   => ['{"ok":false,"description":"chat not found","error_code":400}', 200],
            'HTTP 400'           => ['{"ok":true}', 400],
            'HTTP 500'           => ['', 500],
            'не JSON'            => ['<html>502 Bad Gateway</html>', 200],
            'порожня відповідь'  => ['', 200],
            'ok відсутній'       => ['{"result":[]}', 200],
        ];
    }

    // --- умови відправки ----------------------------------------------------

    /**
     * Сповіщення йде лише коли зійшлось усе троє: тип увімкнено, є токен бота і
     * є чат. Напівналаштований модуль мовчить, а не сипле помилками в лог.
     */
    /** @dataProvider notificationEnabledProvider */
    #[DataProvider('notificationEnabledProvider')]
    public function testNotificationRequiresTypeFlagTokenAndChat(array $settings, bool $expected): void
    {
        $helper = $this->buildHelper($settings);
        $result = $this->callPrivate($helper, 'isNotificationEnabled', 'sviat__telegram_notifier__notify_new_order');

        self::assertSame($expected, (bool) $result);
    }

    public static function notificationEnabledProvider(): array
    {
        $flag  = 'sviat__telegram_notifier__notify_new_order';
        $token = 'sviat__telegram_notifier__bot_token';
        $chat  = 'sviat__telegram_notifier__chat_id';

        return [
            'усе налаштовано'   => [[$flag => 1, $token => 'bot:123', $chat => '-100500'], true],
            'тип вимкнено'      => [[$flag => 0, $token => 'bot:123', $chat => '-100500'], false],
            'немає токена'      => [[$flag => 1, $token => '', $chat => '-100500'], false],
            'немає чату'        => [[$flag => 1, $token => 'bot:123', $chat => ''], false],
            'нічого не задано'  => [[], false],
        ];
    }

    /**
     * Форматування повідомлення не має навіть запускатись, коли сповіщення
     * вимкнене: побудова тексту замовлення тягне за собою походи в базу.
     */
    public function testDisabledNotificationSkipsTheFormatterEntirely(): void
    {
        $helper = $this->buildHelper([]);
        $called = false;

        $result = $this->callPrivate(
            $helper,
            'sendNotification',
            'sviat__telegram_notifier__notify_new_order',
            function () use (&$called): string {
                $called = true;
                return 'текст';
            }
        );

        self::assertFalse($result);
        self::assertFalse($called, 'форматер не мав викликатись при вимкнених сповіщеннях');
    }
}

<?php

namespace Modules\Sviat\TelegramNotifier;

use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Helpers\MainHelper;
use Okay\Modules\Sviat\TelegramNotifier\Helpers\FormatterHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Складання повідомлень для Telegram. Модуль шле їх у режимі parse_mode=HTML,
 * тож будь-який неекранований `<` у назві товару чи імені клієнта ламає
 * розмітку — Telegram відповідає помилкою і сповіщення не доходить взагалі.
 */
class FormatterHelperTest extends TestCase
{
    private FormatterHelper $formatter;

    protected function setUp(): void
    {
        $this->formatter = $this->buildFormatter();
    }

    private function buildFormatter(?string $productFormat = null, ?string $currencySign = '₴'): FormatterHelper
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(
            static fn (string $key) => $key === 'sviat__telegram_notifier__product_format' ? $productFormat : null
        );

        $mainHelper = $this->createStub(MainHelper::class);
        $mainHelper->method('getCurrentCurrency')->willReturn(
            $currencySign === null ? null : (object) ['sign' => $currencySign]
        );

        return new FormatterHelper($settings, $mainHelper, $this->createStub(EntityFactory::class));
    }

    private function callPrivate(FormatterHelper $formatter, string $method, mixed ...$args): mixed
    {
        $reflected = new \ReflectionMethod($formatter, $method);
        if (PHP_VERSION_ID < 80100) { $reflected->setAccessible(true); }
        return $reflected->invokeArgs($formatter, $args);
    }

    // --- екранування --------------------------------------------------------

    /** @dataProvider escapeProvider */
    #[DataProvider('escapeProvider')]
    public function testHtmlIsEscaped(string $raw, string $expected): void
    {
        self::assertSame($expected, $this->formatter->escapeHtml($raw));
    }

    public static function escapeProvider(): array
    {
        return [
            'кутові дужки'   => ['<b>жирний</b>', '&lt;b&gt;жирний&lt;/b&gt;'],
            'амперсанд'      => ['Пилосос & Co', 'Пилосос &amp; Co'],
            'лапки'          => ['13" монітор', '13&quot; монітор'],
            'апостроф'       => ["Кав'ярня", 'Кав&#039;ярня'],
            'кирилиця чиста' => ['Звичайна назва', 'Звичайна назва'],
        ];
    }

    // --- формат суми --------------------------------------------------------

    /**
     * Копійки показуються лише коли вони справді є. Поріг 0.0001 захищає від
     * ситуації, коли сума 125430.0 приходить із бази як 125429.99999999999 і
     * інакше друкувалася б із фальшивими копійками.
     */
    /** @dataProvider totalPriceProvider */
    #[DataProvider('totalPriceProvider')]
    public function testTotalPriceShowsCentsOnlyWhenPresent(float $price, string $expectedInner): void
    {
        self::assertSame(
            '<b>' . $expectedInner . '</b>',
            $this->callPrivate($this->formatter, 'formatTotalPrice', $price, '₴')
        );
    }

    public static function totalPriceProvider(): array
    {
        return [
            'кругла сума'          => [125430.0, '125 430 ₴'],
            'з копійками'          => [125430.5, '125 430.50 ₴'],
            'нуль'                 => [0.0, '0 ₴'],
            'дрібний шум нижче порогу' => [125430.00001, '125 430 ₴'],
            'дрібниця вище порогу' => [125430.001, '125 430.00 ₴'],
            'сотні без розділювача' => [430.0, '430 ₴'],
        ];
    }

    /**
     * Екранування йде ПІСЛЯ форматування, а теги <b> дописуються вже після
     * нього — інакше сам <b> був би екранований і Telegram показав би літерали.
     */
    public function testBoldTagsSurviveEscaping(): void
    {
        $result = $this->callPrivate($this->formatter, 'formatTotalPrice', 100.0, '<script>');

        self::assertStringStartsWith('<b>', $result);
        self::assertStringEndsWith('</b>', $result);
        self::assertStringContainsString('&lt;script&gt;', $result);
    }

    /** Ціна позиції — завжди дві цифри й без розділювача тисяч, на відміну від суми. */
    public function testItemPriceAlwaysHasTwoDecimalsAndNoThousandsSeparator(): void
    {
        self::assertSame('1234.50 ₴', $this->callPrivate($this->formatter, 'formatPrice', 1234.5, '₴'));
        self::assertSame('1234.00 ₴', $this->callPrivate($this->formatter, 'formatPrice', 1234.0, '₴'));
    }

    // --- назва товару -------------------------------------------------------

    /** @dataProvider productNameProvider */
    #[DataProvider('productNameProvider')]
    public function testProductNameFormats(string $format, string $variant, string $sku, string $expected): void
    {
        self::assertSame(
            $expected,
            $this->callPrivate($this->formatter, 'formatProductName', 'Пилосос', $variant, $sku, $format)
        );
    }

    public static function productNameProvider(): array
    {
        return [
            'лише назва'                 => ['name_only', 'Синій', 'SKU1', 'Пилосос'],
            'назва + варіант'            => ['name_variant', 'Синій', 'SKU1', 'Пилосос(Синій)'],
            'назва + варіант, без варіанта' => ['name_variant', '', 'SKU1', 'Пилосос'],
            'назва + артикул'            => ['name_sku', 'Синій', 'SKU1', 'Пилосос, SKU1'],
            'назва + артикул, без артикула' => ['name_sku', 'Синій', '', 'Пилосос'],
            'усе разом'                  => ['name_variant_sku', 'Синій', 'SKU1', 'Пилосос(Синій), SKU1'],
            'усе разом, без варіанта'    => ['name_variant_sku', '', 'SKU1', 'Пилосос, SKU1'],
            'усе разом, без артикула'    => ['name_variant_sku', 'Синій', '', 'Пилосос(Синій)'],
            'невідомий формат → повний'  => ['вигаданий', 'Синій', 'SKU1', 'Пилосос(Синій), SKU1'],
        ];
    }

    /** Невідоме або порожнє налаштування не має ламати вивід — падаємо у найповніший формат. */
    /** @dataProvider unknownFormatProvider */
    #[DataProvider('unknownFormatProvider')]
    public function testUnknownProductFormatSettingFallsBackToFull(?string $stored): void
    {
        $formatter = $this->buildFormatter($stored);

        self::assertSame('name_variant_sku', $this->callPrivate($formatter, 'getProductFormat'));
    }

    public static function unknownFormatProvider(): array
    {
        return [
            'не задано'      => [null],
            'порожній рядок' => [''],
            'вигаданий'      => ['вигаданий_формат'],
            'інший регістр'  => ['NAME_ONLY'],
        ];
    }

    // --- дані клієнта -------------------------------------------------------

    /** @dataProvider clientNameProvider */
    #[DataProvider('clientNameProvider')]
    public function testClientNameJoinsFirstAndLastName(array $order, string $expected): void
    {
        self::assertSame($expected, $this->callPrivate($this->formatter, 'getClientName', (object) $order));
    }

    public static function clientNameProvider(): array
    {
        return [
            'імʼя та прізвище' => [['name' => 'Іван', 'last_name' => 'Петренко'], 'Іван Петренко'],
            'лише імʼя'        => [['name' => 'Іван'], 'Іван'],
            'лише прізвище'    => [['last_name' => 'Петренко'], 'Петренко'],
            'порожньо'         => [[], 'Не вказано'],
            'самі пробіли'     => [['name' => '  ', 'last_name' => ' '], 'Не вказано'],
        ];
    }

    /**
     * Спосіб оплати приходить то рядком, то об'єктом — залежно від того, чи
     * замовлення вже пройшло через приєднання платіжного методу.
     */
    /** @dataProvider paymentMethodProvider */
    #[DataProvider('paymentMethodProvider')]
    public function testPaymentMethodAcceptsBothStringAndObject(mixed $value, string $expected): void
    {
        self::assertSame(
            $expected,
            $this->callPrivate($this->formatter, 'getPaymentMethod', (object) ['payment_method_name' => $value])
        );
    }

    public static function paymentMethodProvider(): array
    {
        return [
            'рядок'              => ['Готівка', 'Готівка'],
            'обʼєкт'             => [(object) ['name' => 'Картка'], 'Картка'],
            'обʼєкт без name'    => [(object) ['id' => 3], 'Не вказано'],
            'порожній рядок'     => ['', 'Не вказано'],
            'null'               => [null, 'Не вказано'],
        ];
    }

    // --- статистика ---------------------------------------------------------

    /**
     * Повідомлення зі щомісячною статистикою будується цілком з аргументів —
     * жодного походу в базу. Нумерація топу починається з 1, а не з 0.
     */
    public function testOrderStatsMessageIsBuiltEntirelyFromArguments(): void
    {
        $message = $this->formatter->formatOrderStatsMessage(
            42,
            125430.5,
            [
                ['name' => 'Виконано', 'count' => 30],
                ['name' => 'Скасовано', 'count' => 12],
            ],
            [
                ['name' => 'Пилосос', 'amount' => 9],
                ['name' => 'Чайник', 'amount' => 4],
            ],
            'липень 2025'
        );

        self::assertStringContainsString('липень 2025', $message);
        self::assertStringContainsString('Кількість замовлень: <b>42</b>', $message);
        self::assertStringContainsString('• Виконано: 30', $message);
        self::assertStringContainsString('• Скасовано: 12', $message);
        self::assertStringContainsString('<b>125 430.50 ₴</b>', $message);
        self::assertStringContainsString('Топ 2 товарів:', $message);
        self::assertStringContainsString('<b>1.</b> Пилосос — 9 шт.', $message);
        self::assertStringContainsString('<b>2.</b> Чайник — 4 шт.', $message);
        self::assertStringEndsWith('#order_stats', $message);
    }

    /** Порожні блоки не мають лишати після себе заголовків без вмісту. */
    public function testOrderStatsOmitsEmptySections(): void
    {
        $message = $this->formatter->formatOrderStatsMessage(0, 0.0, [], [], 'липень 2025');

        self::assertStringNotContainsString('За статусами:', $message);
        self::assertStringNotContainsString('Топ', $message);
        self::assertStringContainsString('<b>0 ₴</b>', $message);
    }

    /** Назви статусів і товарів приходять з бази — вони мусять екрануватись. */
    public function testOrderStatsEscapesNamesFromTheDatabase(): void
    {
        $message = $this->formatter->formatOrderStatsMessage(
            1,
            0.0,
            [['name' => '<b>Зламаний</b>', 'count' => 1]],
            [['name' => 'Пилосос & Co', 'amount' => 1]],
            '<script>'
        );

        self::assertStringContainsString('&lt;b&gt;Зламаний&lt;/b&gt;', $message);
        self::assertStringContainsString('Пилосос &amp; Co', $message);
        self::assertStringContainsString('&lt;script&gt;', $message);
    }

    /** Без валюти в системі знак підставляється гривневий, а не порожній. */
    public function testCurrencySignFallsBackToHryvnia(): void
    {
        $formatter = $this->buildFormatter(null, null);
        $message = $formatter->formatOrderStatsMessage(1, 100.0, [], [], 'липень');

        self::assertStringContainsString('100 ₴', $message);
    }
}

<?php

namespace Modules\Sviat\TelegramNotifier;

use PHPUnit\Framework\TestCase;

/**
 * Сторінку налаштувань відкриває кожен менеджер з правом settings, а токен бота
 * дає повне керування ботом. Тож токен у HTML не повертається, а порожнє поле
 * при збереженні лишає збережений токен.
 */
class BotTokenWriteOnlyTest extends TestCase
{
    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4) . '/Okay/Modules/Sviat/TelegramNotifier/' . $path);
    }

    public function testTheTemplateNeverPrintsTheToken(): void
    {
        $template = $this->source('Backend/design/html/telegram_admin.tpl');

        $this->assertStringNotContainsString('$bot_token|', $template);
        $this->assertMatchesRegularExpression('~<input type="password" name="bot_token"[^>]*value=""~', $template);
    }

    public function testAnEmptyFieldKeepsTheStoredToken(): void
    {
        $controller = $this->source('Backend/Controllers/TelegramAdmin.php');

        $this->assertStringNotContainsString("set('sviat__telegram_notifier__bot_token', \$this->request->post('bot_token'))", $controller);
        $this->assertMatchesRegularExpression('~if \(\$botToken !== \'\'\) \{\s*\$this->settings->set\(\'sviat__telegram_notifier__bot_token\', \$botToken\);~', $controller);
    }

    public function testValidationAcceptsAnEmptyFieldOnlyWhenATokenIsSaved(): void
    {
        $script = $this->source('Backend/design/js/telegram_admin.js');

        $this->assertStringContainsString("data('token-set') === 1", $script);
    }
}

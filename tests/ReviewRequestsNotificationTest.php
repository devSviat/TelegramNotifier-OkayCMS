<?php

namespace Modules\Sviat\TelegramNotifier;

use Okay\Core\Scheduler\Schedule;
use Okay\Helpers\CommentsHelper;
use Okay\Modules\Sviat\TelegramNotifier\Extenders\FrontExtender;
use Okay\Modules\Sviat\TelegramNotifier\Init\Init;
use PHPUnit\Framework\TestCase;

/**
 * Відгук за посиланням із листа ReviewRequests зберігається повз
 * CommentsHelper::addCommentProcedure, і сповіщення в Telegram про нього не
 * приходило. TelegramNotifier слухає ще й подію ReviewRequestReviews::create
 * тим самим обробником — але лише там, де ReviewRequests є: тести модуля
 * йдуть і на чистому OkayCMS, і на форку, без нього.
 */
class ReviewRequestsNotificationTest extends TestCase
{
    private const REVIEW_REQUESTS = 'Okay\Modules\Sviat\ReviewRequests\Helpers\ReviewRequestReviews';

    /** @return array<int, array{string, string}> пари «що розширюється» => «чим» */
    private function registrations(): array
    {
        $init = new class (0, 'Sviat', 'TelegramNotifier') extends Init {
            public array $queue = [];

            public function __construct($moduleId, $vendor, $moduleName)
            {
            }

            protected function registerBackendController($controllerClass)
            {
            }

            protected function addBackendControllerPermission($controllerClass, $permission)
            {
            }

            protected function registerQueueExtension($expandable, $extension)
            {
                // Як AbstractExtender::validateExtension(): реєстрація на відсутній
                // метод — виняток, тобто зламаний init() на кожному запиті.
                if (!method_exists($expandable[0], $expandable[1])) {
                    throw new \Exception("Expandable \"{$expandable[0]}::{$expandable[1]}()\" is not a method");
                }
                $this->queue[] = [implode('::', $expandable), implode('::', $extension)];
            }

            public function registerSchedule(Schedule $schedule): void
            {
            }
        };
        $init->init();

        return $init->queue;
    }

    public function testOrdinaryReviewIsHandled(): void
    {
        $this->assertContains(
            [CommentsHelper::class . '::addCommentProcedure', FrontExtender::class . '::addCommentProcedure'],
            $this->registrations()
        );
    }

    /** Де ReviewRequests є — його відгук іде тим самим обробником, де немає — підписки немає й init() цілий. */
    public function testReviewFromTheRequestLinkIsHandledOnlyWhereTheModuleExists(): void
    {
        $subscription = [self::REVIEW_REQUESTS . '::create', FrontExtender::class . '::addCommentProcedure'];

        if (method_exists(self::REVIEW_REQUESTS, 'create')) {
            $this->assertContains($subscription, $this->registrations());
        } else {
            $this->assertNotContains($subscription, $this->registrations());
        }
    }

    /**
     * Обробник бере перший аргумент як id коментаря. Подія create() мусить
     * віддавати саме його, і лише для збереженого відгуку: дубль і збій
     * повертають null раніше, до події.
     */
    public function testRequestEventCarriesTheSavedCommentId(): void
    {
        if (!method_exists(self::REVIEW_REQUESTS, 'create')) {
            $this->markTestSkipped('ReviewRequests не встановлено');
        }

        $method = new \ReflectionMethod(self::REVIEW_REQUESTS, 'create');
        $lines = explode("\n", (string) file_get_contents((string) $method->getFileName()));
        $body = implode("\n", array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $this->assertSame(1, substr_count($body, 'ExtenderFacade::execute('));
        $this->assertStringContainsString('return ExtenderFacade::execute(__METHOD__, $commentId, func_get_args());', $body);
        $this->assertLessThan(strpos($body, 'ExtenderFacade::execute('), strrpos($body, 'return null;'));
    }
}

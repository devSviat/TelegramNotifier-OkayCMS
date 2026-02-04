<?php

namespace Okay\Modules\Sviat\TelegramNotifier;

use Okay\Core\EntityFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Settings;
use Okay\Helpers\CommentsHelper;
use Okay\Helpers\MainHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Modules\Sviat\TelegramNotifier\Extenders\FrontExtender;
use Okay\Modules\Sviat\TelegramNotifier\Helpers\ExampleMessageHelper;
use Okay\Modules\Sviat\TelegramNotifier\Helpers\TelegramHelper;
use Okay\Modules\Sviat\TelegramNotifier\Helpers\FormatterHelper;

return [
    FormatterHelper::class => [
        'class' => FormatterHelper::class,
        'arguments' => [
            new SR(Settings::class),
            new SR(MainHelper::class),
            new SR(EntityFactory::class),
        ],
    ],
    ExampleMessageHelper::class => [
        'class' => ExampleMessageHelper::class,
        'arguments' => [
            new SR(FormatterHelper::class),
        ],
    ],
    TelegramHelper::class => [
        'class' => TelegramHelper::class,
        'arguments' => [
            new SR(Settings::class),
            new SR(FormatterHelper::class),
        ],
    ],
    FrontExtender::class => [
        'class' => FrontExtender::class,
        'arguments' => [
            new SR(TelegramHelper::class),
            new SR(EntityFactory::class),
            new SR(OrdersHelper::class),
            new SR(CommentsHelper::class),
        ],
    ],
];

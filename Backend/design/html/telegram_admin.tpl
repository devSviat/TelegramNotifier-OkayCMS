{$meta_title = $btr->sviat_telegram_notifier_title scope=global}

{*Назва сторінки*}
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="heading_page">{$btr->sviat_telegram_notifier_title|escape}</div>
    </div>
</div>

{*Виведення успішних повідомлень*}
{if $message_success}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_success == 'updated'}
                            {$btr->general_settings_saved|escape}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}

<form method="post" enctype="multipart/form-data" id="telegram_settings_form"
    data-error-empty-token="{$btr->sviat_telegram_notifier_error_empty_token|escape}"
    data-error-invalid-token="{$btr->sviat_telegram_notifier_error_invalid_token|escape}"
    data-error-empty-chat-id="{$btr->sviat_telegram_notifier_error_empty_chat_id|escape}">
    <input type="hidden" name="session_id" value="{$smarty.session.id}">

    <div class="row">
        {*Ліва колонка - налаштування бота*}
        <div class="col-lg-6 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    <span>{$btr->sviat_telegram_notifier_settings_title|escape}</span>
                </div>
                <div class="toggle_body_wrap on">
                    <div class="row">
                        <div class="col-xxl-12 col-lg-12 col-md-12">
                            <div class="heading_label heading_label--required">
                                <span>{$btr->sviat_telegram_notifier_bot_token|escape}</span>
                                <i class="fn_tooltips"
                                    title="{$btr->sviat_telegram_notifier_bot_token_hint|default:"Токен бота отриманий від @BotFather"|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </div>
                            <div class="mb-1">
                                <input type="text" name="bot_token" id="bot_token" class="form-control"
                                    value="{$bot_token|escape}" placeholder="bot123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                                <div class="telegram-error-message" id="bot_token_error" style="display: none;"></div>
                            </div>
                        </div>

                        <div class="col-xxl-12 col-lg-12 col-md-12">
                            <div class="heading_label heading_label--required">
                                <span>{$btr->sviat_telegram_notifier_chat_id|escape}</span>
                                <i class="fn_tooltips"
                                    title="{$btr->sviat_telegram_notifier_chat_id_hint|default:"ID чату або каналу куди відправлятитимуться повідомлення"|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </div>
                            <div class="mb-1">
                                <input type="text" name="chat_id" id="chat_id" class="form-control"
                                    value="{$chat_id|escape}" placeholder="-1001234567890">
                                <div class="telegram-error-message" id="chat_id_error" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {*Права колонка - документація*}
        <div class="col-lg-6 col-md-12">
            <div class="telegram-docs-title">
                <span>{$btr->sviat_telegram_notifier_docs_bot_token|escape}</span>
            </div>
            <div class="telegram-docs-text">
                {$btr->sviat_telegram_notifier_docs_bot_token_text}
            </div>
            <div class="telegram-docs-title">
                <span>{$btr->sviat_telegram_notifier_docs_chat_id|escape}</span>
            </div>
            <div class="telegram-docs-text">
                {$btr->sviat_telegram_notifier_docs_chat_id_text}
            </div>
        </div>
    </div>

    {*Блоки сповіщень - знизу*}
    <div class="row mt-2">
        {*Нове замовлення*}
        <div class="col-lg-3 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box heading_box--switch-right">
                    <span>{$btr->sviat_telegram_notifier_order_notify_title|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="order_notify_enabled" value="1" type="checkbox"
                            id="telegram_order_notify_enabled" {if $order_notify_enabled}checked{/if}>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
                <div class="toggle_body_wrap on">
                    <div class="heading_label">
                        <span>{$btr->sviat_telegram_notifier_product_format|escape}</span>
                    </div>
                    <label>
                        <select name="product_format" class="selectpicker form-control">
                            <option value="name_only" {if $product_format == 'name_only'}selected{/if}>
                                {$btr->sviat_telegram_notifier_format_name_only|escape}</option>
                            <option value="name_variant" {if $product_format == 'name_variant'}selected{/if}>
                                {$btr->sviat_telegram_notifier_format_name_variant|escape}</option>
                            <option value="name_sku" {if $product_format == 'name_sku'}selected{/if}>
                                {$btr->sviat_telegram_notifier_format_name_sku|escape}</option>
                            <option value="name_variant_sku"
                                {if $product_format == 'name_variant_sku' || !$product_format}selected{/if}>
                                {$btr->sviat_telegram_notifier_format_name_variant_sku|escape}</option>
                        </select>
                    </label>

                    <div class="row mt-h">
                        <div class="col-xxl-12 col-lg-12 col-md-12">
                            <div class="heading_label">
                                <span>{$btr->sviat_telegram_notifier_example_title|escape}</span>
                            </div>
                            <div class="telegram_message_preview">
                                {$example_order_message}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {*Новий коментар*}
        <div class="col-lg-3 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box heading_box--switch-right">
                    <span>{$btr->sviat_telegram_notifier_comment_notify_title|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="comment_notify_enabled" value="1" type="checkbox"
                            id="telegram_comment_notify_enabled" {if $comment_notify_enabled}checked{/if}>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
                <div class="toggle_body_wrap on">
                    <div class="row">
                        <div class="col-xxl-12 col-lg-12 col-md-12">
                            <div class="heading_label">
                                <span>{$btr->sviat_telegram_notifier_example_title|escape}</span>
                            </div>
                            <div class="telegram_message_preview">
                                {$example_comment_message}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {*Зворотний зв'язок*}
        <div class="col-lg-3 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box heading_box--switch-right">
                    <span>{$btr->sviat_telegram_notifier_feedback_notify_title|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="feedback_notify_enabled" value="1" type="checkbox"
                            id="telegram_feedback_notify_enabled" {if $feedback_notify_enabled}checked{/if}>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
                <div class="toggle_body_wrap on">
                    <div class="row">
                        <div class="col-xxl-12 col-lg-12 col-md-12">
                            <div class="heading_label">
                                <span>{$btr->sviat_telegram_notifier_example_title|escape}</span>
                            </div>
                            <div class="telegram_message_preview">
                                {$example_feedback_message}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {*Заявка на дзвінок*}
        <div class="col-lg-3 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box heading_box--switch-right">
                    <span>{$btr->sviat_telegram_notifier_callback_notify_title|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="callback_notify_enabled" value="1" type="checkbox"
                            id="telegram_callback_notify_enabled" {if $callback_notify_enabled}checked{/if}>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
                <div class="toggle_body_wrap on">
                    <div class="row">
                        <div class="col-xxl-12 col-lg-12 col-md-12">
                            <div class="heading_label">
                                <span>{$btr->sviat_telegram_notifier_example_title|escape}</span>
                            </div>
                            <div class="telegram_message_preview">
                                {$example_callback_message}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {*Оплачене замовлення та Статистика замовлень — другий ряд*}
    <div class="row mt-2">
        {*Оплачене замовлення*}
        <div class="col-lg-3 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box heading_box--switch-right">
                    <span>{$btr->sviat_telegram_notifier_paid_order_notify_title|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="paid_order_notify_enabled" value="1" type="checkbox"
                            id="telegram_paid_order_notify_enabled" {if $paid_order_notify_enabled}checked{/if}>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
                <div class="toggle_body_wrap on">
                    <div class="heading_label">
                        <span>{$btr->sviat_telegram_notifier_paid_order_message_type|escape}</span>
                    </div>
                    <label>
                        <select name="paid_order_message_type" id="telegram_paid_order_message_type" class="selectpicker form-control">
                            <option value="short" {if $paid_order_message_type == 'short'}selected{/if}>
                                {$btr->sviat_telegram_notifier_paid_order_type_short|escape}</option>
                            <option value="full" {if $paid_order_message_type == 'full' || !$paid_order_message_type}selected{/if}>
                                {$btr->sviat_telegram_notifier_paid_order_type_full|escape}</option>
                        </select>
                    </label>

                    {*Формат товару — показується лише при виборі «Повне» (перемикається по JS без збереження)*}
                    <div class="fn_paid_order_product_format_block" {if $paid_order_message_type != 'full'}style="display:none"{/if}>
                        <div class="heading_label">
                            <span>{$btr->sviat_telegram_notifier_product_format|escape}</span>
                        </div>
                        <label>
                            <select name="product_format" class="selectpicker form-control">
                                <option value="name_only" {if $product_format == 'name_only'}selected{/if}>
                                    {$btr->sviat_telegram_notifier_format_name_only|escape}</option>
                                <option value="name_variant" {if $product_format == 'name_variant'}selected{/if}>
                                    {$btr->sviat_telegram_notifier_format_name_variant|escape}</option>
                                <option value="name_sku" {if $product_format == 'name_sku'}selected{/if}>
                                    {$btr->sviat_telegram_notifier_format_name_sku|escape}</option>
                                <option value="name_variant_sku"
                                    {if $product_format == 'name_variant_sku' || !$product_format}selected{/if}>
                                    {$btr->sviat_telegram_notifier_format_name_variant_sku|escape}</option>
                            </select>
                        </label>
                    </div>

                    <div class="row mt-h">
                        <div class="col-xxl-12 col-lg-12 col-md-12">
                            <div class="heading_label">
                                <span>{$btr->sviat_telegram_notifier_example_title|escape}</span>
                            </div>
                            <div id="example_paid_order_short" class="telegram_message_preview" {if $paid_order_message_type != 'short'}style="display:none"{/if}>
                                {$example_paid_order_message_short}
                            </div>
                            <div id="example_paid_order_full" class="telegram_message_preview" {if $paid_order_message_type != 'full'}style="display:none"{/if}>
                                {$example_paid_order_message_full}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {*Статистика замовлень (крон 1-го числа о 9:00)*}
        <div class="col-lg-3 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box heading_box--switch-right">
                    <span>{$btr->sviat_telegram_notifier_order_stats_title|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="order_stats_enabled" value="1" type="checkbox"
                            id="telegram_order_stats_enabled" {if $order_stats_enabled}checked{/if}>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
                <div class="toggle_body_wrap on">
                    <div class="heading_label">
                        <span>{$btr->sviat_telegram_notifier_order_stats_schedule|escape}</span>
                    </div>
                    <div class="row mt-h">
                        <div class="col-xxl-12 col-lg-12 col-md-12">
                            <div class="heading_label">
                                <span>{$btr->sviat_telegram_notifier_example_title|escape}</span>
                            </div>
                            <div class="telegram_message_preview">
                                {$example_order_stats_message}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {*Кнопка збереження внизу форми*}
    <div class="row mb-3">
        <div class="col-lg-12 col-md-12">
            <button type="submit" class="btn btn_small btn_blue float-md-right">
                {include file='svg_icon.tpl' svgId='checked'}
                <span>{$btr->general_apply|escape}</span>
            </button>
        </div>
    </div>
</form>
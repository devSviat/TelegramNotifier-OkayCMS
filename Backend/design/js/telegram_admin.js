"use strict";

$(function(){
    // Отримуємо тексти помилок з data-атрибутів форми
    var $form = $('#telegram_settings_form');
    var telegramErrors = {
        emptyToken: $form.data('error-empty-token') || '',
        invalidToken: $form.data('error-invalid-token') || '',
        emptyChatId: $form.data('error-empty-chat-id') || ''
    };

    // Обробник зміни стану увімкнення
    $(document).on('change', '#telegram_notify_enabled', function() {
        $(this).closest('form').submit();
    });

    // Обробник зміни стану увімкнення повідомлень про замовлення
    $(document).on('change', '#telegram_order_notify_enabled', function() {
        $(this).closest('form').submit();
    });

    // Обробник зміни стану увімкнення повідомлень про коментарі
    $(document).on('change', '#telegram_comment_notify_enabled', function() {
        $(this).closest('form').submit();
    });

    // Обробник зміни стану увімкнення повідомлень про зворотний зв'язок
    $(document).on('change', '#telegram_feedback_notify_enabled', function() {
        $(this).closest('form').submit();
    });

    // Обробник зміни стану увімкнення повідомлень про заявки на дзвінок
    $(document).on('change', '#telegram_callback_notify_enabled', function() {
        $(this).closest('form').submit();
    });

    // Обробник зміни стану увімкнення повідомлень про оплачене замовлення
    $(document).on('change', '#telegram_paid_order_notify_enabled', function() {
        $(this).closest('form').submit();
    });

    // Обробник зміни стану увімкнення щомісячної статистики замовлень
    $(document).on('change', '#telegram_order_stats_enabled', function() {
        $(this).closest('form').submit();
    });

    // Синхронізація обох селектів «Формат відображення товару» (Нове замовлення та Оплачене замовлення)
    $(document).on('change', 'select[name="product_format"]', function() {
        var value = $(this).val();
        $form.find('select[name="product_format"]').not(this).val(value);
        if ($form.find('.selectpicker').length) {
            $form.find('select[name="product_format"]').selectpicker('refresh');
        }
    });

    // Функція для відображення помилки
    function showError(fieldId, errorDivId, message) {
        $('#' + errorDivId).text(message).show();
        $('#' + fieldId).addClass('is-invalid');
    }

    // Функція для очищення помилки
    function clearError(fieldId, errorDivId) {
        $('#' + errorDivId).hide();
        $('#' + fieldId).removeClass('is-invalid');
    }

    // Валідація Bot Token
    function validateBotToken(token) {
        if (!token) {
            return telegramErrors.emptyToken;
        }
        if (!token.match(/^bot\d+:[A-Za-z0-9_-]+$/)) {
            return telegramErrors.invalidToken;
        }
        return '';
    }

    // Chat ID — обов'язкове поле (тільки перевірка на порожнє)
    function validateChatId(chatId) {
        if (!chatId) {
            return telegramErrors.emptyChatId;
        }
        return '';
    }
    
    // Валідація форми при відправці
    $(document).on('submit', '#telegram_settings_form', function(e) {
        var botToken = $('#bot_token').val().trim();
        var chatId = $('#chat_id').val().trim();
        var botTokenError = validateBotToken(botToken);
        var chatIdError = validateChatId(chatId);
        
        // Відображення помилок
        if (botTokenError) {
            showError('bot_token', 'bot_token_error', botTokenError);
        } else {
            clearError('bot_token', 'bot_token_error');
        }
        
        if (chatIdError) {
            showError('chat_id', 'chat_id_error', chatIdError);
        } else {
            clearError('chat_id', 'chat_id_error');
        }
        
        if (botTokenError || chatIdError) {
            e.preventDefault();
            return false;
        }
    });
});

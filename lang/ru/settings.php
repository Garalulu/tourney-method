<?php

return [
    'title' => 'Настройки',
    'discord_webhook' => [
        'title' => 'Подключить Discord Webhook',
        'description' => 'Получайте уведомления о турнирах в ЛС или на Ваш сервер.',
        'webhook_url' => 'Webhook URL',
        'valid' => 'Допустимый',
        'invalid' => 'Недопустимый',
        'how_to' => 'Как создать Discord Webhook',
        'how_to_url' => 'https://support.discord.com/hc/ru/articles/228383668-%D0%92%D0%B2%D0%B5%D0%B4%D0%B5%D0%BD%D0%B8%D0%B5-%D0%B2-%D0%B2%D0%B5%D0%B1%D1%85%D1%83%D0%BA%D0%B8',
        'test_button' => 'Отправить тестовое сообщение',
        'test_sending' => 'Отправка...',
        'test_success' => 'Сообщение отправлено! Проверьте Discord.',
        'test_error' => 'Не удалось отправить тестовое сообщение.',
    ],
    'game_mode' => [
        'title' => 'Основной режим',
        'description' => 'Ваш основной режим игры для дэшборда.',
    ],
    'in_app_notifications' => [
        'title' => 'Настройки уведомлений',
        'description' => 'Выберите, какие уведомления Вы хотите получать.',
        'categories' => [
            'participation_records' => 'Записи участия',
            'new_tournament_alert' => 'Уведомления о новых турнирах',
        ],
        'events' => [
            'teammate_changes' => [
                'title' => 'Изменения команды',
                'description' => 'Когда другой игрок добавляет Вас в команду.',
            ],
            'participation_updates' => [
                'title' => 'Изменения деталей участия в турнирах',
                'description' => 'Когда другой игрок изменяет название команды, стадию, или сид на турнире в котором Вы участвуете.',
            ],
            'deletion_requests' => [
                'title' => 'Результат запросов на удаление',
                'description' => 'Когда Ваш запрос на удаление участия одобрен или отклонен.',
            ],
            'eligible_registration_open' => [
                'title' => 'Открытие регистраций',
                'description' => 'Когда открывается регистрация на турнир в котором Вы можете участвовать.',
            ],
            'eligible_registration_closing_24h' => [
                'title' => 'Ближайшее закрытие регистраций',
                'description' => 'Когда регистрация на турнир в котором Вы можете участвовать закрывается в течение 24 часов.',
            ],
        ],
    ],
    'save_button' => 'Сохранить',
    'saving' => 'Сохранение...',
    'saved' => 'Успешно сохранено!',
    'save_error' => 'Не удалось сохранить. Попробуйте ещё раз.',
];

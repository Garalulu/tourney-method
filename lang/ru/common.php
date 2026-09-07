<?php

return [
    'changelog' => [
        'eyebrow' => 'Что изменилось',
        'dont_show_again' => 'Больше не показывать это обновление',
        'ok' => 'Окей',
        'close' => 'Закрыть список изменений',
    ],
    'errors' => [
        404 => [
            'heading' => 'Страница не найдена',
            'message' => 'Страница, которую вы ищете, не существует или была перемещена.',
            'go_home' => 'Вернуться на главную',
            'go_back' => 'Назад',
        ],
        401 => [
            'heading' => 'Требуется аутентификация',
        ],
        403 => [
            'heading' => 'Доступ запрещен',
        ],
        500 => [
            'heading' => 'Что-то пошло не так',
        ],
    ],
    'nav' => [
        'tournaments' => 'Турниры',
        'dashboard' => 'Дэшборд',
        'admin' => 'Админ панель',
        'profile' => 'Профиль',
        'settings' => 'Настройки',
        'device_theme' => 'Device theme',
        'use_device_theme' => 'Use Device theme',
        'dark_theme' => 'Dark theme',
        'light_theme' => 'Light theme',
        'search' => 'Поиск',
        'logout' => 'Выйти из аккаунта',
        'login_with_osu' => 'Войти с помощью osu!',
    ],
    'actions' => [
        'reload' => 'Попробовать ещё раз',
    ],
    'messages' => [
        'language_updated' => 'Язык успешно обновлен.',
    ],
    'notifications' => [
        'title' => 'Уведомления',
        'read_all' => 'Отметить всё прочитанным',
        'remove_read' => 'Remove all read notifications',
        'loading' => 'Загрузка уведомлений...',
        'empty' => 'Еще нет уведомлений.',
        'fields' => [
            'team_name' => 'Название команды',
            'stage_value' => 'Итоговая стадия',
            'seed' => 'Место в квалификациях/групповой стадии',
        ],
        'messages' => [
            'teammate_added' => [
                'title' => 'Добавлен как тиммейт',
                'body' => ':actor добавил(-а) Вас как тиммейта на :tournament.',
            ],
            'teammate_removed' => [
                'title' => 'Удален как тиммейт',
                'body' => ':actor удалил(-а) Вас как тиммейта на :tournament.',
            ],
            'participation_updated' => [
                'title' => 'Участие в турнире обновлено',
                'body' => ':actor обновил(-а) информацию для :tournament: :fields.',
            ],
            'participation_add_approved' => [
                'title' => 'Участие в турнире утверждено',
                'body' => 'Ваше изменение информации о подиуме для :tournament была одобрена.',
            ],
            'participation_add_denied' => [
                'title' => 'Участие в турнире отказано',
                'body' => 'Ваше изменение информации о подиуме для :tournament была отклонена.',
            ],
            'teammate_osu_id_failed' => [
                'title' => 'Тиммейт не может быть добавлен',
                'body' => 'Не удалось добавить osu! ID :ids для :tournament.',
            ],
            'deletion_approved' => [
                'title' => 'Запрос на удаление одобрен',
                'body' => 'Ваш запрос на удаление для :tournament был одобрен.',
            ],
            'deletion_denied' => [
                'title' => 'Запрос на удаление отклонен',
                'body' => 'Ваш запрос на удаление для :tournament был отклонен.',
            ],
            'eligible_registration_open' => [
                'title' => 'Регистрация открыта',
                'body' => ':tournament открыт для регистрации и совпадает с вашим основным режимом.',
            ],
            'eligible_registration_closing_24h' => [
                'title' => 'Регистрация скоро будет закрыта',
                'body' => 'Регистрация на :tournament заканчивается в течение 24 часов.',
            ],
        ],
    ],
    'footer' => [
        'contact' => 'Связаться',
        'discord' => 'Discord-сервер',
        'donate' => 'Поддержать',
        'contribute' => 'Внести свой вклад',
        'copyright' => '@ 2026 osu! Korean Tourney Hub',
    ],
];

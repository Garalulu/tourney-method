<?php

return [
    'title' => 'Configuración',
    'discord_webhook' => [
        'title' => 'Webhook de Discord',
        'description' => 'Recibe notificaciones de torneos directamente en tu servidor de Discord o vía mensaje directo.',
        'webhook_url' => 'URL del Webhook',
        'valid' => 'Válido',
        'invalid' => 'Inválido',
        'how_to' => 'Cómo crear un webhook de Discord',
        'how_to_url' => 'https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks',
        'test_button' => 'Enviar mensaje de prueba',
        'test_sending' => 'Enviado...',
        'test_success' => '¡Mensaje de prueba enviado! Revisa tu Discord.',
        'test_error' => 'Error al enviar mensaje de prueba.',
    ],
    'game_mode' => [
        'title' => 'Modo de juego principal',
        'description' => 'Tu modo de juego principal para el tablero.',
    ],
    'in_app_notifications' => [
        'title' => 'Preferencias de notificaciones',
        'description' => 'Elige que notificaciones en la app quieres recibir.',
        'categories' => [
            'participation_records' => 'Registros de participación',
            'new_tournament_alert' => 'Nuevas alertas de torneos',
        ],
        'events' => [
            'teammate_changes' => [
                'title' => 'Cambios de compañeros de equipo',
                'description' => 'Cuando otro usuario te agrega o elimina como compañero de equipo en los registros de participación.',
            ],
            'participation_updates' => [
                'title' => 'Detalles de la participación cambiados',
                'description' => 'Cuando otro usuario cambia el nombre del equipo, la etapa final o clasificación por un registro en el que participaste.',
            ],
            'deletion_requests' => [
                'title' => 'Resultados de la solicitud de eliminación',
                'description' => 'Cuando su solicitud de eliminación de participación es aprobada o denegada.',
            ],
            'eligible_registration_open' => [
                'title' => 'Registros abiertos elegibles para usted',
                'description' => 'Cuando un torneo elegible para su modo principal abre registros.',
            ],
            'eligible_registration_closing_24h' => [
                'title' => 'Los registros abiertos elegibles para usted cerrarán pronto',
                'description' => 'Cuando un torneo elegible para su modo principal cierra registros dentro de 24 horas.',
            ],
        ],
    ],
    'save_button' => 'Guardar configuración',
    'saving' => 'Guardando...',
    'saved' => 'Configuración guardada con éxito!',
    'save_error' => 'Error al guardar la configuración. Por favor, inténtelo nuevamente.',
];

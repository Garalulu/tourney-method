<?php

return [
    'changelog' => [
        'eyebrow' => 'Lo que cambió',
        'dont_show_again' => 'No mostrar esta actualización nuevamente',
        'ok' => 'Entendido',
        'close' => 'Cerrar registro de cambios',
    ],
    'errors' => [
        404 => [
            'heading' => 'Página no encontrada',
            'message' => 'La página que estás buscando no existe o ha sido removida.',
            'go_home' => 'Ir a Inicio',
            'go_back' => 'Volver atrás',
        ],
        401 => [
            'heading' => 'Autentificación requerida',
        ],
        403 => [
            'heading' => 'Acceso prohibido',
        ],
        500 => [
            'heading' => 'Algo ha salido mal',
        ],
    ],
    'nav' => [
        'tournaments' => 'Torneos',
        'dashboard' => 'Panel de control',
        'admin' => 'Administrador',
        'profile' => 'Perfil',
        'settings' => 'Configuración',
        'device_theme' => 'Device theme',
        'use_device_theme' => 'Use Device theme',
        'dark_theme' => 'Dark theme',
        'light_theme' => 'Light theme',
        'search' => 'Buscar',
        'logout' => 'Cerrar sesión',
        'login_with_osu' => 'Iniciar sesión con osu!',
    ],
    'actions' => [
        'reload' => 'Inténtalo de nuevo',
    ],
    'messages' => [
        'language_updated' => 'Idioma actualizado correctamente.',
    ],
    'notifications' => [
        'title' => 'Notificaciones',
        'read_all' => 'Leer todas las notificaciones',
        'remove_read' => 'Remove all read notifications',
        'loading' => 'Cargando notificaciones...',
        'empty' => 'Aún no hay notificaciones.',
        'fields' => [
            'team_name' => 'nombre del equipo',
            'stage_value' => 'etapa final',
            'seed' => 'seed',
        ],
        'messages' => [
            'teammate_added' => [
                'title' => 'Agregado como compañero de equipo',
                'body' => ':actor te ha añadido como compañero de equipo para :tournament.',
            ],
            'teammate_removed' => [
                'title' => 'Eliminado como compañero de equipo',
                'body' => ':actor te eliminó como compañero de equipo de :tournament.',
            ],
            'participation_updated' => [
                'title' => 'Participación actualizada',
                'body' => ':actor actualizó :fields para :tournament.',
            ],
            'participation_add_approved' => [
                'title' => 'Participación aprobada',
                'body' => 'Tu solicitud para participar en el podio de :tournament ha sido aprobada.',
            ],
            'participation_add_denied' => [
                'title' => 'Participación denegada',
                'body' => 'Tu solicitud para participar en el podio de :tournament ha sido denegada.',
            ],
            'teammate_osu_id_failed' => [
                'title' => 'No se pudo agregar compañero',
                'body' => 'Error al agregar osu! ID :ids para :tournament.',
            ],
            'deletion_approved' => [
                'title' => 'Solicitud de eliminación aprobada',
                'body' => 'Tu solicitud de eliminación para :tournament ha sido aprobada.',
            ],
            'deletion_denied' => [
                'title' => 'Solicitud de eliminación rechazada',
                'body' => 'Tu solicitud de eliminación para :tournament ha sido denegada.',
            ],
            'eligible_registration_open' => [
                'title' => 'Los registros están abiertos',
                'body' => 'Los registros de :tournament están abiertos y coincide con tu modo principal.',
            ],
            'eligible_registration_closing_24h' => [
                'title' => 'Registros cierran pronto',
                'body' => 'Los registros de :tournament terminan en 24 horas.',
            ],
        ],
    ],
    'footer' => [
        'contact' => 'Contacto',
        'discord' => 'Servidor de Discord',
        'donate' => 'Donar',
        'contribute' => 'Contribuir',
        'copyright' => '@ 2026 osu! Korean Tourney Hub',
    ],
];

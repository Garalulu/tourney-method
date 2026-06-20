<?php

return [
    'hero' => [
        'title' => 'Tourney Method',
        'subtitle' => 'Descubre torneos de osu!, sigue las participaciones y ayuda a mejorar los datos de los torneos',
    ],
    'guest_actions' => [
        'browse_tournaments' => 'Ver Torneos',
        'browse_tournaments_desc' => 'Explora torneos desde los foros de osu!',
        'login_to_search' => 'Inicia sesión para Buscar y Filtrar',
        'login_to_search_desc' => 'Buscar usuarios y comprobar torneos elegibles',
    ],
    'auth_actions' => [
        'browse_tournaments' => 'Ver Torneos',
        'browse_tournaments_desc' => 'Explorar y filtrar torneos',
        'already_logged_in' => 'Ya estás conectado',
        'already_logged_in_desc' => 'Utilizar la búsqueda y los filtros en la navegación',
    ],
    'sections' => [
        'tournament_discovery' => [
            'title' => 'Descubrimiento de Torneos',
            'content' => 'La lista de torneos está automáticamente actualizada desde las fuentes de torneos de osu!. Filtra por modo de juego, estado de registros, requerimientos de rangos, región y formato para encontrar torneos en los que puedas participar.',
        ],
        'tournament_history' => [
            'title' => 'Historial de torneos',
            'content' => 'Cada página de torneo muestra fechas, elegibilidad, enlaces, staff, resultados de podio y correcciones de la comunidad. Si algo está mal, desplácese hasta la parte inferior y use Corregir Torneo.',
        ],
        'user_profiles' => [
            'title' => 'Perfiles de Usuario',
            'content' => 'Tu perfil de usuario mantiene registros de participación personal, podios, compañeros de equipo, historial de personal, insignias e historial de contribuciones en un solo lugar.',
        ],
        'how_to_use' => [
            'title' => 'Cómo usar Tourney Method',
            'items' => [
                [
                    'title' => 'Encuentra torneos',
                    'body' => 'Utilice filtros de torneo y comandos de búsqueda para comparar el estado de registros, rangos de rango, modos, regiones y formatos.',
                ],
                [
                    'title' => 'Ver registro',
                    'body' => 'Cuando veas torneos, las notificaciones en la aplicación pueden avisarte cuando el registro se abra o cierre dentro de 24 horas.',
                ],
                [
                    'title' => 'Sigue tu historial',
                    'body' => 'Abre tu perfil y añade registros de participación para torneos que hayas jugado, incluyendo etapa alcanzada, clasificación, compañeros de equipo y enlaces de partida.',
                ],
                [
                    'title' => 'Mejorar los datos',
                    'body' => 'Abre una página de detalles del torneo, desplázate en Contribuciones y envía correciones para metadatos, staff, podios o enlaces.',
                ],
            ],
        ],
        'eligibility_checking' => [
            'title' => 'Comprobación de elegibilidad',
            'content' => 'Algunos torneos utilizan el sistema Badge Weighted Seeding (BWS) para determinar la elegibilidad. Este sistema admite formatos BWS estándar y personalizados, incluyendo métodos que excluyen insignias antiguas (ejemplo: las insignias ganadas antes de 2022 están excluidas del cálculo), permitiéndote ver para qué torneos eres elegible actualmente basándose en varios métodos de cálculo de BWS.',
        ],
        'data_sources' => [
            'title' => 'Fuentes de datos',
            'osu_api' => 'osu! API',
            'osu_api_desc' => 'Autenticación de usuarios, datos de perfil y datos del torneo',
            'tcomm' => 'tcomm API',
            'tcomm_desc' => 'Datos de torneos con insignia (badged)',
            'otr' => 'Conjunto de datos públicos de o!TR',
            'otr_desc' => 'Datos de torneos',
            'community' => 'Comunidad',
            'community_desc' => 'Correcciones, traducciones y registros de participación',
        ],
    ],
    'stats' => [
        'active_players' => 'Jugadores activos',
        'active_staff' => 'Staff activo',
        'tournaments' => 'Torneos',
    ],
];

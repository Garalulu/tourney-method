<?php

return [
    /*
    |--------------------------------------------------------------------------
    | United Nations Geoscheme Templates
    |--------------------------------------------------------------------------
    |
    | Region groupings are based on the UN M49 geoscheme hierarchy. The leaf
    | values are CLDR/ISO-style territory codes used by the rest of the app.
    | Group codes are intentionally kept as M49 strings so templates can be
    | expanded recursively without duplicating long country lists.
    |
    */

    'groups' => [
        '002' => [
            'name' => 'Africa',
            'type' => 'continent',
            'children' => ['015', '202'],
        ],
        '019' => [
            'name' => 'Americas',
            'type' => 'continent',
            'children' => ['021', '419'],
        ],
        '142' => [
            'name' => 'Asia',
            'type' => 'continent',
            'children' => ['143', '030', '035', '034', '145'],
        ],
        '150' => [
            'name' => 'Europe',
            'type' => 'continent',
            'children' => ['151', '154', '039', '155'],
        ],
        '009' => [
            'name' => 'Oceania',
            'type' => 'continent',
            'children' => ['053', '054', '057', '061'],
        ],
        '010' => [
            'name' => 'Antarctica',
            'type' => 'continent',
            'children' => ['AQ'],
        ],
        '202' => [
            'name' => 'Sub-Saharan Africa',
            'type' => 'intermediary',
            'children' => ['011', '017', '014', '018'],
        ],
        '419' => [
            'name' => 'Latin America and the Caribbean',
            'type' => 'intermediary',
            'children' => ['013', '029', '005'],
        ],
        '015' => [
            'name' => 'Northern Africa',
            'type' => 'subregion',
            'children' => ['DZ', 'EG', 'EH', 'LY', 'MA', 'SD', 'TN', 'EA', 'IC'],
        ],
        '011' => [
            'name' => 'Western Africa',
            'type' => 'subregion',
            'children' => ['BF', 'BJ', 'CI', 'CV', 'GH', 'GM', 'GN', 'GW', 'LR', 'ML', 'MR', 'NE', 'NG', 'SH', 'SL', 'SN', 'TG'],
        ],
        '017' => [
            'name' => 'Middle Africa',
            'type' => 'subregion',
            'children' => ['AO', 'CD', 'CF', 'CG', 'CM', 'GA', 'GQ', 'ST', 'TD'],
        ],
        '014' => [
            'name' => 'Eastern Africa',
            'type' => 'subregion',
            'children' => ['BI', 'DJ', 'ER', 'ET', 'IO', 'KE', 'KM', 'MG', 'MU', 'MW', 'MZ', 'RE', 'RW', 'SC', 'SO', 'SS', 'TF', 'TZ', 'UG', 'YT', 'ZM', 'ZW'],
        ],
        '018' => [
            'name' => 'Southern Africa',
            'type' => 'subregion',
            'children' => ['BW', 'LS', 'NA', 'SZ', 'ZA'],
        ],
        '021' => [
            'name' => 'Northern America',
            'type' => 'subregion',
            'children' => ['BM', 'CA', 'GL', 'PM', 'US'],
        ],
        '013' => [
            'name' => 'Central America',
            'type' => 'subregion',
            'children' => ['BZ', 'CR', 'GT', 'HN', 'MX', 'NI', 'PA', 'SV'],
        ],
        '029' => [
            'name' => 'Caribbean',
            'type' => 'subregion',
            'children' => ['AG', 'AI', 'AW', 'BB', 'BL', 'BQ', 'BS', 'CU', 'CW', 'DM', 'DO', 'GD', 'GP', 'HT', 'JM', 'KN', 'KY', 'LC', 'MF', 'MQ', 'MS', 'PR', 'SX', 'TC', 'TT', 'VC', 'VG', 'VI'],
        ],
        '005' => [
            'name' => 'South America',
            'type' => 'subregion',
            'children' => ['AR', 'BO', 'BR', 'BV', 'CL', 'CO', 'EC', 'FK', 'GF', 'GS', 'GY', 'PE', 'PY', 'SR', 'UY', 'VE'],
        ],
        '143' => [
            'name' => 'Central Asia',
            'type' => 'subregion',
            'children' => ['TM', 'TJ', 'KG', 'KZ', 'UZ'],
        ],
        '030' => [
            'name' => 'Eastern Asia',
            'type' => 'subregion',
            'children' => ['CN', 'HK', 'JP', 'KP', 'KR', 'MN', 'MO', 'TW'],
        ],
        '035' => [
            'name' => 'South-eastern Asia',
            'type' => 'subregion',
            'children' => ['BN', 'ID', 'KH', 'LA', 'MM', 'MY', 'PH', 'SG', 'TH', 'TL', 'VN'],
        ],
        '034' => [
            'name' => 'Southern Asia',
            'type' => 'subregion',
            'children' => ['AF', 'BD', 'BT', 'IN', 'IR', 'LK', 'MV', 'NP', 'PK'],
        ],
        '145' => [
            'name' => 'Western Asia',
            'type' => 'subregion',
            'children' => ['AE', 'AM', 'AZ', 'BH', 'CY', 'GE', 'IL', 'IQ', 'JO', 'KW', 'LB', 'OM', 'PS', 'QA', 'SA', 'SY', 'TR', 'YE'],
        ],
        '151' => [
            'name' => 'Eastern Europe',
            'type' => 'subregion',
            'children' => ['BG', 'BY', 'CZ', 'HU', 'MD', 'PL', 'RO', 'RU', 'SK', 'UA'],
        ],
        '154' => [
            'name' => 'Northern Europe',
            'type' => 'subregion',
            'children' => ['GG', 'IM', 'JE', 'AX', 'DK', 'EE', 'FI', 'FO', 'GB', 'IE', 'IS', 'LT', 'LV', 'NO', 'SE', 'SJ'],
        ],
        '039' => [
            'name' => 'Southern Europe',
            'type' => 'subregion',
            'children' => ['AD', 'AL', 'BA', 'ES', 'GI', 'GR', 'HR', 'IT', 'ME', 'MK', 'MT', 'RS', 'PT', 'SI', 'SM', 'VA', 'XK'],
        ],
        '155' => [
            'name' => 'Western Europe',
            'type' => 'subregion',
            'children' => ['AT', 'BE', 'CH', 'DE', 'FR', 'LI', 'LU', 'MC', 'NL'],
        ],
        '053' => [
            'name' => 'Australia and New Zealand',
            'type' => 'subregion',
            'children' => ['AU', 'CC', 'CX', 'HM', 'NF', 'NZ'],
        ],
        '054' => [
            'name' => 'Melanesia',
            'type' => 'subregion',
            'children' => ['FJ', 'NC', 'PG', 'SB', 'VU'],
        ],
        '057' => [
            'name' => 'Micronesia',
            'type' => 'subregion',
            'children' => ['FM', 'GU', 'KI', 'MH', 'MP', 'NR', 'PW', 'UM'],
        ],
        '061' => [
            'name' => 'Polynesia',
            'type' => 'subregion',
            'children' => ['AS', 'CK', 'NU', 'PF', 'PN', 'TK', 'TO', 'TV', 'WF', 'WS'],
        ],
    ],

    'custom_templates' => [
        'custom-russian-speaking' => [
            'name' => 'Russian Speaking',
            'type' => 'custom',
            'children' => ['AM', 'AZ', 'BY', 'EE', 'GE', 'KZ', 'KG', 'LV', 'LT', 'MD', 'RU', 'TJ', 'UA', 'UZ'],
        ],
        'custom-ibero-american' => [
            'name' => 'Ibero-American',
            'type' => 'custom',
            'children' => ['AD', 'AR', 'BO', 'BR', 'CL', 'CO', 'CR', 'EC', 'SV', 'GT', 'HN', 'MX', 'NI', 'PA', 'PY', 'PE', 'PT', 'ES', 'UY', 'VE'],
        ],
        'custom-balkan' => [
            'name' => 'Balkan',
            'type' => 'custom',
            'children' => ['AL', 'BA', 'BG', 'HR', 'GR', 'XK', 'ME', 'MK', 'RO', 'RS', 'SI'],
        ],
        'custom-chinese-speaking' => [
            'name' => 'Chinese Speaking',
            'type' => 'custom',
            'children' => ['CN', 'HK', 'MO', 'TW'],
        ],
        'custom-asia-pacific' => [
            'name' => 'Asia-Pacific',
            'type' => 'custom',
            'children' => ['AU', 'BN', 'CN', 'HK', 'ID', 'JP', 'KH', 'KR', 'LA', 'MM', 'MO', 'MY', 'NZ', 'PH', 'SG', 'TH', 'TL', 'TW', 'VN'],
        ],
        'custom-nordic' => [
            'name' => 'Nordic',
            'type' => 'custom',
            'children' => ['DK', 'FI', 'IS', 'NO', 'SE'],
        ],
        'custom-dach' => [
            'name' => 'DACH',
            'type' => 'custom',
            'children' => ['AT', 'CH', 'DE'],
        ],
        'custom-benelux' => [
            'name' => 'Benelux',
            'type' => 'custom',
            'children' => ['BE', 'LU', 'NL'],
        ],
        'custom-central-europe' => [
            'name' => 'Central Europe',
            'type' => 'custom',
            'children' => ['AT', 'CH', 'CZ', 'DE', 'HU', 'PL', 'SK', 'SI'],
        ],
        'custom-western-europe' => [
            'name' => 'Western Europe',
            'type' => 'custom',
            'children' => ['AT', 'BE', 'CH', 'DE', 'FR', 'IE', 'LU', 'MC', 'NL', 'GB'],
        ],
    ],
];

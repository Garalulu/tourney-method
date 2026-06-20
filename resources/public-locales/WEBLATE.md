# Hosted Weblate configuration

Apply for the Hosted Weblate **Libre** plan for the public repository:

`https://github.com/Garalulu/tourney-method-locales`

The project and all components must use the MIT License. Do not enable paid
machine translation services or any feature requiring billing.

Create one component for each filename:

| Component | File mask | Base language file |
| --- | --- | --- |
| Authentication | `lang/*/auth.php` | `lang/en/auth.php` |
| Common | `lang/*/common.php` | `lang/en/common.php` |
| Contribute | `lang/*/contribute.php` | `lang/en/contribute.php` |
| Dashboard | `lang/*/dashboard.php` | `lang/en/dashboard.php` |
| Home | `lang/*/home.php` | `lang/en/home.php` |
| Pagination | `lang/*/pagination.php` | `lang/en/pagination.php` |
| Search | `lang/*/search.php` | `lang/en/search.php` |
| Settings | `lang/*/settings.php` | `lang/en/settings.php` |
| Tournaments | `lang/*/tournaments.php` | `lang/en/tournaments.php` |
| Users | `lang/*/users.php` | `lang/en/users.php` |
| Validation | `lang/*/validation.php` | `lang/en/validation.php` |

Use these settings for every component:

- File format: Laravel PHP strings
- Source language: English
- Template for new translations: the matching `lang/en/<file>.php`
- Language code style: POSIX-style codes compatible with the existing folders
- Repository branch: `main`
- Push branch: `main`
- Push on commit: enabled
- Translation license: MIT

Grant Weblate write access only to this public locale repository. It must never
receive access to the private application repository.

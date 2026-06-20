# tourney-method locales

Public, community-maintained translations for tourney-method.

The application source code is private. This repository intentionally contains
only approved public-facing interface text. Administrative, operational, and
unreleased strings are not published here.

## Languages

- English (`en`) — source language
- Korean (`ko`)
- Russian (`ru`)
- Simplified Chinese (`zh-Hans`)
- Traditional Chinese (`zh-Hant`)
and many more!

## Contributing

The preferred translation interface is Hosted Weblate. Until the Hosted
Weblate Libre application is approved, pull requests are also welcome.

Please preserve Laravel placeholders such as `:name`, `:count`, and `:id`
exactly. Run the validator before opening a pull request:

```bash
php tools/validate-locales.php
```

Translations contributed here are licensed under the MIT License. Contributors
are credited through Git history and Weblate contribution history.

## Repository policy

- Only files and keys listed in `config/public-locales.php` are accepted.
- Do not add credentials, private URLs, internal routes, operational notes, or
  unreleased feature text.
- English is the canonical source language.
- Translation changes are reviewed and synchronized into the private
  application repository by automation.

See [WEBLATE.md](WEBLATE.md) for the component configuration.

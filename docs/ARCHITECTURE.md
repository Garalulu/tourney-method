# Architecture

Tourney Method is a Laravel 12 application with Blade and Livewire interfaces,
PostgreSQL persistence, Redis-backed queues, and integrations with tournament
and community services.

## Request flow

```text
Route
  -> middleware and form request
  -> controller or Livewire component
  -> domain/service boundary
  -> Eloquent model or queued job
  -> view, redirect, or JSON response
```

## Main areas

- `app/Http` owns transport concerns: authorization, validation, requests, and
  responses.
- `app/Domain` contains reusable tournament and participation behavior.
- `app/Integrations` contains provider-specific translation and protocol code.
- `app/Services` contains established application entrypoints and orchestration.
- `app/Jobs` performs asynchronous parsing, imports, synchronization, and
  notifications.
- `app/Models` defines persistence and relationships.
- `app/Support` contains small shared primitives without domain ownership.
- `resources/views` and `app/Livewire` implement the user interface.

`ForumParser` and `OsuApiService` remain stable facades while their internals are
incrementally extracted. New code should prefer existing domain boundaries and
avoid adding unrelated responsibilities to these facades.

## Data and compatibility

Migrations are the source of truth for the database schema. Tests and factories
should construct data through public model behavior. Changes should preserve
route names, JSON fields, queue payloads, and model relationships unless the
project intentionally schedules a breaking release.

## External calls

Provider calls belong behind integration or service boundaries. Tests must fake
all network requests and must not depend on live credentials or provider state.

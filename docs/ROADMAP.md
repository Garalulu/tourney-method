# Refactoring Roadmap

The public release uses staged, behavior-preserving refactoring. The following
work is intentionally split into reviewable milestones:

1. Move parsing sections from `ForumParser` into focused date, link, format, and
   staff parsers behind the existing service entrypoint.
2. Move tournament correction comparison, presentation, staff application, and
   podium application into `app/Domain/Tournaments/Corrections`.
3. Move podium grouping and synchronization into
   `app/Domain/Tournaments/Podium`.
4. Move the remaining participation shared-record graph operations into
   `app/Domain/Participation`.
5. Split external HTTP clients into `app/Integrations` while retaining existing
   service facades for backward compatibility.
6. Break oversized Blade templates and Pest files into feature-focused units.

Each milestone must preserve routes, JSON shapes, database schema, queued
payloads, and existing service entrypoints unless documented as a breaking
release.

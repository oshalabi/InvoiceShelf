# ADR 0001: Laravel-native modular monolith

Date: 2026-08-05

Status: Accepted

## Context

InvoiceShelf 3.x is still in alpha and may break PHP namespaces and internal
extension contracts. Existing installations must retain their database data,
document links, and public `/api/v1` behavior. The current layer-first layout
mixes sales, receivables, purchases, reporting, and infrastructure in global
model, controller, and service directories.

## Decision

The backend is organized as a modular monolith under `app/Domains` and
`app/Platform`.

Business contexts are Accounts, Contacts, Catalog, Taxation, Money, Metadata,
Sales, Receivables, Purchases, and Reporting. Platform capabilities are
Modules, AI, Mail, PDF, Storage, and Operations.

Each context owns its models, actions, queries, policies, events, jobs, and HTTP
adapters. Large contexts may contain feature subdirectories. Every context is
registered explicitly through a service provider; root route files are
composition roots for context-owned route fragments.

Direct Eloquent relationships across contexts are permitted for read
navigation when declared by the architecture dependency map. Cross-context
writes and workflows use contracts and application actions. Reporting may
query shared tables through read-only query objects. Infrastructure is reached
through platform contracts. No repository abstraction is required around
Eloquent by default.

All first-party models use stable morph aliases. Model namespaces, Hashids
connection names, and public API discriminators are separate identities. The
database stores aliases, Hashids retain their historical salt inputs, and v1
resources retain their existing discriminator strings.

Modules depend on the versioned `invoiceshelf/modules` SDK and must not import
domain internals.

## Migration rules

- Contexts move in independently green pull requests.
- A moved class has one canonical namespace; class aliases are forbidden.
- Temporary adapters may connect migrated and unmigrated contexts, but must be
  deleted when the counterpart context moves.
- Existing table and column names remain unchanged and moved models declare
  their table explicitly.
- HTTP methods, paths, middleware, payloads, status codes, and OpenAPI schemas
  remain stable.
- Queue workers are drained and restarted for the namespace cutover; serialized
  legacy jobs are not supported.

## Consequences

Feature ownership and cross-domain writes become explicit, while Eloquent
remains usable without a framework-independent persistence layer. Namespace
changes require coordinated imports across application code, factories,
seeders, configuration, tests, and historical migrations, but no persisted
record depends on those namespaces after the alias migration.

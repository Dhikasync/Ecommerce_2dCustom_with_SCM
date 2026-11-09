# Nichibag — Ecommerce_2dCustom_with_SCM

Final thesis project: web marketplace for Toko Nichibag with supply-chain
integration and 2D custom design. Laravel (PHP 8.5), PostgreSQL (nichibag_db).

## Source of truth

- SRS v1.0 and User Story v1.1.4 are the only source of truth. They live in
  the `nichibag-docs` skill (`references/srs.md`, `references/user-story.md`).
  Read them first.
- Never invent a table, field, status, or rule from examples or other
  projects. If a rule is not in the documents, ask.
- Documents are in Indonesian. Business field names stay Indonesian; foreign
  keys use Laravel convention: singular English model + `_id`
  (e.g. `category_id`, `product_id`, `variant_id`, `user_id`).

## How to work with me

- One micro-step at a time. Do one step, then wait for my "next" before
  continuing.
- Explain file by file, line by line. No combined theory-plus-code drills.
  No syntax to memorise — explain by job/pattern.
- I am learning backend (route to DevOps, then AI infra). Explain what each
  line does and why it is there.
- Never present a partial set as complete. Check completeness against the
  documents before saying something is done.

## Rules that must not be broken

- Payment by gateway only. No manual payment, no "Menunggu Verifikasi" status.
- No Super Admin. No in-app admin account management. Admin accounts are
  provisioned at setup, outside the app.
- Dashboard is admin-only (Admin Ecommerce / Admin SCM, separate). Users
  never see a dashboard.
- Auth is manual — no Breeze/Fortify/Jetstream. Register/login written by
  hand. On register: role is fixed to `'user'` (ignore any role in the
  request), password is passed plain so the model's `hashed` cast hashes it
  once (`Hash::make` would not double-hash in Laravel 13 because the cast
  checks `Hash::isHashed()`, but keep hashing in one place), regenerate the
  session after `Auth::login`, throttle auth routes.
- Stock is per variant/SKU only: `stok_fisik`, `stok_direservasi`,
  `Tersedia = Fisik - Direservasi` (calculated, never stored). Checkout
  reserves stock; Lunas drops Fisik; cancel before Lunas releases the
  reservation.
- Kategori deletion is `restrict` — admin must move products first.
- Timestamps on every table. Laporan keuangan is a report only, not a table.
- Guests can browse and customise, but must register/login at checkout.

## Git

- Build locally first, push to `main` when a step is verified. Never force
  push. Never commit `.env` or `vendor/`.

## Skills to use

- `/nichibag-docs` for all thesis rules and document lookups.
- `/spec-driven-development` or `/incremental-implementation` for planning
  and building.
- `/test-driven-development` plus the `test-engineer` agent for tests.

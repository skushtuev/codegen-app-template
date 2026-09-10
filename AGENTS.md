# AGENTS.md

Shared, **agent-agnostic** rules for this repository and the single source of truth for every
coding agent (Claude, Cursor, Copilot, …). Tool-specific entry points such as `CLAUDE.md` only
point here — all rules live in this file.

## Project

An LLM-powered **code-generation application**: it turns natural-language input into code
according to its own ruleset. This repository is the template/skeleton — a monorepo with an
admin frontend and a backend application (more APIs to follow).

## First run (configuring a new project from this template)

On your **first** interaction in a freshly-cloned copy of this template, proactively offer to set it
up — ask first, don't assume defaults, and let the user skip any step:

1. **Domain** — ask which domain to use, then replace the `app.test` placeholder in every place
   listed under [Domains](#domains) and add the hosts to `/etc/hosts` → `127.0.0.1`.
2. **Graphify** — offer to install the code knowledge graph (`pip install graphifyy && graphify install`,
   then `/graphify .`); see [Code search](#code-search--graphify-first).
3. **Env files** — `cp .env.example .env` in `admin-frontend/` and `backend/`, then set a real
   `ADMIN_AUTH_JWT_SECRET` in `backend/.env`.

## Working in this repo

Monorepo with per-app rules. Each app has its own entry point (`CLAUDE.md`, which points to that
app's `AGENTS.md`) with stack-specific rules and commands. **Before editing an app, open its
local docs first.**

| Area           | Path              | Stack                                                                   | Local entry                                          |
| -------------- | ----------------- | ----------------------------------------------------------------------- | ---------------------------------------------------- |
| Admin frontend | `admin-frontend/` | Next.js 16, React 19, TypeScript, Mantine v9, Redux Toolkit, next-intl  | [admin-frontend/CLAUDE.md](admin-frontend/CLAUDE.md) |
| Backend        | `backend/`        | Yii3, PHP 8.2+ (contains `admin-api`; more APIs later)                   | [backend/CLAUDE.md](backend/CLAUDE.md)               |

Local infra lives in [docker-compose.yml](docker-compose.yml): traefik, postgres, redis, rabbitmq,
**Ory Kratos** (end-user identity, config in [kratos/](kratos/)) and **Mailpit** (dev mail catcher).

## Code search — Graphify first

Prefer querying the **Graphify** knowledge graph for code navigation and "where / how is X"
questions; fall back to grep / file search only when the graph can't answer.

- Install once per machine: `pip install graphifyy && graphify install`
- Build / refresh the graph: run `/graphify .` in Claude Code → output in `graphify-out/`
  (gitignored — regenerate locally)
- Requires Python 3.10+

## Conventions

- **Commits:** [Conventional Commits](https://www.conventionalcommits.org) — `type(scope): subject`
  (`feat`, `fix`, `chore`, `refactor`, `docs`, `test`, …). Scope = the area: `admin-api`,
  `admin-frontend`, `common`, `console`. Subject text may be EN or RU.
- **Required checks (before merging to `main`):** frontend `npm run lint` (in `admin-frontend/`) **and**
  backend `make phpstan` (in `backend/`) — both must pass.
- **Branching:** none for now — commit directly to `main`. (Revisit when the team grows.)

## Domains

The template ships with a default local domain **`app.test`** and these subdomains (add them to
`/etc/hosts` → `127.0.0.1` for local dev):

| Host                 | Used by                          | Defined in                                                                                          |
| -------------------- | -------------------------------- | -------------------------------------------------------------------------------------------------- |
| `admin.app.test`     | admin frontend (UI)              | `admin-frontend/package.json` (`dev --hostname`), `docker-compose.yml` (CORS allow-origin)         |
| `admin-api.app.test` | admin API (backend)              | `admin-frontend/.env.example` (`API_URL`), `backend/docker/nginx/admin-api.conf` (`server_name`), `docker-compose.yml` (`Host(...)`) |
| `cdn.app.test`       | optional uploads CDN (commented) | `backend/.env.example` (`PUBLIC_UPLOADS_BASE_URL`)                                                  |
| `id.app.test`        | Ory Kratos **public** API        | `kratos/kratos.yml` (`serve.public.base_url`), `docker-compose.yml` (`Host(...)`)                   |
| `app.app.test`       | end-user frontend (planned)      | `kratos/kratos.yml` (all `ui_url` / return URLs, CORS allow-origin)                                  |
| `mail.app.test`      | Mailpit UI (dev only)            | `docker-compose.yml` (`Host(...)`)                                                                  |

The Kratos **admin** API (port 4434) is deliberately not routed through traefik — it has no
authentication of its own. Reach it only from inside the `app` docker network (`http://kratos:4434`).

**Rule — `app.test` is only a placeholder so the template runs out of the box. Before configuring a
new project from this template, ASK the user which domain they want**, then replace `app.test` with
it in every place above (and update `/etc/hosts`). Never assume the user keeps `app.test`.

## Hard rules

- Never commit secrets or `.env` files — only `.env.example`.
- Never commit generated artifacts: `graphify-out/`, `node_modules/`, `vendor/`, `.next/`, `runtime/`.

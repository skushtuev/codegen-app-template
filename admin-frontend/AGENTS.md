# AGENTS.md — admin-frontend

Rules for the admin UI. Repo-wide shared rules: **[../AGENTS.md](../AGENTS.md)** (read that first).
Architecture is governed by the bundled skill, which is the authority:
**[skills/next-frontend-architecture/SKILL.md](skills/next-frontend-architecture/SKILL.md)** (+ `references/`).

## Framework

Next.js 16 (App Router + Turbopack) · React 19 · TypeScript. `src/app` is the composition layer only
(routes, layouts, providers, metadata); business code lives in `src/modules/<module>`. Real modules:
`auth`, `dashboard`, `shared`.

## Stack — what we use and where it comes from

| Concern               | Source / library                                | Where in the code                                                                 |
| --------------------- | ----------------------------------------------- | -------------------------------------------------------------------------------- |
| UI components         | **Mantine v9** (`@mantine/core`)               | primary UI kit — every component is a thin wrapper over a Mantine primitive       |
| Forms                 | **`@mantine/form`** (`useForm`)                | `validate` + `transformValues`; **not** React Hook Form / Zod                     |
| Validation messages   | **next-intl** (`Shared.Validation`)            | returned from per-field validators; only helper is `isValidEmail` in `shared/helpers/validation.ts` |
| API error shape       | `IApiErrorResponse { errors?: Record<string,string> }` | `shared/models/api-error-response.interface.ts` — flat `field → message` map |
| Form ⇄ API errors     | `useApiFormErrors(form, error)`                | `shared/hooks/use-api-form-errors.ts` — maps `errors` to fields, unknown → `root` (rendered by `FormRootError`) |
| Icons                 | **`lucide-react`** (primary)                   | `size`+`strokeWidth`, `LucideIcon` type; SVG-as-component is a fallback via `src/types/svg.d.ts` |
| i18n                  | **`next-intl` v4**                             | single locale via `APP_LOCALE`; per-module `i18n.ts` (`{ ru, en } as const`) + `shared/i18n/{messages,shared-messages,request}.ts` |
| State + data fetching | **RTK Query only** (`@reduxjs/toolkit`, `react-redux`) | **no slices**; per-resource `store/<domain>-api.ts`; root store `shared/store/store.ts`; base query `shared/helpers/rtk-query.ts` (cookie auth, 500-only toast) |
| Store hooks           | `useAppDispatch` / `useAppSelector`            | `shared/hooks/use-app-*.ts`                                                        |
| Notifications / toast | **`@mantine/notifications`**                   | imperative `notifications.show({color,title,message})`; single outlet in `shared/provider.tsx` (no redux state) |
| Route progress        | **`@mantine/nprogress`**                       | `shared/components/route-progress`                                                |
| File upload           | **`@mantine/dropzone`**                        | `dashboard/.../create-file-modal` (wired to `@mantine/form`)                      |
| Utility hooks         | **`@mantine/hooks`** (+ `shared/hooks/*`)      | `use-copy`, `use-download`, …                                                      |
| Theme                 | Mantine `createTheme` + `defaultProps`         | `shared/theme.ts`, applied via `MantineProvider` in `shared/provider.tsx`         |
| Styling               | **SCSS (`sass`) modules + BEM** + Mantine theme | co-located `styles.module.scss` (start with `@use "~styles/app.scss" as *;`); globals/vars/mixins in `src/styles/` |

## Theme & defaults

`shared/theme.ts` = `createTheme({ primaryColor: 'indigo', defaultRadius: 'sm', components: {...} })` and
sets **`defaultProps` per component** for `Button`, `TextInput`, `PasswordInput`, `Fieldset`, `Select`,
`Modal`, `Card`, `ThemeIcon`, `ActionIcon`, `Tooltip` (plus `Modal`/`Select` z-index + styles).

→ Don't repeat `radius`/`size`/`variant` at call sites — they come from the theme; override only when a
specific case needs it. Configure new global component defaults in `theme.ts`, not per call. There is
**no custom Spinner** — use Mantine `Loader type="bars"`, `Skeleton`, or `Button loading`.

## Common components — three levels

A component is shared **by meaning**, at the lowest level that owns it; all are thin wrappers over Mantine.

- **App infrastructure** — `modules/shared/components/`: `ConfirmModal`, `CopyInput`, `EmailInput`,
  `PasswordInput`, `FormRootError`, `RouteProgress`.
- **Module-wide** — `modules/<module>/components/` (e.g. `dashboard/components/`): `DashboardTable`,
  `DashboardPageHeader`, `DashboardCreateButton`, `MediaLibraryFileSelect`.
- **Feature-local** — `features/<feature>/components/`: each feature owns its own shared pieces, e.g.
  - `layout` → the dashboard shell `DashboardLayout` with `menu` (+`items.ts`), `mobile-menu`,
    `user-menu`, `user-settings-modal`;
  - `admin-users` → `role-badge`, `status-badge`, `role-select`, `ban-confirm`, `change-password`,
    `change-role`, `create`, `list`;
  - `media-library` → `file-card`, `folder-card`, `folder-picker`(+modal), `create/edit-file/folder-modal`;
  - `settings` → `password-form`, `profile-form`.

Promote a component up a level only when a second consumer at that level actually needs it; extend an
existing shared component instead of duplicating one with a similar API.

## Architecture (follow the skill)

Per-module layout: `index.tsx` (public API barrel — `shared` has none, it is deep-import-only) ·
`provider.tsx` (exports `Provider`) · `i18n.ts` · `features/` · `components/` · `hooks/` · `helpers/` ·
`models/` · `store/<domain>-api.ts`.

- Cross-module imports go through `@modules/<module>` (the `index.tsx` barrel); `@modules/shared/*` is
  deep-import. Never `@/modules/*`. Enforced by ESLint rule `custom-rules/modules-imports`.
- Store: RTK Query only, **no slices**; a module may own several `*-api.ts`. `index.tsx` re-exports the
  api **objects** (`authApi`, `adminUsersApi`, …); **generated hooks are the consumption surface** and are
  imported directly (incl. cross-module). Cross-module *behavior* is exposed as semantic hooks
  (`useMustUser`, `useLogout`).
- Auth is **cookie-based**, enforced at the React layer in `auth/provider.tsx` (no 401 interceptor /
  refresh-token flow in the data layer).
- Naming: `kebab-case` files/folders, `PascalCase` components, `use-x.ts` hooks, `IName`
  interfaces (`*.interface.ts`).

Read [SKILL.md](skills/next-frontend-architecture/SKILL.md) before adding modules, features, store,
forms, styles, or components. Ready-to-use scaffolding:
[skills/next-frontend-architecture/assets/](skills/next-frontend-architecture/assets/).

## Commands

- `npm install`
- `npm run dev` — dev server on host `admin.app.test` (from the `dev` script) — add it to
  `/etc/hosts` → `127.0.0.1`
- `npm run build` / `npm run start`
- `npm run lint` — ESLint (enforces module-boundary import rules) — **run before a PR**

## Env

Whitelist vars in `next.config.ts` under `env: {}` and mirror in `.env.example` — do **not** use
`NEXT_PUBLIC_*`. Real keys: `APP_NAME`, `APP_LOCALE` (selects the active locale), `API_URL`.

## Tests

None by default — see the skill's *Verification* section for when/how to verify (min mobile width
`375px`; check no horizontal scroll, working modals/menus, visible loading/error states).

<!-- BEGIN:nextjs-agent-rules -->

# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` (resolved from this file's directory; in monorepos the `next` package may not be visible from the repo root) before writing any code. Heed deprecation notices.

This block is written and re-added by `next dev` — verify at `node_modules/next/dist/server/lib/generate-agent-files.js`. Removing it from a diff only re-creates the uncommitted change; committing it with your work keeps the tree clean.

<!-- END:nextjs-agent-rules -->

# IR4 Platform

On-premise safety command-centre. Monorepo layout:

| Path | Contents |
|---|---|
| `Server/` | Laravel + Inertia operator UI, device API, public QR pages |
| `Mobile/` | Android Flutter app (equipment QR scan / checkout / return) |
| `Docs/` | Authoritative design docs (DOC-01 … DOC-22) |

## Server (Laravel)

```bash
cd Server
composer setup
php artisan serve --host=0.0.0.0 --port=8000
# optional: npm run dev  /  php artisan reverb:start
```

Copy `Server/.env.example` → `Server/.env` if setup did not already.

## Mobile (Flutter)

```bash
cd Mobile
flutter pub get
flutter run
# or: flutter build apk --debug
```

On the login screen, base URL is the LAN address of the Server (e.g. `http://10.0.2.2:8000` for the Android emulator, or `http://<mac-lan-ip>:8000` for a physical device).

## Docs

Start with `Docs/Doc 01 base structure.md`. Conventions for agents: `.cursor/rules/ir4-conventions.mdc`.

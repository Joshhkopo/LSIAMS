# api/

The device REST API is served through the single front controller
(`public/index.php`); requests to `/api/*` are matched by `routes/api.php`
and authenticated by `App\Middleware\DeviceAuthMiddleware`.

See `docs/API.md` for the endpoint contract.

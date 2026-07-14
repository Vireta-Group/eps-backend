<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>
    <style>
        .light-mode .sidebar {
            background: linear-gradient(180deg, #fef9ef 0%, #f0f4ff 50%, #f5f0ff 100%) !important;
        }
        .dark-mode .sidebar {
            background: linear-gradient(180deg, #1a1025 0%, #0f1729 50%, #0d1b2a 100%) !important;
        }
        .light-mode .sidebar .search-input {
            background: rgba(255,255,255,0.7) !important;
            backdrop-filter: blur(8px);
        }
        .dark-mode .sidebar .search-input {
            background: rgba(255,255,255,0.05) !important;
            backdrop-filter: blur(8px);
        }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--scalar-border-color, #e2e8f0); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--scalar-color-3, #94a3b8); }
    </style>
</head>
<body>
<div id="app"></div>
<script src="{{ $config->renderer()->get('cdn', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference') }}"></script>

<script>
    const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
    const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
    const getCookieValue = (key) => {
        const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
        return cookie?.split("=")[1];
    };

    Scalar.createApiReference('#app', {
        content: @json($spec),
        ...@json($config->renderer()->all(except: ['cdn', 'credentials'])),
        onBeforeRequest: ({ requestBuilder }) => {
            requestBuilder.headers.set(CSRF_TOKEN_HEADER_KEY, decodeURIComponent(getCookieValue(CSRF_TOKEN_COOKIE_KEY)))
        },
        customFetch: (input, init) => {
            return window.fetch(input, { ...init, credentials: @json($config->renderer()->get('credentials', 'include')) })
        }
    })
</script>
</body>
</html>

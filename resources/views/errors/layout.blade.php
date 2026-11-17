{{--
    Standalone shell for error pages.

    Deliberately self-contained: no @vite, no Flux, no app layout. An error page
    that depends on a built asset manifest can itself fail, which leaves the
    visitor with a stack trace instead of an explanation.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">

    <style>
        :root {
            color-scheme: light dark;
            --bg: #f5f7fa;
            --card: #ffffff;
            --text: #191c22;
            --muted: #52555d;
            --line: #d2d6dc;
            --accent: #3a5cff;
            --accent-text: #ffffff;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f131a;
                --card: #191c22;
                --text: #f2f3f5;
                --muted: #9fa1a7;
                --line: #3e4149;
                --accent: #6489fd;
                --accent-text: #0d0f15;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
        }

        .card {
            width: 100%;
            max-width: 480px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 32px;
            text-align: center;
        }

        .code {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .08em;
            color: var(--muted);
            margin: 0 0 8px;
        }

        h1 {
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 12px;
            letter-spacing: -.01em;
        }

        p { margin: 0 0 8px; color: var(--muted); font-size: 15px; }

        .who {
            margin: 20px 0 0;
            padding: 12px 16px;
            background: var(--bg);
            border-radius: 10px;
            font-size: 14px;
            color: var(--muted);
        }

        .actions {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }

        a.button, button.button {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--line);
            color: var(--text);
            background: transparent;
            cursor: pointer;
            font-family: inherit;
        }

        a.button.primary {
            background: var(--accent);
            color: var(--accent-text);
            border-color: var(--accent);
        }
    </style>
</head>
<body>
    <div class="card">
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>

        @yield('body')

        @auth
            <div class="who">
                {{ __('You are signed in as :name (:role).', [
                    'name' => auth()->user()->name,
                    'role' => auth()->user()->role->label(),
                ]) }}
            </div>
        @endauth

        <div class="actions">
            @yield('actions')
        </div>
    </div>
</body>
</html>

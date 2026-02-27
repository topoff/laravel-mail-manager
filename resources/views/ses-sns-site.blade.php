<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mail Manager SES/SNS Nova Site</title>
    <style>
        :root {
            --ok: #0f766e;
            --warn: #b45309;
            --fail: #b91c1c;
            --muted: #6b7280;
            --bg: #f8fafc;
            --card: #ffffff;
            --line: #e5e7eb;
            --blue: #1d4ed8;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--bg); margin: 0; padding: 24px; color: #111827; }
        .wrap { max-width: 1120px; margin: 0 auto; display: grid; gap: 16px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 20px; }
        h1, h2, h3 { margin: 0 0 12px; }
        h1 { font-size: 24px; }
        h2 { font-size: 18px; }
        h3 { font-size: 15px; }
        .meta { color: var(--muted); margin-bottom: 8px; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-weight: 700; font-size: 12px; }
        .ok { background: #ccfbf1; color: var(--ok); }
        .warn { background: #fef3c7; color: var(--warn); }
        .fail { background: #fee2e2; color: var(--fail); }
        .grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .button-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .command-button { width: 100%; border: 1px solid var(--line); background: #f9fafb; color: #111827; border-radius: 10px; padding: 10px 12px; font-weight: 700; cursor: pointer; text-align: left; }
        .command-button:hover { border-color: #cbd5e1; background: #f3f4f6; }
        .link-button { display: inline-block; border: 1px solid var(--line); background: #111827; color: #fff; border-radius: 10px; padding: 10px 12px; font-weight: 700; text-decoration: none; }
        .command-desc { margin: 6px 0 0; color: var(--muted); font-size: 12px; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        th { color: var(--muted); font-weight: 600; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 6px; font-size: 12px; }
        pre { margin: 0; background: #0b1020; color: #d1d5db; padding: 14px; border-radius: 10px; overflow-x: auto; }
        ul { margin: 0; padding-left: 18px; }
        li { margin: 6px 0; }
        a { color: var(--blue); text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Amazon SES + SNS Nova Site</h1>
        <p class="meta">One place to setup and verify AWS SES sending + SES/SNS event tracking for a new app.</p>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Sending Status</h2>
            @if(data_get($sending, 'ok') === true)
                <span class="badge ok">Healthy</span>
            @elseif(data_get($sending, 'ok') === false)
                <span class="badge fail">Needs Fixes</span>
            @else
                <span class="badge warn">Unknown</span>
            @endif
        </div>

        <div class="card">
            <h2>Tracking Status</h2>
            @if(data_get($tracking, 'ok') === true)
                <span class="badge ok">Healthy</span>
            @elseif(data_get($tracking, 'ok') === false)
                <span class="badge fail">Needs Fixes</span>
            @else
                <span class="badge warn">Unknown</span>
            @endif
        </div>

        <div class="card">
            <h2>Mail Transport</h2>
            <p><strong>Mailer:</strong> <code>{{ data_get($app_config, 'mail_default_mailer') ?: '(empty)' }}</code></p>
            <p><strong>From Email:</strong> <code>{{ data_get($app_config, 'mail_from_address') ?: '(empty)' }}</code></p>
            <p><strong>From Name:</strong> <code>{{ data_get($app_config, 'mail_from_name') ?: '(empty)' }}</code></p>
        </div>
    </div>

    <div class="card">
        <h2>Setup Commands</h2>
        <pre>@foreach($commands as $command){{ $command }}
@endforeach</pre>
    </div>

    <div class="card" id="command-results">
        <h2>Actions</h2>
        <p><a class="link-button" href="{{ $custom_mail_action_url }}" target="_blank" rel="noopener">Open Custom Mail Action</a></p>
        <div class="button-grid">
            @foreach((array) $command_buttons as $button)
                <form method="POST" action="{{ data_get($button, 'url') }}">
                    @csrf
                    <button type="submit" class="command-button">{{ data_get($button, 'label') }}</button>
                    <p class="command-desc">{{ data_get($button, 'description') }}</p>
                </form>
            @endforeach
        </div>

        @if(session()->has('mail_manager_ses_sns_command_result'))
            @php($result = (array) session('mail_manager_ses_sns_command_result'))
            <h3 style="margin-top:16px;">Last Command Result</h3>
            <p>
                @if((bool) data_get($result, 'ok'))
                    <span class="badge ok">Success</span>
                @else
                    <span class="badge fail">Failed</span>
                @endif
                <code>{{ data_get($result, 'label') }}</code>
                <code>exit: {{ data_get($result, 'exit_code') }}</code>
            </p>
            <pre>{{ data_get($result, 'output') }}</pre>
        @endif
    </div>

    <div class="card">
        <h2>Required Environment Variables</h2>
        <ul>
            @foreach($required_env as $name)
                <li><code>{{ $name }}</code></li>
            @endforeach
        </ul>
    </div>

    <div class="card">
        <h2>App Configuration Snapshot</h2>
        <table>
            @foreach((array) $app_config as $key => $value)
                <tr>
                    <th>{{ $key }}</th>
                    <td>
                        @if(is_bool($value))
                            <code>{{ $value ? 'true' : 'false' }}</code>
                        @elseif(is_array($value))
                            <code>{{ implode(', ', $value) }}</code>
                        @else
                            <code>{{ $value !== '' ? (string) $value : '(empty)' }}</code>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Sending Checks (SES)</h2>
            @if(data_get($sending, 'error'))
                <p><span class="badge fail">{{ data_get($sending, 'error') }}</span></p>
            @endif
            <table>
                <tr>
                    <th>Status</th>
                    <th>Check</th>
                    <th>Details</th>
                </tr>
                @forelse((array) data_get($sending, 'checks', []) as $check)
                    <tr>
                        <td>
                            @if((bool) data_get($check, 'ok'))
                                <span class="badge ok">OK</span>
                            @else
                                <span class="badge fail">FAIL</span>
                            @endif
                        </td>
                        <td>{{ data_get($check, 'label') }}</td>
                        <td>{{ data_get($check, 'details') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="meta">No checks available.</td></tr>
                @endforelse
            </table>
        </div>

        <div class="card">
            <h2>Tracking Checks (SES/SNS)</h2>
            @if(data_get($tracking, 'error'))
                <p><span class="badge fail">{{ data_get($tracking, 'error') }}</span></p>
            @endif
            <table>
                <tr>
                    <th>Status</th>
                    <th>Check</th>
                    <th>Details</th>
                </tr>
                @forelse((array) data_get($tracking, 'checks', []) as $check)
                    <tr>
                        <td>
                            @if((bool) data_get($check, 'ok'))
                                <span class="badge ok">OK</span>
                            @else
                                <span class="badge fail">FAIL</span>
                            @endif
                        </td>
                        <td>{{ data_get($check, 'label') }}</td>
                        <td>{{ data_get($check, 'details') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="meta">No checks available.</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Required DNS Records (Sending)</h2>
        <table>
            <tr>
                <th>Type</th>
                <th>Name</th>
                <th>Values</th>
            </tr>
            @forelse((array) data_get($sending, 'dns_records', []) as $record)
                <tr>
                    <td><code>{{ data_get($record, 'type') }}</code></td>
                    <td><code>{{ data_get($record, 'name') }}</code></td>
                    <td><code>{{ implode(' | ', (array) data_get($record, 'values', [])) }}</code></td>
                </tr>
            @empty
                <tr><td colspan="3" class="meta">No DNS records available (configure sending identity first).</td></tr>
            @endforelse
        </table>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Tracking Routes</h2>
            <ul>
                <li>Open pixel: <code>{{ data_get($routes, 'tracking_open') ?: '(route not available)' }}</code></li>
                <li>Click redirect: <code>{{ data_get($routes, 'tracking_click') ?: '(route not available)' }}</code></li>
                <li>SNS callback: <code>{{ data_get($routes, 'sns_callback') ?: '(route not available)' }}</code></li>
            </ul>
        </div>

        <div class="card">
            <h2>AWS Console Cross-Check</h2>
            <ul>
                <li><a href="{{ data_get($tracking, 'aws_console.ses_configuration_sets', '#') }}" target="_blank" rel="noopener">SES Configuration Sets</a></li>
                <li><a href="{{ data_get($tracking, 'aws_console.sns_topics', '#') }}" target="_blank" rel="noopener">SNS Topics</a></li>
                <li><a href="{{ data_get($tracking, 'aws_console.sns_subscriptions', '#') }}" target="_blank" rel="noopener">SNS Subscriptions</a></li>
            </ul>
        </div>
    </div>
</div>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mail Manager SES/SNS Status</title>
    <style>
        :root {
            --ok: #0f766e;
            --fail: #b91c1c;
            --muted: #6b7280;
            --bg: #f8fafc;
            --card: #ffffff;
            --line: #e5e7eb;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--bg); margin: 0; padding: 24px; color: #111827; }
        .wrap { max-width: 980px; margin: 0 auto; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        h1, h2 { margin: 0 0 12px; }
        h1 { font-size: 24px; }
        h2 { font-size: 18px; }
        .meta { color: var(--muted); margin-bottom: 8px; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-weight: 600; }
        .ok { background: #ccfbf1; color: var(--ok); }
        .fail { background: #fee2e2; color: var(--fail); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        th { color: var(--muted); font-weight: 600; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 6px; }
        ul { margin: 0; padding-left: 18px; }
        a { color: #1d4ed8; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>SES/SNS Integration Status</h1>
        @if(data_get($status, 'ok'))
            <span class="badge ok">Everything looks good</span>
        @else
            <span class="badge fail">Setup incomplete</span>
        @endif
        <p class="meta">Use this page after running the setup action/button to verify all required AWS resources.</p>
    </div>

    <div class="card">
        <h2>Configured Values</h2>
        <table>
            @foreach((array) data_get($status, 'configuration', []) as $key => $value)
                <tr>
                    <th>{{ $key }}</th>
                    <td>
                        @if(is_array($value))
                            <code>{{ implode(', ', $value) }}</code>
                        @else
                            <code>{{ (string) $value }}</code>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="card">
        <h2>Checks</h2>
        <table>
            <tr>
                <th>Status</th>
                <th>Check</th>
                <th>Details</th>
            </tr>
            @foreach((array) data_get($status, 'checks', []) as $check)
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
            @endforeach
        </table>
    </div>

    <div class="card">
        <h2>AWS Console Cross-Check</h2>
        <ul>
            <li><a href="{{ data_get($status, 'aws_console.ses_configuration_sets') }}" target="_blank" rel="noopener">SES Configuration Sets</a></li>
            <li><a href="{{ data_get($status, 'aws_console.sns_topics') }}" target="_blank" rel="noopener">SNS Topics</a></li>
            <li><a href="{{ data_get($status, 'aws_console.sns_subscriptions') }}" target="_blank" rel="noopener">SNS Subscriptions</a></li>
        </ul>
    </div>
</div>
</body>
</html>


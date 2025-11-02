<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant Not Found - {{ config('app.name', 'Laravel') }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .error-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 3rem;
            max-width: 32rem;
            width: 100%;
            text-align: center;
        }
        .error-code {
            font-size: 6rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
        }
        .error-message {
            color: #6b7280;
            margin-bottom: 2rem;
            line-height: 1.625;
        }
        .error-details {
            background: #f9fafb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: left;
        }
        .error-details-title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .error-details-item {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        .error-details-item strong {
            color: #374151;
        }
        .btn-home {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1 class="error-title">Tenant Not Found</h1>
        <p class="error-message">
            The tenant you're looking for doesn't exist or is not available for the domain/subdomain you're accessing.
        </p>
        
        <div class="error-details">
            <div class="error-details-title">Request Details</div>
            <div class="error-details-item">
                <strong>Domain/Subdomain:</strong> {{ $host }}
            </div>
            <div class="error-details-item">
                <strong>Resolver:</strong> {{ $resolver ?? 'not set' }}
            </div>
            @if($appDomain)
            <div class="error-details-item">
                <strong>APP_DOMAIN:</strong> {{ $appDomain }}
            </div>
            @endif
        </div>
        
        <a href="{{ url('/') }}" class="btn-home">
            Go to Homepage
        </a>
    </div>
</body>
</html>


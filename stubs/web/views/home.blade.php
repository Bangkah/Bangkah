<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Ready to Launch</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 800px;
        }
        .logo {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        h1 {
            font-size: 2.5rem;
            margin: 0 0 1rem;
            font-weight: 700;
        }
        .subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            margin-bottom: 3rem;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        .feature {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .feature h3 {
            margin: 0 0 0.5rem;
            font-size: 1.1rem;
        }
        .feature p {
            margin: 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        .cta {
            display: inline-flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn {
            padding: 0.875rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid;
        }
        .btn-primary {
            background: #fff;
            color: #667eea;
            border-color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .btn-secondary {
            background: transparent;
            color: #fff;
            border-color: rgba(255,255,255,0.5);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
            border-color: #fff;
        }
        .footer {
            margin-top: 3rem;
            opacity: 0.7;
            font-size: 0.9rem;
        }
        @media (max-width: 640px) {
            .logo { font-size: 3rem; }
            h1 { font-size: 1.875rem; }
            .subtitle { font-size: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🚀</div>
        <h1>Welcome to {{ config('app.name') }}</h1>
        <p class="subtitle">Your Laravel project is ready. Start building something amazing!</p>
        
        <div class="features">
            <div class="feature">
                <h3>⚡ Fast Setup</h3>
                <p>Bangkah starter kit configured and ready</p>
            </div>
            <div class="feature">
                <h3>🎨 Modern Stack</h3>
                <p>Laravel 12 with Vite & Tailwind</p>
            </div>
            <div class="feature">
                <h3>🔒 Secure</h3>
                <p>Best practices built-in</p>
            </div>
        </div>

        <div class="cta">
            <a href="https://laravel.com/docs" class="btn btn-primary" target="_blank">Documentation</a>
            <a href="https://github.com/Bangkah" class="btn btn-secondary" target="_blank">GitHub</a>
        </div>

        <div class="footer">
            <p>Built with ❤️ using Bangkah Starter Kit</p>
        </div>
    </div>
</body>
</html>

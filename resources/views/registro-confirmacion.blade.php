{{-- Resultado del link de confirmación del registro público cuando NO se pudo
     crear la cuenta (enlace inválido, vencido, ya usado...). Si se crea, el
     controller redirige directo al dashboard y esta vista no se usa.
     Misma identidad que registro.blade.php (negro/rojo de plataforma). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma Reservas - {{ $titulo }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #0a0a0a;
            --accent: #e11d2e;
            --white: #ffffff;
            --muted: #a3a3a3;
            --card: rgba(255, 255, 255, 0.05);
            --border: rgba(255, 255, 255, 0.1);
        }

        body {
            background-color: var(--bg);
            color: var(--white);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .tarjeta-resultado {
            max-width: 460px;
            width: 100%;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2.5rem 2rem;
            text-align: center;
        }

        .tarjeta-resultado i {
            font-size: 2.6rem;
            color: var(--accent);
            display: block;
            margin-bottom: 1rem;
        }

        .tarjeta-resultado h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.6rem;
            margin-bottom: 0.75rem;
        }

        .tarjeta-resultado p {
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 1.75rem;
        }

        .btn-resultado {
            display: inline-block;
            background-color: var(--accent);
            color: var(--white);
            font-weight: 600;
            text-decoration: none;
            padding: 0.7rem 1.6rem;
            border-radius: 10px;
        }

        .btn-resultado:hover {
            color: var(--white);
            filter: brightness(1.1);
        }
    </style>
</head>
<body>
    <div class="tarjeta-resultado">
        <i class="bi bi-envelope-exclamation"></i>
        <h1>{{ $titulo }}</h1>
        <p>{{ $texto }}</p>

        @if ($accion === 'login')
            <a href="{{ url('/login') }}" class="btn-resultado">Iniciar sesión</a>
        @else
            <a href="{{ url('registro') }}" class="btn-resultado">Registrarme de nuevo</a>
        @endif
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found — SanCo</title>
    <meta name="robots" content="noindex, nofollow">

    
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>

    <style>
        :root {
            --bg:    #18181b;
            --card:  #1e1e21;
            --text:  #ffffff;
            --muted: #a1a1aa;
            --accent:#ec4899;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Card */
        .card {
            position: relative;
            z-index: 10;
            text-align: center;
            padding: 3.5rem 4rem;
            background: #1e1e21;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 1.5rem;
            box-shadow: 0 32px 80px rgba(0,0,0,0.6);
            max-width: 480px;
            width: 90%;
            animation: fadeUp 0.55s cubic-bezier(0.16,1,0.3,1) both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* 404 number */
        .error-code {
            font-size: clamp(5rem, 18vw, 7rem);
            font-weight: 800;
            letter-spacing: -4px;
            line-height: 1;
            color: #ec4899;
            margin-bottom: 0.5rem;
            user-select: none;
        }

        .title {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.6rem;
            letter-spacing: -0.3px;
        }

        .subtitle {
            font-size: 0.92rem;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 2.2rem;
        }

        /* Button */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.7rem 1.8rem;
            border-radius: 0.75rem;
            border: none;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            background-color: #ec4899;
            color: #fff;
            box-shadow: 0 4px 20px rgba(236, 72, 153, 0.3);
            transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            background-color: #db2777;
            box-shadow: 0 8px 28px rgba(236, 72, 153, 0.45);
        }
        .btn:active { transform: translateY(0); }

        /* Icon */
        .icon {
            font-size: 3.2rem;
            margin-bottom: 1rem;
            display: block;
            animation: wobble 3s ease-in-out infinite;
        }
        @keyframes wobble {
            0%, 100% { transform: rotate(-6deg); }
            50%       { transform: rotate(6deg); }
        }

        /* Divider */
        .divider {
            width: 40px;
            height: 3px;
            border-radius: 999px;
            background-color: #ec4899;
            margin: 0 auto 1.4rem;
        }
    </style>
</head>

<body>
    <div class="card">
        <span class="icon">🔍</span>
        <div class="error-code">404</div>
        <div class="divider"></div>
        <p class="title">Page Not Found</p>
        <p class="subtitle">
            The page you're looking for doesn't exist.
        </p>
        <a href="<?php echo e(url('/')); ?>" class="btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 3l9 6.75V21a.75.75 0 01-.75.75H15.75a.75.75 0 01-.75-.75v-4.5h-6V21a.75.75 0 01-.75.75H3.75A.75.75 0 013 21V9.75z"/>
            </svg>
            Go Home
        </a>
    </div>
</body>

</html>
<?php /**PATH C:\Users\johan\Desktop\Laravel\SanCo\resources\views/errors/4xx.blade.php ENDPATH**/ ?>
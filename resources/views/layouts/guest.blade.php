<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#033151">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <script>
            (() => {
                const theme = localStorage.getItem('coreti-theme') || 'system';
                const resolved = theme === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : theme;

                document.documentElement.classList.toggle('dark', resolved === 'dark');
                document.documentElement.dataset.theme = theme;
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('layouts.partials.button-theme')
    </head>
    <body class="site-body font-sans text-gray-900 antialiased">
        @include('layouts.partials.toasts')

        <div class="login-shell">
            {{-- Painel de marca com a cena 3D (decorativo) --}}
            <section class="login-hero">
                <canvas class="login-net" aria-hidden="true"></canvas>
                <div class="login-hero__glow" aria-hidden="true"></div>

                <div class="login-nodes" aria-hidden="true">
                    <div class="login-tile"><span class="login-tile__dot"></span>Active Directory</div>
                    <div class="login-tile"><span class="login-tile__dot"></span>Office 365</div>
                    <div class="login-tile"><span class="login-tile__dot"></span>Bancada de Serviços</div>
                    <div class="login-tile"><span class="login-tile__dot"></span>Jira</div>
                    <div class="login-tile"><span class="login-tile__dot"></span>Dashboards</div>
                    <div class="login-tile"><span class="login-tile__dot"></span>CMDB</div>
                </div>

                <div class="login-hero__content">
                    <img src="{{ asset('images/branding/cocari-logo-white.png') }}" alt="Cocari" class="login-brandmark">
                    <h1 class="login-title">Central de TI</h1>
                    <p class="login-subtitle">
                        Service Desk, Active Directory, Office 365, Bancada de Serviços,
                        Jira e dashboards — a TI da Cocari num só lugar.
                    </p>
                </div>
            </section>

            {{-- Formulário --}}
            <main class="login-panel">
                <div class="login-card">
                    <a href="/" class="login-card__logo-link">
                        <x-application-logo class="login-card__logo" />
                    </a>

                    {{ $slot }}
                </div>

                <p class="login-foot">© {{ date('Y') }} Cocari · CoreTI</p>
            </main>
        </div>

        <script>
            // Rede de pontos conectados (efeito "plexus") no fundo do painel.
            // Os CHIPS DOS MÓDULOS são nós da rede: derivam devagar e as linhas
            // saem do ponto (dot) de cada módulo e também se ligam ao cursor.
            // Canvas puro, sem dependências.
            (() => {
                const hero = document.querySelector('.login-hero');
                const canvas = hero && hero.querySelector('.login-net');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                if (!ctx) return;

                const reduz = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                const dpr = Math.min(window.devicePixelRatio || 1, 2);
                const LINK = 150;   // distância máx. de ligação entre nós (px)
                const MLINK = 185;  // distância de ligação até o cursor
                const DOTX = 18;    // deslocamento do dot a partir da esquerda do chip
                const PAD = 8;      // margem p/ o chip não encostar na borda
                let w = 0, h = 0, pts = [], labels = [], raf = null, rodando = false;
                const mouse = { x: -9999, y: -9999, ativo: false };

                const els = Array.from(hero.querySelectorAll('.login-nodes .login-tile'));

                const corLinha = (ratioX, a) => {
                    // Azul sky; ganha um tom rosado à direita (como na referência).
                    if (ratioX > 0.66) {
                        const t = Math.min(1, (ratioX - 0.66) / 0.34);
                        const r = Math.round(120 + t * 80);
                        const g = Math.round(190 - t * 110);
                        const b = Math.round(255 - t * 70);
                        return `rgba(${r},${g},${b},${a})`;
                    }
                    return `rgba(120,190,255,${a})`;
                };

                const construirPts = (n) => {
                    pts = [];
                    for (let i = 0; i < n; i++) {
                        pts.push({
                            x: Math.random() * w, y: Math.random() * h,
                            vx: (Math.random() - 0.5) * 0.35,
                            vy: (Math.random() - 0.5) * 0.35,
                        });
                    }
                };

                const medirLabels = () => {
                    // No celular (hero baixo), mostra só 2 módulos; no desktop, todos.
                    const maxLabels = w < 680 ? 2 : els.length;
                    const antigos = labels;
                    labels = [];
                    els.forEach((el, i) => {
                        if (i >= maxLabels) { el.style.display = 'none'; return; }
                        el.style.display = '';
                        const r = el.getBoundingClientRect();
                        const bw = r.width || 150, bh = r.height || 34;
                        const prev = antigos.find((L) => L.el === el);
                        const L = {
                            el, bw, bh,
                            // x,y = ponto do dot (âncora das linhas)
                            x: prev ? prev.x : (Math.random() * (w - bw - 2 * PAD) + PAD + DOTX),
                            y: prev ? prev.y : (Math.random() * (h - bh - 2 * PAD) + PAD + bh / 2),
                            vx: prev ? prev.vx : (Math.random() - 0.5) * 0.16,
                            vy: prev ? prev.vy : (Math.random() - 0.5) * 0.16,
                        };
                        // Posiciona já (evita o "flash" no canto antes do 1º frame).
                        el.style.transform = 'translate(' + (L.x - DOTX) + 'px,' + (L.y - L.bh / 2) + 'px)';
                        el.style.opacity = '1';
                        labels.push(L);
                    });
                };

                const redimensionar = () => {
                    const r = hero.getBoundingClientRect();
                    w = r.width; h = r.height;
                    canvas.width = Math.max(1, Math.floor(w * dpr));
                    canvas.height = Math.max(1, Math.floor(h * dpr));
                    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                    construirPts(Math.round(Math.min(90, (w * h) / 16000)));
                    medirLabels();
                };

                const mover = (p, minX, maxX, minY, maxY) => {
                    p.x += p.vx; p.y += p.vy;
                    if (p.x < minX) { p.x = minX; p.vx *= -1; } else if (p.x > maxX) { p.x = maxX; p.vx *= -1; }
                    if (p.y < minY) { p.y = minY; p.vy *= -1; } else if (p.y > maxY) { p.y = maxY; p.vy *= -1; }
                };

                const desenhar = () => {
                    raf = rodando ? requestAnimationFrame(desenhar) : null;
                    ctx.clearRect(0, 0, w, h);

                    for (const p of pts) mover(p, 0, w, 0, h);
                    for (const L of labels) {
                        mover(L, PAD + DOTX, w - L.bw - PAD + DOTX, PAD + L.bh / 2, h - L.bh / 2 - PAD);
                        L.el.style.transform = 'translate(' + (L.x - DOTX) + 'px,' + (L.y - L.bh / 2) + 'px)';
                    }

                    const all = pts.concat(labels);
                    for (let i = 0; i < all.length; i++) {
                        const a = all[i];
                        for (let j = i + 1; j < all.length; j++) {
                            const b = all[j];
                            const dx = a.x - b.x, dy = a.y - b.y;
                            const d = Math.hypot(dx, dy);
                            if (d < LINK) {
                                ctx.strokeStyle = corLinha(((a.x + b.x) / 2) / w, (1 - d / LINK) * 0.5);
                                ctx.lineWidth = 1;
                                ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
                            }
                        }
                        if (mouse.ativo) {
                            const dx = a.x - mouse.x, dy = a.y - mouse.y;
                            const d = Math.hypot(dx, dy);
                            if (d < MLINK) {
                                ctx.strokeStyle = `rgba(125,211,252,${(1 - d / MLINK) * 0.85})`;
                                ctx.lineWidth = 1;
                                ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(mouse.x, mouse.y); ctx.stroke();
                                if (!a.el) { a.x -= dx * 0.004; a.y -= dy * 0.004; } // atrai só os pontinhos
                            }
                        }
                    }

                    // Pontinhos (os chips já têm o próprio dot no HTML).
                    ctx.fillStyle = 'rgba(180,220,255,0.9)';
                    for (const p of pts) { ctx.beginPath(); ctx.arc(p.x, p.y, 1.6, 0, Math.PI * 2); ctx.fill(); }
                };

                const iniciar = () => { if (!rodando) { rodando = true; if (!raf) raf = requestAnimationFrame(desenhar); } };
                const parar = () => { rodando = false; };
                const umFrame = () => { const r = rodando; rodando = false; desenhar(); rodando = r; };

                window.addEventListener('resize', () => { redimensionar(); if (reduz) umFrame(); });
                hero.addEventListener('pointermove', (e) => {
                    const r = hero.getBoundingClientRect();
                    mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top; mouse.ativo = true;
                });
                hero.addEventListener('pointerleave', () => { mouse.ativo = false; mouse.x = -9999; mouse.y = -9999; });
                document.addEventListener('visibilitychange', () => { document.hidden ? parar() : (reduz ? umFrame() : iniciar()); });

                redimensionar();
                reduz ? umFrame() : iniciar();
            })();
        </script>
    </body>
</html>

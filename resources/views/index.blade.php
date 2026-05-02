<!DOCTYPE html>
<html lang="ar" dir="ltr" id="html-root" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ai Pro</title>
    <link rel="preload" href="/images/ai_logo.png" as="image" type="image/webp" fetchpriority="high">
    <link rel="preload" href="/images/google_logo.webp" as="image" type="image/webp">

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>

    @vite(['resources/js/app.js'])
</head>

<body class="app-body">
    <div id="app"></div>

</body>

</html>


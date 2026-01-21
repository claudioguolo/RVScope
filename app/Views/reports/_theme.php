<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
<style>
    :root {
        --brand: #166379;
        --brand-dark: #0b3a47;
        --accent: #f0b429;
        --bg-top: #f7f9fb;
        --bg-bottom: #eef2f6;
        --ink: #0f172a;
        --card: #ffffff;
        --border: #e2e8f0;
    }

    body {
        font-family: "Source Sans 3", "Segoe UI", sans-serif;
        color: var(--ink);
        background: linear-gradient(180deg, var(--bg-top), var(--bg-bottom));
    }

    h1, h2, h3, .app-title {
        font-family: "Space Grotesk", "Trebuchet MS", sans-serif;
        letter-spacing: 0.3px;
    }

    .app-header {
        background: linear-gradient(100deg, var(--brand-dark), var(--brand));
        color: #fff;
        border-radius: 16px;
        padding: 18px 22px;
        box-shadow: 0 14px 26px rgba(15, 23, 42, 0.14);
    }

    .app-card {
        background: var(--card);
        border-radius: 16px;
        border: 1px solid var(--border);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }

    .btn-brand {
        background: var(--brand);
        border-color: var(--brand);
        color: #fff;
    }

    .btn-brand:hover {
        background: var(--brand-dark);
        border-color: var(--brand-dark);
        color: #fff;
    }

    .btn-soft {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #fff;
    }

    .btn-soft:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
    }

    .table thead th {
        background: var(--brand-dark);
        color: #fff;
        border-color: var(--brand-dark);
    }

    .accordion-button:not(.collapsed) {
        background: rgba(22, 99, 121, 0.12);
        color: var(--brand-dark);
    }

    .accordion-item {
        border: 1px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
    }

    .accordion-item + .accordion-item {
        margin-top: 12px;
    }

    .modal-content {
        border-radius: 16px;
        border: 1px solid var(--border);
    }

    a {
        color: var(--brand-dark);
    }

    a:hover {
        color: var(--brand);
    }
</style>

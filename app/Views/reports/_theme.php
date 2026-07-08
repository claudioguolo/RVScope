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

    .app-menu-nav .nav-link {
        color: var(--brand-dark);
        font-weight: 600;
        border-radius: 10px;
        padding: 8px 12px;
    }

    .app-menu-nav .nav-link.active {
        background: rgba(22, 99, 121, 0.12);
        color: var(--brand-dark);
    }

    .app-menu-dropdown {
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
    }

    .app-menu-dropdown .dropdown-item {
        font-weight: 600;
        color: var(--brand-dark);
    }

    .app-menu-dropdown .dropdown-item.active,
    .app-menu-dropdown .dropdown-item:active {
        background: rgba(22, 99, 121, 0.12);
        color: var(--brand-dark);
    }

    .app-breadcrumb {
        --bs-breadcrumb-divider: ">";
        margin: 0;
    }

    .app-breadcrumb .breadcrumb-item {
        color: #64748b;
        font-size: 0.95rem;
    }

    .app-breadcrumb .breadcrumb-item a {
        color: #64748b;
        text-decoration: none;
    }

    .app-breadcrumb .breadcrumb-item a:hover {
        color: var(--brand-dark);
        text-decoration: underline;
    }

    .app-breadcrumb .breadcrumb-item.active {
        color: var(--brand-dark);
        font-weight: 600;
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

    .app-settings-link {
        width: 46px;
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.24);
        background: rgba(255, 255, 255, 0.1);
        transition: transform 0.18s ease, background 0.18s ease, border-color 0.18s ease;
    }

    .app-settings-link:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.18);
        border-color: rgba(255, 255, 255, 0.38);
        transform: translateY(-1px) rotate(10deg);
    }

    .app-admin-cluster {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .app-admin-user {
        max-width: min(240px, 48vw);
        display: inline-block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #fff;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.24);
        background: rgba(255, 255, 255, 0.1);
        padding: 8px 12px;
        font-weight: 600;
        line-height: 1.2;
    }

    .app-admin-user:hover,
    .app-admin-user:focus {
        color: #fff;
        background: rgba(255, 255, 255, 0.18);
        border-color: rgba(255, 255, 255, 0.38);
    }

    .app-login-link {
        display: inline-flex;
        align-items: center;
        min-height: 46px;
        color: #fff;
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.24);
        background: rgba(255, 255, 255, 0.1);
        padding: 8px 16px;
        font-weight: 700;
        text-decoration: none;
        line-height: 1.2;
    }

    .app-login-link:hover,
    .app-login-link:focus {
        color: #fff;
        background: rgba(255, 255, 255, 0.18);
        border-color: rgba(255, 255, 255, 0.38);
    }

    .app-settings-link svg,
    .app-gear-badge svg {
        width: 22px;
        height: 22px;
        fill: currentColor;
    }

    .app-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(22, 99, 121, 0.1);
        color: var(--brand-dark);
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .app-gear-badge {
        flex: 0 0 auto;
        width: 58px;
        height: 58px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
        color: var(--brand);
        background: linear-gradient(135deg, rgba(22, 99, 121, 0.1), rgba(240, 180, 41, 0.14));
        border: 1px solid rgba(22, 99, 121, 0.12);
    }

    .app-inline-note {
        padding: 14px 16px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid var(--border);
        color: #475569;
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

    .form-control:focus {
        border-color: rgba(22, 99, 121, 0.45);
        box-shadow: 0 0 0 0.25rem rgba(22, 99, 121, 0.15);
    }
</style>

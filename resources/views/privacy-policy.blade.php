<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MemoDoc Privacy Policy</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #07111f;
            --bg-soft: #0c1730;
            --panel: rgba(8, 16, 34, 0.9);
            --border: rgba(146, 214, 255, 0.2);
            --text: #ebf5ff;
            --text-soft: rgba(235, 245, 255, 0.78);
            --accent: #73f0ff;
            --radius: 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(115, 240, 255, 0.14), transparent 35%),
                radial-gradient(circle at 80% 18%, rgba(125, 140, 255, 0.16), transparent 30%),
                linear-gradient(180deg, #07111f 0%, #050b16 100%);
            color: var(--text);
            font-family: "Plus Jakarta Sans", "Segoe UI", sans-serif;
            line-height: 1.65;
        }

        .wrapper {
            width: min(920px, calc(100vw - 32px));
            margin: 48px auto;
            padding: 28px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: linear-gradient(180deg, var(--bg-soft) 0%, var(--panel) 100%);
            box-shadow: 0 24px 80px rgba(4, 9, 23, 0.45);
        }

        h1, h2 {
            line-height: 1.2;
            margin: 0;
        }

        h1 {
            font-size: clamp(2rem, 5vw, 3rem);
            margin-bottom: 12px;
        }

        h2 {
            margin-top: 34px;
            font-size: clamp(1.2rem, 3vw, 1.55rem);
            color: var(--accent);
        }

        p, li {
            color: var(--text-soft);
            margin: 14px 0 0;
        }

        ul {
            margin: 10px 0 0;
            padding-left: 20px;
        }

        .meta {
            margin-top: 0;
            color: var(--text-soft);
            font-size: 0.95rem;
        }

        .home-link {
            display: inline-flex;
            margin-top: 28px;
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }

        .home-link:hover,
        .home-link:focus-visible {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <main class="wrapper">
        <h1>Privacy Policy</h1>
        <p class="meta">Last updated: {{ now()->toDateString() }}</p>

        <p>
            This Privacy Policy explains how MemoDoc collects, uses, and protects information when you use our website,
            request a demo, or interact with our document intelligence services.
        </p>

        <h2>1. Information We Collect</h2>
        <ul>
            <li>Contact details you provide, such as name, email, and company.</li>
            <li>Usage details related to demo requests and product interactions.</li>
            <li>Technical information like browser type, IP address, and basic diagnostic logs.</li>
        </ul>

        <h2>2. How We Use Information</h2>
        <ul>
            <li>To respond to demo requests and communicate with you.</li>
            <li>To improve product quality, reliability, and security.</li>
            <li>To monitor abuse, protect our systems, and comply with legal requirements.</li>
        </ul>

        <h2>3. Document Data Handling</h2>
        <p>
            If you share document-related content during evaluation or onboarding, we use it only to provide and improve
            the service experience you requested. We do not sell your data.
        </p>

        <h2>4. Data Sharing</h2>
        <p>
            We may share data with trusted infrastructure and service providers strictly to operate our platform.
            These providers are expected to protect data and process it only for authorized purposes.
        </p>

        <h2>5. Data Retention</h2>
        <p>
            We retain information for as long as necessary to provide services, fulfill legal obligations, resolve disputes,
            and enforce agreements.
        </p>

        <h2>6. Your Choices</h2>
        <p>
            You can contact us to request access, correction, or deletion of personal information, subject to applicable law.
        </p>

        <h2>7. Contact</h2>
        <p>
            For privacy-related requests, please contact us at <a href="mailto:privacy@memodoc.ai">privacy@memodoc.ai</a>.
        </p>

        <a class="home-link" href="/">Back to MemoDoc landing page</a>
    </main>
</body>
</html>

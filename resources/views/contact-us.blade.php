<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MemoDoc Contact Us</title>
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
            --danger: #ff8f93;
            --success: #90ffa7;
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

        h1 {
            margin: 0;
            font-size: clamp(2rem, 5vw, 3rem);
            line-height: 1.2;
        }

        .lead {
            margin-top: 12px;
            color: var(--text-soft);
        }

        .status {
            margin-top: 18px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(144, 255, 167, 0.3);
            background: rgba(144, 255, 167, 0.08);
            color: var(--success);
        }

        .form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        label {
            display: grid;
            gap: 8px;
        }

        .full {
            grid-column: 1 / -1;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid rgba(235, 245, 255, 0.16);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.04);
            color: var(--text);
            font: inherit;
        }

        textarea {
            resize: vertical;
        }

        input::placeholder,
        textarea::placeholder {
            color: rgba(235, 245, 255, 0.38);
        }

        input:focus,
        textarea:focus {
            outline: 1px solid rgba(115, 240, 255, 0.52);
            border-color: rgba(115, 240, 255, 0.52);
        }

        .error {
            color: var(--danger);
            margin-top: -2px;
            font-size: 0.9rem;
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 8px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 20px;
            border: 0;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--accent) 0%, #a8fbff 100%);
            color: #06101b;
            font-weight: 700;
            cursor: pointer;
        }

        .home-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
        }

        .home-link:hover,
        .home-link:focus-visible {
            text-decoration: underline;
        }

        @media (max-width: 760px) {
            .wrapper {
                margin: 18px auto;
                padding: 20px;
            }

            .form {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <main class="wrapper">
        <h1>Contact Us</h1>
        <p class="lead">Tell us what you need and we will respond as soon as possible.</p>

        @if (session('status'))
            <p class="status">{{ session('status') }}</p>
        @endif

        <form class="form" method="POST" action="/contact-us">
            @csrf
            <label>
                <span>Name</span>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Ada Lovelace" required>
                @error('name')
                    <small class="error">{{ $message }}</small>
                @enderror
            </label>

            <label>
                <span>Email</span>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="ada@company.com" required>
                @error('email')
                    <small class="error">{{ $message }}</small>
                @enderror
            </label>

            <label>
                <span>Company (optional)</span>
                <input type="text" name="company" value="{{ old('company') }}" placeholder="Memo Systems">
                @error('company')
                    <small class="error">{{ $message }}</small>
                @enderror
            </label>

            <label>
                <span>Subject</span>
                <input type="text" name="subject" value="{{ old('subject') }}" placeholder="Need help with onboarding" required>
                @error('subject')
                    <small class="error">{{ $message }}</small>
                @enderror
            </label>

            <label class="full">
                <span>Message</span>
                <textarea name="message" rows="6" placeholder="Describe your request" required>{{ old('message') }}</textarea>
                @error('message')
                    <small class="error">{{ $message }}</small>
                @enderror
            </label>

            <div class="full actions">
                <button class="button" type="submit">Send message</button>
                <a class="home-link" href="/">Back to MemoDoc landing page</a>
            </div>
        </form>
    </main>
</body>
</html>

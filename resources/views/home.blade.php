<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta
        name="description"
        content="MemoDoc transforms PDFs into searchable AI memory with grounded answers, OCR-ready ingestion, and fast retrieval."
    />
    <title>MemoDoc | AI Memory For Documents</title>
    @vite(['resources/ts/landing.ts'])
</head>
<body class="landing-shell">
    <div id="landing-app"></div>
</body>
</html>

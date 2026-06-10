<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New MemoDoc contact message</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">New MemoDoc contact message</h1>

    <p><strong>Name:</strong> {{ $submission['name'] }}</p>
    <p><strong>Email:</strong> {{ $submission['email'] }}</p>
    <p><strong>Company:</strong> {{ $submission['company'] ?: 'Not provided' }}</p>
    <p><strong>Subject:</strong> {{ $submission['subject'] }}</p>

    <h2 style="font-size: 16px; margin-top: 24px;">Message</h2>
    <p style="white-space: pre-line;">{{ $submission['message'] }}</p>
</body>
</html>

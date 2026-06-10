# Flutter Gemini OCR via Laravel

This guide replaces direct Gemini calls from Flutter for document OCR and structured extraction.

Flutter should upload page images to Laravel. Laravel uses the server-side `GEMINI_API_KEY` and `GEMINI_MODEL`, calls Gemini, validates the JSON response, and returns the structured OCR payload to Flutter.

## Endpoint

`POST /api/ocr/analyze`

Authentication:

- Bearer token from Laravel Sanctum is required.

Content type:

```text
multipart/form-data
```

## Request Fields

- `pages[]`: required, one or more page images
- `allowed_categories[]`: required, one or more category strings that the OCR result must use for `category`

Accepted image types:

- `jpg`
- `jpeg`
- `png`
- `webp`
- `pdf`

Current limits:

- up to 12 page images per request
- up to 10 MB per image

## Laravel Response

Response body:

- `data`: normalized OCR result
- `meta.page_count`: number of uploaded page images
- `meta.allowed_categories`: the category list Laravel enforced
- `meta.model`: Gemini model used by Laravel

Example response:

```json
{
  "data": {
    "document_type": "receipt",
    "category": "expenses",
    "extracted_text": "Full OCR text from all pages...",
    "summary": "This is a restaurant receipt showing a completed card payment.",
    "action_items": [
      "Record the receipt in monthly expenses"
    ],
    "entities": {
      "Organization": "Acme Cafe",
      "Date": "2026-04-07",
      "Currency": "USD"
    },
    "amount": 42.5,
    "date": "2026-04-07",
    "merchant": "Acme Cafe",
    "payment_method": "Visa",
    "items": [
      "Lunch combo",
      "Coffee"
    ],
    "tax_amount": "3.50",
    "tip_amount": null
  },
  "meta": {
    "page_count": 2,
    "allowed_categories": [
      "expenses",
      "legal",
      "personal"
    ],
    "model": "gemma-4-31b-it"
  }
}
```

## Error Handling

Validation errors return `422`.

Examples:

- missing `pages`
- non-image uploads
- unsupported image format
- missing `allowed_categories`

Gemini upstream or response-shape errors return `502` or `503`.

Examples:

- `503` when `GEMINI_API_KEY` is not configured on Laravel
- `502` when Gemini returns invalid JSON or a category outside the allowed set

## Flutter Dio Example

```dart
import 'dart:io';

import 'package:dio/dio.dart';

class OcrApiClient {
  OcrApiClient({required this.dio});

  final Dio dio;

  Future<Map<String, dynamic>> analyzeDocument({
    required String token,
    required List<File> pages,
    required List<String> allowedCategories,
  }) async {
    final formData = FormData();

    for (final page in pages) {
      formData.files.add(
        MapEntry(
          'pages[]',
          await MultipartFile.fromFile(
            page.path,
            filename: page.uri.pathSegments.last,
          ),
        ),
      );
    }

    for (final category in allowedCategories) {
      formData.fields.add(MapEntry('allowed_categories[]', category));
    }

    final response = await dio.post<Map<String, dynamic>>(
      '/api/ocr/analyze',
      data: formData,
      options: Options(
        headers: {
          'Authorization': 'Bearer $token',
        },
      ),
    );

    return response.data ?? <String, dynamic>{};
  }
}
```

## Migration Notes

Remove direct Gemini usage from Flutter:

1. Stop sending the Gemini API key from the mobile app.
2. Stop building the OCR prompt in Flutter.
3. Send page images and allowed categories to Laravel instead.
4. Read the structured result from `response.data['data']`.
5. Handle Laravel validation and upstream errors in the app UI.

Recommended Flutter flow:

1. Capture or select the document page images.
2. Build the allowed category list for the current user flow.
3. Send the multipart request to Laravel.
4. Use `response.data['data']` as the structured OCR result.
5. Show `response.data['meta']` only for diagnostics or logging if needed.

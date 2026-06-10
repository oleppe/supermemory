# Flutter API Integration Guide

This document explains how to connect the Flutter app to the Laravel API layer that now proxies Supermemory.

## Overview

The Flutter app should talk only to Laravel.

Flow:

1. Flutter authenticates with Laravel using Sanctum bearer tokens.
2. Laravel talks to Supermemory using the server-side `SUPERMEMORY_API_KEY`.
3. Every Supermemory request is scoped with a deterministic per-user tag: `user-<local_user_id>`.
4. Laravel persists ingestion status rows in MySQL and mirrors them into Firestore at `user_ingestions/{userId}/items/{trackingId}`.
5. Flutter never sends the Supermemory key and never manages upstream auth.

Base URL example:

```text
http://foobar.net1:8080/api
```

## Authentication

### Register

`POST /api/auth/register`

Request:

```json
{
  "email": "user@example.com",
  "password": "secret123"
}
```

Response:

```json
{
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "user",
    "is_admin": false
  },
  "subscription": {
    "id": 1,
    "status": "active",
    "current_period_start": "2026-04-07T00:00:00Z",
    "current_period_end": "2026-05-07T00:00:00Z",
    "cancelled_at": null,
    "ends_at": null,
    "assignment_note": "Assigned on registration.",
    "plan": {
      "id": 1,
      "code": "free",
      "name": "Free / Discovery",
      "description": "Light usage to help users adopt the product.",
      "price_cents": 0,
      "currency": "USD",
      "billing_interval_months": 1,
      "limits": {
        "monthly_files": 10,
        "daily_files": null,
        "monthly_questions": 25,
        "daily_questions": null,
        "max_pdf_pages": 10
      },
      "is_active": true,
      "sort_order": 1
    }
  },
  "usage": {
    "files": {
      "metric": "files",
      "limit": 10,
      "used": 0,
      "remaining": 10,
      "billing_cycle": {
        "limit": 10,
        "used": 0,
        "remaining": 10,
        "period_start": "2026-04-07T00:00:00Z",
        "period_end": "2026-05-07T00:00:00Z"
      },
      "daily": {
        "limit": null,
        "used": 0,
        "remaining": null,
        "period_start": "2026-04-07T00:00:00Z",
        "period_end": "2026-04-08T00:00:00Z"
      }
    },
    "ai_questions": {
      "metric": "ai_questions",
      "limit": 25,
      "used": 0,
      "remaining": 25,
      "billing_cycle": {
        "limit": 25,
        "used": 0,
        "remaining": 25,
        "period_start": "2026-04-07T00:00:00Z",
        "period_end": "2026-05-07T00:00:00Z"
      },
      "daily": {
        "limit": null,
        "used": 0,
        "remaining": null,
        "period_start": "2026-04-07T00:00:00Z",
        "period_end": "2026-04-08T00:00:00Z"
      }
    }
  },
  "token": "1|sanctum-token"
}
```

### Login

`POST /api/auth/login`

Request:

```json
{
  "email": "user@example.com",
  "password": "secret123"
}
```

Response:

```json
{
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "user",
    "is_admin": false
  },
  "subscription": {
    "id": 1,
    "status": "active",
    "current_period_start": "2026-04-07T00:00:00Z",
    "current_period_end": "2026-05-07T00:00:00Z",
    "cancelled_at": null,
    "ends_at": null,
    "assignment_note": "Assigned default free plan.",
    "plan": {
      "id": 1,
      "code": "free",
      "name": "Free / Discovery"
    }
  },
  "usage": {
    "files": {
      "metric": "files",
      "limit": 10,
      "used": 0,
      "remaining": 10
    },
    "ai_questions": {
      "metric": "ai_questions",
      "limit": 25,
      "used": 0,
      "remaining": 25
    }
  },
  "token": "2|sanctum-token"
}
```

### Current User

`GET /api/auth/me`

Response:

```json
{
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "user",
    "is_admin": false
  },
  "subscription": {
    "id": 1,
    "status": "active",
    "current_period_start": "2026-04-07T00:00:00Z",
    "current_period_end": "2026-05-07T00:00:00Z",
    "cancelled_at": null,
    "ends_at": null,
    "assignment_note": "Assigned default free plan.",
    "plan": {
      "id": 1,
      "code": "free",
      "name": "Free / Discovery"
    }
  },
  "usage": {
    "files": {
      "metric": "files",
      "limit": 10,
      "used": 0,
      "remaining": 10
    },
    "ai_questions": {
      "metric": "ai_questions",
      "limit": 25,
      "used": 0,
      "remaining": 25
    }
  },
  "supermemory": {
    "configured": true,
    "container_tag": "user-1"
  }
}
```

## Plans And Usage

### List Available Plans

`GET /api/plans`

Response:

```json
{
  "plans": [
    {
      "id": 1,
      "code": "free",
      "name": "Free / Discovery",
      "description": "Light usage to help users adopt the product.",
      "price_cents": 0,
      "currency": "USD",
      "billing_interval_months": 1,
      "limits": {
        "monthly_files": 10,
        "daily_files": null,
        "monthly_questions": 25,
        "daily_questions": null,
        "max_pdf_pages": 10
      },
      "is_active": true,
      "sort_order": 1,
      "is_current": true
    },
    {
      "id": 2,
      "code": "pro",
      "name": "Pro / Unlimited-ish",
      "description": "Paid plan for heavier usage with abuse protection.",
      "price_cents": 999,
      "currency": "USD",
      "billing_interval_months": 1,
      "limits": {
        "monthly_files": 150,
        "daily_files": null,
        "monthly_questions": 400,
        "daily_questions": 50,
        "max_pdf_pages": 10
      },
      "is_active": true,
      "sort_order": 2,
      "is_current": false
    },
    {
      "id": 3,
      "code": "pro-annual",
      "name": "Pro / Annual",
      "description": "Annual Pro plan with the same usage limits billed at $100 per year.",
      "price_cents": 10000,
      "currency": "USD",
      "billing_interval_months": 12,
      "limits": {
        "monthly_files": 150,
        "daily_files": null,
        "monthly_questions": 400,
        "daily_questions": 50,
        "max_pdf_pages": 10
      },
      "is_active": true,
      "sort_order": 3,
      "is_current": false
    }
  ]
}
```

### Get Current Subscription

`GET /api/subscription`

Response body:

- `subscription.plan`: the current plan details
- `subscription.current_period_start`: start of the current billing cycle
- `subscription.current_period_end`: end of the current billing cycle
- `subscription.status`: current subscription status

### Get Current Usage

`GET /api/usage`

Response body:

- `usage.files`: uploaded document usage for the current user
- `usage.ai_questions`: combined OCR + memory search + document Q&A usage
- Each metric returns total billing-cycle usage plus an optional `daily` bucket when the plan defines a daily cap

### Billing Overview

`GET /api/billing/overview`

Returns a single payload for the account billing screen.

Response body:

- `subscription`: current subscription with plan details
- `usage`: current usage counters
- `plans`: active plans with `is_current`, `is_paid`, and `can_checkout`
- `billing.configured`: whether Stripe billing is configured on the backend
- `billing.portal_available`: whether the user already has a Stripe customer profile
- `billing.manages_current_subscription`: whether the active subscription is Stripe-managed

### Create PaymentSheet For Paid Upgrade

`POST /api/subscription/payment-sheet`

Use this to start a paid upgrade with Stripe PaymentSheet for Flutter Android/iOS.

Request:

```json
{
  "plan_id": 2
}
```

Response:

```json
{
  "payment_sheet": {
    "customer_id": "cus_test_123",
    "ephemeral_key_secret": "ek_test_123",
    "payment_intent_client_secret": "pi_123_secret_123",
    "subscription_id": "sub_test_123"
  }
}
```

Flutter should use this payload to initialize Stripe PaymentSheet in-app.

Notes:

- Free plans do not use this endpoint.
- Paid plans must be configured with a Stripe recurring `price` ID in the backend.
- If the current subscription is already managed by Stripe, use the billing portal instead of creating a new subscription bootstrap.
- Initialize Flutter Stripe with:
  - `paymentIntentClientSecret = payment_sheet.payment_intent_client_secret`
  - `customerId = payment_sheet.customer_id`
  - `customerEphemeralKeySecret = payment_sheet.ephemeral_key_secret`
- Configure the PaymentSheet `returnURL` in Flutter, for example `yourapp://stripe-redirect`.
- Present PaymentSheet inside the app instead of opening a browser or webview.
- The legacy endpoint `/api/subscription/checkout-session` currently points to the same mobile PaymentSheet flow for backward compatibility.

### Open Billing Portal

`POST /api/subscription/portal-session`

Use this for customers who already have a Stripe-managed subscription and need Stripe's hosted management UI.

Request:

```json
{
  "return_url": "https://app.example.com/account"
}
```

Response:

```json
{
  "portal": {
    "url": "https://billing.stripe.com/p/session/test"
  }
}
```

### Cancel Subscription

`POST /api/subscription/cancel`

Request:

```json
{
  "immediately": false
}
```

Behavior:

- `immediately=false` cancels at period end
- `immediately=true` requests immediate cancellation

Response returns the updated `subscription` object.

### Resume Subscription

`POST /api/subscription/resume`

Clears `cancel_at_period_end` for a Stripe-managed subscription.

Response returns the updated `subscription` object.

### Quota Exceeded Response

Endpoints that consume plan usage can return `429 Too Many Requests` when a plan limit is exhausted.

Example:

```json
{
  "message": "You have reached your billing cycle ai questions limit.",
  "detail": {
    "code": "USAGE_LIMIT_EXCEEDED",
    "metric": "ai_questions",
    "period": "billing_cycle",
    "limit": 25,
    "used": 25,
    "requested": 1,
    "remaining": 0,
    "reset_at": "2026-05-07T00:00:00Z",
    "plan": {
      "code": "free",
      "name": "Free / Discovery"
    }
  }
}
```

### Logout

`POST /api/auth/logout`

Response:

```json
{
  "message": "Logged out"
}
```

## Memories

### Add Memory

`POST /api/memories`

Request:

```json
{
  "text": "User summary of the uploaded contract",
  "custom_id": "note-123",
  "entity_context": "Personal notes about project Phoenix",
  "metadata": {
    "category": "notes",
    "screen": "capture"
  }
}
```

Response:

```json
{
  "memory": {
    "id": "mem_abc123",
    "status": "processed",
    "container_tag": "user-1",
    "custom_id": "note-123",
    "metadata": {
      "category": "notes",
      "screen": "capture",
      "source": "memory",
      "uploaded_by_user_id": 1
    }
  }
}
```

Use this endpoint for user-written content, summaries, chat takeaways, and other extracted memory content.

## Documents

### Upload Documents

`POST /api/documents`

Content type:

```text
multipart/form-data
```

Fields:

- `files[]`: one or more files
- optional `custom_id`
- optional `entity_context`
- optional `metadata[...]`
- optional `summary`
- optional `summary_custom_id`
- optional `summary_entity_context`
- optional `summary_metadata[...]`

Response:

```json
{
  "documents": [
    {
      "id": "doc_file_1",
      "status": "queued",
      "name": "contract.pdf",
      "container_tag": "user-1",
      "custom_id": "upload-1",
      "metadata": {
        "source": "file",
        "original_name": "contract.pdf",
        "mime_type": "application/pdf",
        "uploaded_by_user_id": 1
      }
    }
  ],
  "summary_memory": {
    "id": "mem_summary_1",
    "status": "processed",
    "container_tag": "user-1",
    "custom_id": "upload-1-summary",
    "metadata": {
      "source": "document_summary",
      "uploaded_by_user_id": 1,
      "linked_document_id": "doc_file_1",
      "original_name": "contract.pdf"
    }
  }
}
```

Notes:

- For multiple files, Laravel uploads each file separately to Supermemory.
- If you send one `custom_id` with multiple files, Laravel appends `-1`, `-2`, and so on to keep them unique.
- `summary` can only be used when uploading exactly one file.
- PDF uploads are limited by plan. The current Free and Pro plans both reject PDFs above 10 pages.
- When `summary` is present, Laravel uploads the file as a document first, then stores the summary as memory for faster memory-style retrieval later.
- Supermemory processing is asynchronous, so use the returned document IDs for polling.
- Laravel creates a user-related status record for every uploaded file and every created memory.
- Every successfully uploaded document consumes the `files` quota for the current billing cycle.

### Status Tracking and Firestore Sync

Laravel stores ingestion tracking rows in MySQL (`supermemory_ingestions`) linked to the authenticated user.

Tracked fields include:

- Supermemory id (`supermemory_id`) for each file/memory
- Current status (`supermemory_status`)
- Source type (`memory` or `document`)
- Source name (uploaded filename for documents)

Firestore mirror path:

- `user_ingestions/{userId}/items/{trackingId}`

Sync behavior:

- Memory status is saved on create and treated as final.
- Document status is refreshed every minute by Laravel scheduler (`supermemory:sync-statuses`).
- On status changes, Laravel updates MySQL and upserts the Firestore item.

Server cron requirement:

```bash
* * * * * cd /var/www/foobar.net1 && php artisan schedule:run >> /dev/null 2>&1
```

### List Documents

`GET /api/documents?page=1&limit=10&sort=createdAt&order=desc`

Response:

```json
{
  "data": [
    {
      "id": "doc_abc123",
      "status": "done",
      "title": "Meeting notes",
      "type": "text",
      "content": null,
      "summary": null,
      "custom_id": "note-123",
      "metadata": {
        "source": "text"
      },
      "container_tags": ["user-1"],
      "created_at": "2026-04-02T10:00:00Z",
      "updated_at": "2026-04-02T10:00:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "limit": 10,
    "pagination": null
  }
}
```

### Get Document Status and Details

`GET /api/documents/{id}`

Use this to poll processing state after ingestion.

Response:

```json
{
  "document": {
    "id": "doc_abc123",
    "status": "processing",
    "title": "Meeting notes",
    "type": "text",
    "content": "Raw content when available",
    "summary": "Short summary when available",
    "custom_id": "note-123",
    "metadata": {
      "source": "text"
    },
    "container_tags": ["user-1"],
    "created_at": "2026-04-02T10:00:00Z",
    "updated_at": "2026-04-02T10:05:00Z"
  }
}
```

Typical Supermemory statuses:

- `queued`
- `extracting`
- `chunking`
- `embedding`
- `done`
- `failed`

Recommended mobile flow:

1. If the user writes a summary or note directly, call `/api/memories`.
2. If the user uploads a file, call `/api/documents`.
3. If the file already has a user summary, send that summary in the same `/api/documents` request so Laravel also stores it as memory.
4. Poll `/api/documents/{id}` until `status == "done"` or `status == "failed"`.
5. Use memory search for summary-style recall and document search for source-grounded retrieval.

For page-image OCR that previously called Gemini directly from Flutter, use the Laravel OCR endpoint described in `docs/flutter-gemini-ocr-api.md`.

## Search

### Search Memories

`POST /api/search/memories`

Response body:

- `results`: array of memory matches
- `results[].id`: memory identifier
- `results[].content`: memory text to show or reuse
- `results[].score`: relevance score when available
- `results[].metadata`: memory metadata object
- `meta.search_mode`: always `memories`
- `meta.upstream_search_mode`: currently `hybrid`
- `meta.limit`: applied result limit
- `meta.threshold`: applied threshold or `null`
- `meta.rerank`: whether reranking was enabled
- `meta.container_tag`: current user's server-side container tag
- `meta.total`: number of returned results
- `meta.timing`: upstream timing when available

This endpoint ignores `conversationHistory` and behaves as a direct retrieval call.

Request:

```json
{
  "query": "What did I say the contract summary was?",
  "limit": 5,
  "threshold": 0.6,
  "rerank": true
}
```

Response:

```json
{
  "results": [
    {
      "id": "mem_abc123",
      "score": 0.91,
      "content": "The contract renews automatically unless notice is given.",
      "metadata": {
        "source": "document_summary",
        "linked_document_id": "doc_abc123"
      }
    }
  ],
  "meta": {
    "search_mode": "memories",
    "upstream_search_mode": "hybrid",
    "limit": 5,
    "threshold": 0.6,
    "rerank": true,
    "container_tag": "user-1",
    "total": 1,
    "timing": 87
  }
}
```

Notes:

- Use this endpoint for summaries, user notes, extracted takeaways, and other memory-style recall.
- Every successful call consumes one `ai_questions` unit from the current plan.

### Search Documents

`POST /api/search/documents`

Response body:

- `answer`: final Gemini answer for the user
- `meta.search_mode`: always `documents`
- `meta.response_mode`: always `answer_only`
- `meta.upstream_search_mode`: currently `hybrid`
- `meta.limit`: applied result limit
- `meta.threshold`: applied threshold or `null`
- `meta.rerank`: whether reranking was enabled
- `meta.container_tag`: current user's server-side container tag
- `meta.model`: Gemini model used to produce the answer
- `meta.context_items`: number of retrieval context segments sent to Gemini
- `meta.conversation_history_items`: number of prior chat messages forwarded to Gemini
- `meta.file_references`: unique file names from search results with app link tokens
- `meta.no_context`: `true` when no relevant retrieval context was found and a fallback answer was returned
- `meta.timing`: upstream timing when available

Every successful call consumes one `ai_questions` unit from the current plan, even when the response falls back because no relevant context was found.

Request:

```json
{
  "query": "What does the contract say about renewal?",
  "limit": 5,
  "threshold": 0.6,
  "rerank": true,
  "conversationHistory": [
    {
      "role": "user",
      "content": "Which document are we discussing?"
    },
    {
      "role": "assistant",
      "content": "We are discussing the service agreement."
    }
  ]
}
```

Response:

```json
{
  "answer": "The contract renews automatically unless either party sends notice before the renewal window.",
  "meta": {
    "search_mode": "documents",
    "response_mode": "answer_only",
    "upstream_search_mode": "hybrid",
    "limit": 5,
    "threshold": 0.6,
    "rerank": true,
    "container_tag": "user-1",
    "model": "gemini-2.5-flash",
    "context_items": 4,
    "conversation_history_items": 2,
    "file_references": [
      {
        "original_name": "receipt_1775657557025.pdf",
        "link": "app-file://receipt_1775657557025.pdf"
      }
    ],
    "no_context": false,
    "timing": 87
  }
}
```

No-context response:

```json
{
  "answer": "I could not find relevant information in your documents or memories yet. Please add more content and try again.",
  "meta": {
    "search_mode": "documents",
    "response_mode": "answer_only",
    "upstream_search_mode": "hybrid",
    "limit": 5,
    "threshold": null,
    "rerank": false,
    "container_tag": "user-1",
    "model": "gemini-2.5-flash",
    "context_items": 0,
    "conversation_history_items": 0,
    "file_references": [],
    "no_context": true,
    "timing": 12
  }
}
```

Notes:

- Flutter should read `answer` as the final response for the user.
- This endpoint uses hybrid retrieval context (documents plus memories) and then returns only the LLM answer.
- When a filename is present in context, Gemini is instructed to return markdown links in this format: `[receipt_1775657557025.pdf](app-file://receipt_1775657557025.pdf)`.
- Flutter should parse `app-file://...` links, decode the filename, then map it to your real download/view URL.
- Send the last few user and assistant messages in `conversationHistory` for follow-up questions.
- Use only `user` and `assistant` roles in `conversationHistory`.
- If no relevant context is found, Laravel returns a graceful fallback answer with `meta.no_context = true`.
- Both search endpoints call Supermemory with `searchMode=hybrid`; Laravel filters and normalizes the response per endpoint.
- Laravel search integration uses Supermemory `POST /v4/search` upstream.
- Search is always scoped to the current user’s tag on the server.

## System Endpoints

### Public Health

`GET /api/system/health`

Response:

```json
{
  "laravel": "ok",
  "supermemory": {
    "reachable": true,
    "health": {
      "documents": []
    }
  }
}
```

### Authenticated Connection Check

`GET /api/system/connection`

Response:

```json
{
  "data": {
    "connected": true,
    "container_tag": "user-1",
    "document_count": 3
  }
}
```

## Removed Endpoints

These routes no longer exist and should be removed from Flutter:

- `GET /api/session/status`
- `POST /api/session/restore`
- `GET /api/datasets`
- `POST /api/datasets`
- `GET /api/datasets/status`
- `POST /api/cognify`
- `POST /api/memify`
- `GET /api/search/history`

## Recommended Flutter Client Structure

Use a single API client with bearer-token injection.

### Example with Dio

```dart
import 'package:dio/dio.dart';

class ApiClient {
  ApiClient(this._tokenProvider)
      : dio = Dio(BaseOptions(
          baseUrl: 'http://your-server/api',
          headers: {'Accept': 'application/json'},
        )) {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenProvider();

          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }

          handler.next(options);
        },
      ),
    );
  }

  final Dio dio;
  final Future<String?> Function() _tokenProvider;
}
```

### Auth Service Example

```dart
class AuthApi {
  AuthApi(this.client);

  final ApiClient client;

  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await client.dio.post('/auth/login', data: {
      'email': email,
      'password': password,
    });

    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> me() async {
    final response = await client.dio.get('/auth/me');
    return response.data as Map<String, dynamic>;
  }
}
```

### Add Memory Example

```dart
Future<Map<String, dynamic>> addMemory({
  required String text,
  String? customId,
  String? entityContext,
}) async {
  final response = await client.dio.post('/memories', data: {
    'text': text,
    'custom_id': customId,
    'entity_context': entityContext,
  });

  return response.data as Map<String, dynamic>;
}
```

### Upload Files Example

```dart
import 'package:dio/dio.dart';

Future<Map<String, dynamic>> uploadFiles({
  required List<String> paths,
  String? customId,
  String? entityContext,
  String? summary,
  String? summaryCustomId,
  String? summaryEntityContext,
}) async {
  final formData = FormData();

  for (final path in paths) {
    formData.files.add(
      MapEntry(
        'files[]',
        await MultipartFile.fromFile(path),
      ),
    );
  }

  if (customId != null) {
    formData.fields.add(MapEntry('custom_id', customId));
  }

  if (entityContext != null) {
    formData.fields.add(MapEntry('entity_context', entityContext));
  }

  if (summary != null) {
    formData.fields.add(MapEntry('summary', summary));
  }

  if (summaryCustomId != null) {
    formData.fields.add(MapEntry('summary_custom_id', summaryCustomId));
  }

  if (summaryEntityContext != null) {
    formData.fields.add(MapEntry('summary_entity_context', summaryEntityContext));
  }

  final response = await client.dio.post('/documents', data: formData);
  return response.data as Map<String, dynamic>;
}
```

### Poll Document Example

```dart
Future<Map<String, dynamic>> document(String documentId) async {
  final response = await client.dio.get('/documents/$documentId');
  return response.data as Map<String, dynamic>;
}
```

### Search Memories Example

```dart
Future<Map<String, dynamic>> searchMemories({
  required String query,
  int limit = 5,
  double? threshold,
  bool rerank = false,
}) async {
  final response = await client.dio.post('/search/memories', data: {
    'query': query,
    'limit': limit,
    'threshold': threshold,
    'rerank': rerank,
  });

  return response.data as Map<String, dynamic>;
}
```

### Search Documents Example

```dart
Future<Map<String, dynamic>> searchDocuments({
  required String query,
  int limit = 5,
  double? threshold,
  bool rerank = false,
  List<Map<String, String>> conversationHistory = const [],
}) async {
  final response = await client.dio.post('/search/documents', data: {
    'query': query,
    'limit': limit,
    'threshold': threshold,
    'rerank': rerank,
    'conversationHistory': conversationHistory,
  });

  return response.data as Map<String, dynamic>;
}
```

### Frontend File Link Resolution (Flutter)

Use this when rendering assistant messages from `/api/search/documents`.

1. Read `answer` (markdown text) and `meta.file_references`.
2. Find markdown links where URL starts with `app-file://`.
3. Decode the filename from that URL.
4. Resolve that filename to your real backend file URL (or route) and replace the temporary URL before rendering.

```dart
final appFileLinkPattern = RegExp(r'\[([^\]]+)\]\((app-file://[^)]+)\)');

String resolveAppFileLinks({
  required String markdown,
  required List<dynamic> fileReferences,
  required String Function(String originalName) buildFileUrl,
}) {
  final available = <String>{
    for (final item in fileReferences)
      if (item is Map<String, dynamic> && item['original_name'] is String)
        item['original_name'] as String,
  };

  return markdown.replaceAllMapped(appFileLinkPattern, (match) {
    final label = match.group(1)!;
    final appUrl = match.group(2)!;
    final encodedName = appUrl.replaceFirst('app-file://', '');
    final originalName = Uri.decodeComponent(encodedName);

    if (!available.contains(originalName)) {
      return label;
    }

    final resolvedUrl = buildFileUrl(originalName);

    return '[$label]($resolvedUrl)';
  });
}
```

Example usage after API call:

```dart
final data = await searchDocuments(query: 'show receipt total');
final answer = (data['answer'] ?? '') as String;
final meta = (data['meta'] ?? <String, dynamic>{}) as Map<String, dynamic>;
final fileReferences = (meta['file_references'] ?? const <dynamic>[]) as List<dynamic>;

final hydratedMarkdown = resolveAppFileLinks(
  markdown: answer,
  fileReferences: fileReferences,
  buildFileUrl: (name) => 'https://api.your-domain.com/api/documents/by-name/${Uri.encodeComponent(name)}',
);
```

## Error Handling

Validation errors still use Laravel `422` responses.

## Admin Billing APIs

These endpoints require an authenticated admin user.

### List Plans For Admin

`GET /api/admin/plans`

Query params:

- `per_page`
- `active_only`

Each plan includes:

- `stripe_price_id`
- `active_subscriptions_count`
- `checkout_ready`

### Create Or Update Plans

- `POST /api/admin/plans`
- `PATCH /api/admin/plans/{plan}`

Admins can manage plan pricing, limits, active state, ordering, and the Stripe recurring `stripe_price_id` used for checkout.

### List Subscriptions For Admin

`GET /api/admin/subscriptions`

Query params:

- `status`
- `plan_id`
- `user_id`
- `search`
- `per_page`

Each item includes:

- subscription details
- plan details
- user summary
- Stripe IDs when present

### Review A User's Subscription History

`GET /api/admin/users/{user}/subscription`

Returns:

- `user`
- `current_subscription`
- recent `subscriptions`

### Manual Admin Assignment

`PUT /api/admin/users/{user}/subscription`

This still works for manual overrides and internal account management even with Stripe enabled.

### Stripe Webhook

`POST /api/stripe/webhook`

Stripe should send subscription lifecycle events here. The backend verifies the Stripe signature and syncs subscription state into the local `subscriptions` table.

Upstream Supermemory errors use this normalized shape:

```json
{
  "message": "Supermemory API error",
  "detail": "upstream message or JSON payload"
}
```

If the backend is not configured with `SUPERMEMORY_API_KEY`, the API returns:

```json
{
  "message": "Supermemory API is not configured",
  "detail": {
    "code": "SUPERMEMORY_NOT_CONFIGURED",
    "hint": "Set SUPERMEMORY_API_KEY in the environment before using this endpoint."
  }
}
```

Recommended Flutter behavior:

- on `401`: send the user to login
- on `404` for `/api/documents/{id}`: treat the document as unavailable or not owned by the current user
- on `422`: show form validation errors
- on `503`: show a temporary service-unavailable state

## Migration Checklist For Flutter

1. Remove every screen, repository, and DTO tied to datasets, cognify, memify, session status, and session restore.
2. Replace dataset polling with document polling via `/api/documents/{id}`.
3. Send user-generated summaries and notes to `/api/memories`, not `/api/documents`.
4. Send uploaded files to `/api/documents`, and include `summary` in that same request when you want Laravel to also save the summary as memory.
5. Replace the old generic `/api/search` call with `/api/search/memories` or `/api/search/documents` depending on the UI flow.
6. Update result parsing so memory search reads `results[i].content` as the memory text, while document search reads `answer` (and optionally `meta.no_context`) from the response.

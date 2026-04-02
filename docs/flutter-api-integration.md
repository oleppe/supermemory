# Flutter API Integration Guide

This document explains how to connect the Flutter app to the Laravel API layer that proxies Cognee.

## Overview

The Flutter app should talk only to Laravel.

Flow:

1. Flutter authenticates with Laravel using Sanctum bearer tokens.
2. Laravel stores and manages the internal Cognee session cookie.
3. Flutter sends `Authorization: Bearer <token>` on protected requests.
4. Laravel forwards the request to Cognee when needed and normalizes the response.

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
    "name": "user"
  },
  "token": "1|sanctum-token"

  ## Memify

  `POST /api/memify`

  Use this endpoint to enrich an existing knowledge graph without re-uploading source files.

  When to use it:

  - add derived facts or enrichments to an already processed dataset
  - run targeted enrichment against specific graph nodes
  - experiment with custom extraction and enrichment tasks over existing graph data

  Do not use it as the default replacement for uploading a new file. New source files should still go through ingestion and then cognify.

  Request:

  ```json
  {
    "dataset_name": "Docs",
    "extraction_tasks": ["ExtractTopics"],
    "enrichment_tasks": ["SummarizeCommunities"],
    "node_name": ["Project Phoenix"],
    "run_in_background": true
  }
  ```

  Response:

  ```json
  {
    "result": {
      "pipeline_run_id": "run-2"
    }
  }
  ```

  Notes:

  - send either `dataset_id` or `dataset_name`
  - `data` is optional and lets you pass direct text into memify
  - when `data` is omitted, Cognee can enrich the existing graph for that dataset
  - for schema or ontology changes that require re-reading old source documents, prefer an explicit rebuild flow instead of memify
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
    "name": "user"
  },
  "token": "2|sanctum-token"
}
```

### Current User

`GET /api/auth/me`

Headers:

```text
Authorization: Bearer <token>
Accept: application/json
```

Response:

```json
{
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "user"
  },
  "cognee": {
    "id": "uuid",
    "email": "user@example.com"
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

## Session State

### Check App + Cognee Session

`GET /api/session/status`

Use this endpoint when the app starts, returns from background, or receives a Cognee-related `401`.

Response shape:

```json
{
  "auth": {
    "authenticated": true,
    "user_id": 1
  },
  "cognee": {
    "authenticated": true,
    "status": "active",
    "profile": {
      "id": "uuid"
    }
  }
}
```

Possible `cognee.status` values:

- `active`
- `expired`
- `missing`
- `requires_credentials`
- `unavailable`

Recommended Flutter handling:

- `active`: continue normally
- `expired`: retry the request once after calling `/api/session/status`; if it stays expired, call `/api/session/restore`
- `missing`: treat as logged out from Cognee, usually re-login
- `requires_credentials`: prompt for the user's password and call `/api/session/restore` without clearing the Sanctum token
- `unavailable`: show retry / upstream unavailable state

The response also includes `cognee.can_restore_automatically`:

- `true`: Laravel already has a stored Cognee password and can recreate the Cognee session automatically
- `false`: Flutter should prompt for the password and call `/api/session/restore`

### Restore Cognee Session Without Logging Out of Sanctum

`POST /api/session/restore`

Use this when the Sanctum token is still valid but Cognee needs to be re-authenticated.

Request when Laravel already has a stored Cognee password:

```json
{}
```

Request when this is an older account and Laravel does not yet have a stored Cognee password:

```json
{
  "password": "secret123"
}
```

Response:

```json
{
  "message": "Cognee session restored",
  "cognee": {
    "authenticated": true,
    "status": "active",
    "can_restore_automatically": true,
    "profile": {
      "id": "uuid"
    }
  }
}
```

## Datasets

### List Datasets

`GET /api/datasets`

Response:

```json
{
  "data": [
    {
      "id": "123e4567-e89b-12d3-a456-426614174000",
      "name": "Docs"
    }
  ]
}
```

### Create Dataset

`POST /api/datasets`

Request:

```json
{
  "name": "Docs"
}
```

Response:

```json
{
  "data": {
    "id": "123e4567-e89b-12d3-a456-426614174000",
    "name": "Docs"
  }
}
```

### Get Processing Status

`GET /api/datasets/status?dataset_ids[0]=123e4567-e89b-12d3-a456-426614174000`

Response:

```json
{
  "data": {
    "123e4567-e89b-12d3-a456-426614174000": {
      "upstream": "DATASET_PROCESSING_STARTED",
      "state": "processing"
    }
  }
}
```

Mapped `state` values:

- `queued`
- `processing`
- `completed`
- `failed`
- `unknown`

## Ingestion

You can ingest either plain text or files.

Important rule:

- send either `dataset_id`
- or `dataset_name`
- not neither

If you send `dataset_name`, Laravel will create or resolve that dataset before calling Cognee.

By default, Laravel also triggers `cognify` automatically after a successful ingestion request so the dataset starts processing immediately.

### Ingest Text

`POST /api/ingestion/text`

Request:

```json
{
  "text": "This is the document content",
  "dataset_name": "Docs",
  "node_set": ["team-a", "notes"],
  "run_cognify": true,
  "run_in_background": true
}
```

Note: text ingestion no longer accepts a `filename` field.

Response:

```json
{
  "dataset": {
    "id": "123e4567-e89b-12d3-a456-426614174000",
    "name": "Docs"
  },
  "result": {
    "status": "added"
  },
  "cognify": {
    "triggered": true,
    "result": {
      "pipeline_run_id": "run-1"
    }
  }
}
```

### Ingest Files

`POST /api/ingestion/files`

Content type:

```text
multipart/form-data
```

Fields:

- `files[]`: one or more files
- `dataset_id` or `dataset_name`
- optional `node_set[]`
- optional `run_cognify` (defaults to `true`)
- optional `run_in_background` (defaults to `true`)
- optional `custom_prompt`
- optional `ontology_key[]`

File uploads are automatically converted to plain text and forwarded through the same text-based `addData` path.

Response:

```json
{
  "dataset": {
    "id": "123e4567-e89b-12d3-a456-426614174000"
  },
  "result": {
    "status": "added"
  },
  "cognify": {
    "triggered": true,
    "result": {
      "pipeline_run_id": "run-2"
    }
  }
}
```

If you need to upload data without starting processing immediately, send:

```json
{
  "run_cognify": false
}
```

In that case the response contains:

```json
{
  "cognify": {
    "triggered": false,
    "reason": "disabled"
  }
}
```

## Cognify

`POST /api/cognify`

Request:

```json
{
  "dataset_name": "Docs",
  "run_in_background": true,
  "custom_prompt": "Extract the important entities and relations",
  "ontology_key": ["schema"]
}
```

Response:

```json
{
  "dataset": {
    "id": "123e4567-e89b-12d3-a456-426614174000",
    "name": "Docs"
  },
  "result": {
    "pipeline_run_id": "run-1"
  }
}
```

Recommended mobile flow:

1. ingest text or files
2. read `cognify.triggered`
3. if true, poll `/api/datasets/status`
4. if false, call `/api/cognify` manually when ready
5. enable search when state becomes `completed`

## Search

### Execute Search

`POST /api/search`

Request:

```json
{
  "query": "What is in the dataset?",
  "dataset_name": "Docs",
  "search_type": "GRAPH_COMPLETION",
  "top_k": 10,
  "only_context": false
}
```

Allowed `search_type` values in this API layer:

- `GRAPH_COMPLETION`
- `CHUNKS`
- `RAG_COMPLETION`

Response:

```json
{
  "results": [
    {
      "search_result": "Answer text",
      "dataset_id": "123e4567-e89b-12d3-a456-426614174000",
      "dataset_name": "Docs"
    }
  ],
  "meta": {
    "search_type": "GRAPH_COMPLETION",
    "top_k": 10
  }
}
```

If the dataset has uploaded data but has not been processed yet, this endpoint returns a normalized Laravel error instead of Cognee's raw `404`:

```json
{
  "message": "Search requires cognify to be run first",
  "detail": {
    "code": "SEARCH_REQUIRES_COGNIFY",
    "action": "run_cognify",
    "dataset_name": "Docs",
    "hint": "Dataset has data, but the knowledge graph is empty. Run cognify before searching."
  }
}
```

Recommended Flutter handling:

1. show a message that processing is required
2. call `/api/cognify` for that dataset
3. poll `/api/datasets/status`
4. retry search after the dataset reaches `completed`

### Search History

`GET /api/search/history`

Response:

```json
{
  "data": [
    {
      "id": "history-1",
      "text": "previous query"
    }
  ]
}
```

## System Endpoints

### Public Health

`GET /api/system/health`

Response when Cognee is reachable:

```json
{
  "laravel": "ok",
  "cognee": {
    "reachable": true,
    "health": {
      "status": "ok"
    }
  }
}
```

Response when Cognee is unavailable:

```json
{
  "laravel": "ok",
  "cognee": {
    "reachable": false,
    "error": "down"
  }
}
```

### Authenticated Connection Check

`GET /api/system/connection`

Response:

```json
{
  "data": {
    "connected": true
  }
}
```

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

  Future<Map<String, dynamic>> sessionStatus() async {
    final response = await client.dio.get('/session/status');
    return response.data as Map<String, dynamic>;
  }
}
```

### Ingest Text Example

```dart
Future<Map<String, dynamic>> ingestText({
  required String text,
  required String datasetName,
  List<String> nodeSet = const [],
  bool runCognify = true,
  bool runInBackground = true,
}) async {
  final response = await client.dio.post('/ingestion/text', data: {
    'text': text,
    'dataset_name': datasetName,
    'node_set': nodeSet,
    'run_cognify': runCognify,
    'run_in_background': runInBackground,
  });

  return response.data as Map<String, dynamic>;
}
```

### Upload Files Example

```dart
import 'package:dio/dio.dart';

Future<Map<String, dynamic>> uploadFiles({
  required List<String> paths,
  String? datasetId,
  String? datasetName,
  bool runCognify = true,
  bool runInBackground = true,
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

  if (datasetId != null) formData.fields.add(MapEntry('dataset_id', datasetId));
  if (datasetName != null) formData.fields.add(MapEntry('dataset_name', datasetName));
  formData.fields.add(MapEntry('run_cognify', runCognify.toString()));
  formData.fields.add(MapEntry('run_in_background', runInBackground.toString()));

  final response = await client.dio.post('/ingestion/files', data: formData);
  return response.data as Map<String, dynamic>;
}
```

### Cognify Example

```dart
Future<Map<String, dynamic>> cognify({
  required String datasetName,
  bool runInBackground = true,
  String? customPrompt,
}) async {
  final response = await client.dio.post('/cognify', data: {
    'dataset_name': datasetName,
    'run_in_background': runInBackground,
    'custom_prompt': customPrompt,
  });

  return response.data as Map<String, dynamic>;
}
```

### Poll Status Example

```dart
Future<Map<String, dynamic>> datasetStatus(String datasetId) async {
  final response = await client.dio.get('/datasets/status', queryParameters: {
    'dataset_ids': [datasetId],
  });

  return response.data as Map<String, dynamic>;
}
```

### Search Example

```dart
Future<Map<String, dynamic>> search({
  required String query,
  required String datasetName,
}) async {
  final response = await client.dio.post('/search', data: {
    'query': query,
    'dataset_name': datasetName,
    'search_type': 'GRAPH_COMPLETION',
    'top_k': 10,
    'only_context': false,
  });

  return response.data as Map<String, dynamic>;
}
```

## Error Handling

The API returns Laravel validation errors as `422`.

Example:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "dataset_name": [
      "The dataset name field is required when dataset id is not present."
    ]
  }
}
```

Cognee-proxy errors use this shape:

```json
{
  "message": "Cognee session expired",
  "detail": "COGNEE_SESSION_EXPIRED"
}
```

When Laravel cannot auto-restore the Cognee session yet, the error becomes:

```json
{
  "message": "Cognee session could not be restored automatically",
  "detail": {
    "code": "COGNEE_SESSION_RECOVERY_UNAVAILABLE",
    "action": "session_restore",
    "hint": "Call /api/session/restore while the Sanctum session is still valid."
  }
}
```

Recommended Flutter behavior:

- on `401` with `detail = COGNEE_SESSION_EXPIRED`: call `/api/session/status`; if status is `requires_credentials`, prompt for password and call `/api/session/restore`; otherwise retry once or send user to login
- on `401` with `detail.code = COGNEE_SESSION_RECOVERY_UNAVAILABLE`: prompt for password and call `/api/session/restore` without clearing the Sanctum token
- on `409` with `detail.code = SEARCH_REQUIRES_COGNIFY`: trigger cognify flow instead of showing a generic error
- on `422`: show field errors in the current form
- on `503`: show temporary service-unavailable state

## Suggested Integration Order

1. implement login and token persistence
2. call `/api/session/status` on app startup
3. add dataset list/create flow
4. add text ingestion
5. add cognify trigger + status polling
6. add search
7. add file upload and search history

## Notes

- All protected endpoints require the Laravel Sanctum bearer token.
- Flutter should never send or manage the Cognee cookie directly.
- For ingestion and search, prefer `dataset_id` when you already have it.
- For first-time flows, `dataset_name` is simpler because Laravel can create the dataset.

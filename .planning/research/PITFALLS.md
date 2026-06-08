# Domain Pitfalls

**Domain:** User Panel Implementation for Mobile-First Laravel API
**Project:** Supermemory Document Management Web Interface
**Researched:** 2026-06-08
**Confidence:** HIGH (based on codebase analysis)

## Critical Pitfalls

Mistakes that cause rewrites, security vulnerabilities, or major user-facing issues.

### Pitfall 1: SPA Authentication Token Management

**What goes wrong:** Token loss on page refresh, silent logout during use, or tokens persisting after logout across tabs.

**Why it happens:** 
- Current `useApi.ts` reads token from cookie on each request, but there's no reactive state management
- No token refresh mechanism (Sanctum configured with `expiration: null`)
- Cookie not configured with proper security flags (HttpOnly, Secure, SameSite)
- Multiple browser tabs can desynchronize auth state

**Consequences:** 
- Users lose work mid-session when token expires or is cleared
- Security vulnerability if token stored in localStorage (XSS attack vector)
- Confusing UX where some requests succeed while others fail with 401

**Prevention:**
```typescript
// ❌ BAD: Token in localStorage (XSS vulnerable)
localStorage.setItem('token', token)

// ✅ GOOD: HttpOnly cookie set by backend
// In AuthController.php:
Cookie::make('accessToken', $token, [
    'httpOnly' => true,
    'secure' => true,
    'sameSite' => 'strict',
    'path' => '/',
]);

// ✅ GOOD: Reactive auth state in frontend
const authStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isAuthenticated = computed(() => !!user.value)
  
  async function refreshUser() {
    try {
      const { data } = await $api.get('/auth/me')
      user.value = data.user
    } catch {
      user.value = null
    }
  }
  
  return { user, isAuthenticated, refreshUser }
})
```

**Detection:** 
- Test: Open app in two tabs, logout in one, verify other tab redirects to login
- Test: Refresh page after login, verify authenticated state persists
- Monitor: 401 errors in frontend logs after successful login

---

### Pitfall 2: File Upload Timeout and Partial Upload Failures

**What goes wrong:** Large file uploads fail silently, corrupt files are processed, or users see success but file never reaches Supermemory.

**Why it happens:**
- Current `SupermemoryService::uploadFile()` is synchronous with 30s timeout
- No validation that Supermemory actually received/processed the file
- No retry mechanism for transient network failures
- Base64 encoding doubles payload size (10MB file = 20MB request)
- PHP `post_max_size` and `upload_max_filesize` not configured for large uploads

**Consequences:**
- Users upload 50MB PDF, wait 2 minutes, see "success" but document never appears
- Partial uploads corrupt Supermemory index
- Wasted API calls and user frustration
- Rate limit consumed for failed uploads

**Prevention:**
```php
// ❌ BAD: Synchronous upload with no validation
public function storeDocuments(Request $request) {
    foreach ($files as $file) {
        $document = $this->supermemoryService->uploadFile($file, $containerTag);
        // No validation that upload succeeded
    }
}

// ✅ GOOD: Async processing with validation and retry
public function storeDocuments(Request $request) {
    $jobs = [];
    
    foreach ($files as $file) {
        // Store locally first
        $path = $file->store("uploads/{$user->id}");
        
        // Queue for async processing
        $job = new ProcessDocumentUpload($user->id, $path, $containerTag);
        $job->delay(now()->addSeconds(5)); // Avoid rate limits
        $jobs[] = $job;
    }
    
    dispatch($jobs);
    
    return response()->json([
        'message' => 'Uploads queued for processing',
        'count' => count($files),
    ], 202);
}

// In ProcessDocumentUpload job:
public function handle() {
    try {
        $result = $this->supermemoryService->uploadFile(/* ... */);
        
        // Validate response
        if (empty($result['id'])) {
            throw new \Exception('Supermemory returned empty document ID');
        }
        
        // Store ingestion record
        SupermemoryIngestion::create([
            'user_id' => $this->userId,
            'supermemory_id' => $result['id'],
            'status' => $result['status'] ?? 'queued',
            // ...
        ]);
        
    } catch (\Exception $e) {
        // Retry with exponential backoff
        if ($this->attempts() < 3) {
            $this->release(60 * $this->attempts());
        } else {
            // Mark as failed and notify user
            $this->markAsFailed($e);
        }
    }
}
```

**Detection:**
- Monitor Supermemory API response times (P95 > 10s = problem)
- Track upload success rate (should be > 98%)
- Log orphaned local files (uploaded but not in Supermemory)
- Alert on queue job failures

---

### Pitfall 3: OCR Processing Blocks Request and Times Out

**What goes wrong:** User uploads document for OCR analysis, request hangs for 30+ seconds, then times out with generic error.

**Why it happens:**
- `OcrController::analyze()` calls `GeminiService::analyzeDocumentImages()` synchronously
- Gemini API timeout is 30s, but large PDFs can take 60-120s
- Base64 encoding multiple pages creates massive request payload
- No progress feedback to user during processing
- Rate limiter (20/min) easily exhausted with slow requests

**Consequences:**
- User sees "504 Gateway Timeout" or "Request failed" with no explanation
- PHP-FPM worker blocked for 30s per request (exhausts worker pool)
- User retries upload, consuming more rate limit
- Poor UX: no indication if OCR is working or stuck

**Prevention:**
```php
// ❌ BAD: Synchronous OCR processing
public function analyze(Request $request) {
    $data = $this->geminiService->analyzeDocumentImages($pages, $categories);
    return response()->json(['data' => $data]);
}

// ✅ GOOD: Async processing with polling
public function analyze(Request $request) {
    $job = new AnalyzeDocumentOcr(
        userId: $user->id,
        pages: $pages,
        categories: $request->validated('allowed_categories'),
    );
    
    $batchId = Str::uuid();
    
    dispatch($job->onQueue('ocr')->withBatchId($batchId));
    
    return response()->json([
        'batch_id' => $batchId,
        'status' => 'processing',
        'estimated_seconds' => 30,
    ], 202);
}

// Separate endpoint for polling
public function analyzeStatus(string $batchId) {
    $job = OcrJob::where('batch_id', $batchId)->firstOrFail();
    
    return response()->json([
        'status' => $job->status, // processing, completed, failed
        'progress' => $job->progress, // 0-100
        'result' => $job->status === 'completed' ? $job->result : null,
        'error' => $job->status === 'failed' ? $job->error : null,
    ]);
}
```

```typescript
// ✅ Frontend polling with progress
const startOcr = async (files: File[]) => {
  const formData = new FormData()
  files.forEach(f => formData.append('pages[]', f))
  
  const { batch_id } = await $api.post('/ocr/analyze', formData)
  
  // Poll for status
  const poll = setInterval(async () => {
    const { status, progress, result } = await $api.get(`/ocr/status/${batch_id}`)
    
    progressBar.value = progress
    
    if (status === 'completed') {
      clearInterval(poll)
      ocrResult.value = result
    } else if (status === 'failed') {
      clearInterval(poll)
      showError(result.error)
    }
  }, 2000)
}
```

**Detection:**
- Monitor PHP-FPM slow log (requests > 10s)
- Track OCR request duration distribution
- Alert on timeout rate > 5%
- User feedback: "OCR is taking longer than usual" after 15s

---

### Pitfall 4: Chat Message Ordering and Context Loss

**What goes wrong:** Chat messages appear out of order, conversation history is lost on refresh, or AI responses reference wrong context.

**Why it happens:**
- Current `SearchController::searchDocuments()` accepts `conversationHistory` but doesn't persist it
- No message IDs or timestamps for ordering
- Frontend stores messages in component state (lost on navigation)
- Gemini API limited to 6 messages of history (hardcoded in `GeminiService::formatConversationHistory`)
- No deduplication if user sends same message twice

**Consequences:**
- User refreshes page, entire chat history disappears
- Messages appear in wrong order (race condition with async responses)
- AI answers reference outdated context from earlier in conversation
- User sees duplicate messages after retry

**Prevention:**
```php
// ✅ Backend: Persist conversation
public function searchDocuments(Request $request) {
    $conversation = Conversation::firstOrCreate(
        ['user_id' => $user->id, 'session_id' => $request->session_id],
        ['title' => $request->query]
    );
    
    // Store user message
    $userMessage = Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => $request->query,
        'timestamp' => now(),
    ]);
    
    // Get recent history (last 6 messages)
    $history = Message::where('conversation_id', $conversation->id)
        ->orderBy('timestamp', 'desc')
        ->limit(6)
        ->get()
        ->reverse()
        ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
        ->toArray();
    
    // Generate AI response
    $answer = $this->geminiService->generateAnswer(
        $request->query,
        $contextSegments,
        $history
    );
    
    // Store AI response
    $aiMessage = Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => $answer,
        'timestamp' => now(),
    ]);
    
    return response()->json([
        'conversation_id' => $conversation->id,
        'user_message' => $userMessage,
        'ai_message' => $aiMessage,
        'answer' => $answer,
    ]);
}
```

```typescript
// ✅ Frontend: Ordered message list with IDs
interface Message {
  id: string
  role: 'user' | 'assistant'
  content: string
  timestamp: string
  status: 'sending' | 'sent' | 'error'
}

const messages = ref<Message[]>([])

const sendMessage = async (content: string) => {
  const userMsg: Message = {
    id: crypto.randomUUID(),
    role: 'user',
    content,
    timestamp: new Date().toISOString(),
    status: 'sending',
  }
  
  messages.value.push(userMsg)
  
  try {
    const response = await $api.post('/search/documents', {
      query: content,
      session_id: conversationId.value,
    })
    
    // Update user message status
    userMsg.status = 'sent'
    
    // Add AI response
    messages.value.push({
      id: response.ai_message.id,
      role: 'assistant',
      content: response.answer,
      timestamp: response.ai_message.timestamp,
      status: 'sent',
    })
    
  } catch (error) {
    userMsg.status = 'error'
    showError('Failed to send message')
  }
}

// Load conversation on mount
onMounted(async () => {
  const { messages: history } = await $api.get(`/conversations/${conversationId.value}`)
  messages.value = history.sort((a, b) => 
    new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime()
  )
})
```

**Detection:**
- Test: Send 5 messages rapidly, verify all appear in correct order
- Test: Refresh page, verify conversation history loads
- Monitor: Orphaned messages (no conversation_id)
- User feedback: "Message failed to send" retry button

---

### Pitfall 5: Local → S3 Migration Breaks Existing Files

**What goes wrong:** After migrating from local storage to S3, existing file URLs break, thumbnails fail to load, or downloads return 404.

**Why it happens:**
- Current codebase has no local file storage (files go directly to Supermemory)
- When local storage is added, file paths stored in database as absolute paths
- S3 URLs have different structure than local URLs
- No migration script to update existing file references
- Cached file URLs in frontend become stale

**Consequences:**
- All existing user files become inaccessible after migration
- Users see broken images and "File not found" errors
- Support tickets spike after deployment
- Data loss perception (files still exist but can't be accessed)

**Prevention:**
```php
// ❌ BAD: Store absolute paths
Document::create([
    'user_id' => $user->id,
    'file_path' => '/storage/app/uploads/user-123/file.pdf', // Absolute path
]);

// ✅ GOOD: Store relative paths + disk name
Document::create([
    'user_id' => $user->id,
    'storage_disk' => config('filesystems.default'), // 'local' or 's3'
    'file_path' => 'uploads/user-123/file.pdf', // Relative path
    'original_name' => 'invoice.pdf',
]);

// ✅ GOOD: Generate URLs dynamically
class Document extends Model {
    public function getUrlAttribute(): string {
        return Storage::disk($this->storage_disk)->url($this->file_path);
    }
    
    public function getTemporaryUrlAttribute(): string {
        if ($this->storage_disk === 's3') {
            return Storage::disk('s3')->temporaryUrl($this->file_path, now()->addHour());
        }
        return $this->url;
    }
}

// ✅ GOOD: Migration script for existing files
class MigrateLocalToS3 extends Command {
    public function handle() {
        $documents = Document::where('storage_disk', 'local')->get();
        
        foreach ($documents as $doc) {
            $localPath = storage_path('app/' . $doc->file_path);
            
            if (!file_exists($localPath)) {
                $this->warn("File not found: {$localPath}");
                continue;
            }
            
            // Upload to S3
            Storage::disk('s3')->put($doc->file_path, file_get_contents($localPath));
            
            // Update database
            $doc->update(['storage_disk' => 's3']);
            
            // Delete local file
            unlink($localPath);
        }
        
        // Switch default disk
        // Update .env: FILESYSTEM_DISK=s3
    }
}
```

**Detection:**
- Test: Upload file with local storage, migrate to S3, verify file still accessible
- Monitor: 404 errors on file endpoints after migration
- Audit: Count files in local storage vs database records
- Rollback plan: Keep local files for 7 days after migration

---

## Moderate Pitfalls

### Pitfall 6: Rate Limiting Exhausted by Slow Operations

**What goes wrong:** User hits rate limit after only a few requests because OCR/search operations take too long.

**Why it happens:**
- Rate limiter counts requests, not processing time
- OCR endpoint limited to 20/min, but each request takes 30s
- User can only make 10 OCR requests in 5 minutes (not 20)
- No distinction between fast (list documents) and slow (OCR) operations

**Prevention:**
```php
// ✅ Separate rate limiters for different operation types
RateLimiter::for('ocr', function (Request $request) {
    return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('fast_api', function (Request $request) {
    return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
});

// Apply to routes
Route::post('/ocr/analyze', [OcrController::class, 'analyze'])
    ->middleware('throttle:ocr');

Route::get('/documents', [DocumentController::class, 'index'])
    ->middleware('throttle:fast_api');
```

---

### Pitfall 7: Large File Lists Cause Performance Issues

**What goes wrong:** Document list page loads slowly (>5s) when user has 1000+ files, or browser runs out of memory.

**Why it happens:**
- Current `DocumentController::index()` fetches all documents from Supermemory API
- No pagination beyond what Supermemory provides (max 50 per page)
- Frontend renders all documents in DOM (no virtualization)
- No lazy loading or infinite scroll

**Prevention:**
```typescript
// ✅ Frontend: Virtual scrolling for large lists
import { useVirtualList } from '@vueuse/core'

const { list, containerProps, wrapperProps } = useVirtualList(documents, {
  itemHeight: 80,
})

// ✅ Frontend: Infinite scroll with pagination
const loadMore = async () => {
  if (loading.value || !hasMore.value) return
  
  loading.value = true
  const { data, meta } = await $api.get('/documents', {
    params: { page: page.value, limit: 50 }
  })
  
  documents.value.push(...data)
  page.value++
  hasMore.value = meta.pagination.has_more
  loading.value = false
}

// ✅ Backend: Cursor-based pagination for better performance
public function index(Request $request) {
    $documents = Document::where('user_id', $user->id)
        ->when($request->cursor, fn($q) => 
            $q->where('id', '<', $request->cursor)
        )
        ->orderBy('id', 'desc')
        ->limit(50)
        ->get();
    
    return response()->json([
        'data' => $documents,
        'meta' => [
            'next_cursor' => $documents->last()?->id,
            'has_more' => $documents->count() === 50,
        ],
    ]);
}
```

---

### Pitfall 8: File Upload Security Vulnerabilities

**What goes wrong:** Malicious users upload executable files, exploit file name vulnerabilities, or bypass size limits.

**Why it happens:**
- Current validation only checks MIME type and size
- No validation of file content (executable disguised as PDF)
- File names not sanitized (path traversal attacks)
- No virus scanning

**Prevention:**
```php
// ✅ Comprehensive file validation
class AddFilesRequest extends FormRequest {
    public function rules(): array {
        return [
            'files' => ['required', 'array', 'max:10'],
            'files.*' => [
                'file',
                'max:51200', // 50MB
                'mimes:jpg,jpeg,png,pdf,webp',
                'mimetypes:image/jpeg,image/png,application/pdf,image/webp',
                new SafeFileName, // Custom rule
                new FileContentValidator, // Check magic bytes
            ],
        ];
    }
}

// ✅ Sanitize file names before storage
$originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
$safeName = preg_replace('/[^A-Za-z0-9_\-]/', '', $originalName);
$extension = $file->getClientOriginalExtension();
$storedName = "{$safeName}_{$user->id}_{$timestamp}.{$extension}";

// ✅ Store outside public directory
$path = $file->storeAs("uploads/{$user->id}", $storedName, 'local');

// ✅ Serve files through authenticated controller
Route::get('/files/{document}', [FileController::class, 'download'])
    ->middleware('auth:sanctum');

public function download(Document $document) {
    // Verify ownership
    if ($document->user_id !== auth()->id()) {
        abort(403);
    }
    
    return Storage::disk($document->storage_disk)
        ->download($document->file_path, $document->original_name);
}
```

---

### Pitfall 9: Missing CSRF Protection for SPA

**What goes wrong:** Cross-site request forgery attacks succeed because SPA doesn't send CSRF tokens.

**Why it happens:**
- Sanctum configured for SPA authentication but CSRF middleware not applied
- Cookie-based auth vulnerable to CSRF (browser auto-sends cookies)
- No `XSRF-TOKEN` cookie or `X-XSRF-TOKEN` header in requests

**Prevention:**
```php
// ✅ Enable CSRF protection for SPA routes
// In bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);
})

// ✅ Frontend: Automatically include CSRF token
// In resources/ts/utils/api.ts
export const $api = ofetch.create({
  baseURL: '/api',
  async onRequest({ options }) {
    const token = useCookie('XSRF-TOKEN').value
    if (token) {
      options.headers.set('X-XSRF-TOKEN', token)
    }
  },
})
```

---

## Minor Pitfalls

### Pitfall 10: Inconsistent Error Response Format

**What goes wrong:** Frontend shows generic "Something went wrong" because error responses have different structures.

**Why it happens:**
- Laravel validation errors: `{ errors: { field: ["message"] } }`
- Custom exceptions: `{ message: "Error", detail: {...} }`
- Supermemory API errors: `{ error: "message" }`
- No unified error handling

**Prevention:**
```typescript
// ✅ Unified error handler
export class ApiError extends Error {
  constructor(
    public status: number,
    public message: string,
    public errors?: Record<string, string[]>,
    public detail?: any
  ) {
    super(message)
  }
}

export const $api = ofetch.create({
  async onResponseError({ response }) {
    const data = response._data
    
    if (response.status === 422 && data.errors) {
      throw new ApiError(422, 'Validation failed', data.errors)
    }
    
    throw new ApiError(
      response.status,
      data.message || 'An error occurred',
      undefined,
      data.detail
    )
  },
})

// ✅ Frontend: Display validation errors
const handleSubmit = async () => {
  try {
    await $api.post('/documents', formData)
  } catch (error) {
    if (error instanceof ApiError && error.errors) {
      Object.entries(error.errors).forEach(([field, messages]) => {
        formErrors.value[field] = messages[0]
      })
    } else {
      showError(error.message)
    }
  }
}
```

---

### Pitfall 11: Missing Loading States and Optimistic Updates

**What goes wrong:** Users click buttons multiple times because there's no visual feedback, or they think action failed when it's still processing.

**Why it happens:**
- No loading indicators on buttons during API calls
- No optimistic updates (wait for server response before updating UI)
- No disabled state on buttons during submission

**Prevention:**
```typescript
// ✅ Composable for async operations
export function useAsyncAction<T>(action: () => Promise<T>) {
  const loading = ref(false)
  const error = ref<Error | null>(null)
  
  const execute = async () => {
    loading.value = true
    error.value = null
    
    try {
      return await action()
    } catch (e) {
      error.value = e as Error
      throw e
    } finally {
      loading.value = false
    }
  }
  
  return { loading, error, execute }
}

// ✅ Usage in component
const { loading: isUploading, execute: uploadFile } = useAsyncAction(async () => {
  await $api.post('/documents', formData)
  await refreshDocuments()
})

// In template
<VBtn :loading="isUploading" @click="uploadFile">
  Upload
</VBtn>
```

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| **Auth UI (Login/Register)** | Token not persisting across page refresh | Use HttpOnly cookies, test in multiple tabs |
| **File Upload UI** | Large uploads timeout silently | Implement async processing with progress polling |
| **OCR Integration** | Request blocks for 30s, exhausts PHP workers | Move to queue jobs, add progress endpoint |
| **Chat Interface** | Message history lost on refresh | Persist conversations in database, load on mount |
| **Document List** | Slow load with 1000+ files | Implement infinite scroll, virtualize list rendering |
| **Local Storage** | Migration to S3 breaks existing files | Store relative paths, write migration script |
| **File Security** | Malicious file uploads | Validate MIME + content, sanitize names, scan for viruses |
| **Error Handling** | Generic "something went wrong" | Unified error format, display validation errors inline |

## Testing Checklist

Before deploying each feature, verify:

### Authentication
- [ ] Login persists across page refresh
- [ ] Logout clears session in all tabs
- [ ] 401 responses redirect to login
- [ ] Token refresh works (if implemented)

### File Uploads
- [ ] Upload 50MB file successfully
- [ ] Upload 10 files simultaneously
- [ ] Network interruption during upload shows error
- [ ] Progress bar updates correctly
- [ ] Uploaded file appears in document list

### OCR Processing
- [ ] OCR completes for 10-page PDF
- [ ] Progress indicator shows during processing
- [ ] Timeout after 60s shows friendly error
- [ ] Rate limit message is clear

### Chat Interface
- [ ] Messages appear in correct order
- [ ] Conversation persists across refresh
- [ ] AI response references correct context
- [ ] Rapid message sending doesn't create duplicates

### Document List
- [ ] 1000 documents load in <2s
- [ ] Infinite scroll works smoothly
- [ ] Search/filter updates list correctly
- [ ] Pagination state persists

### Storage Migration
- [ ] Files accessible after local → S3 migration
- [ ] Old URLs redirect to new URLs
- [ ] No broken images or downloads
- [ ] Rollback plan tested

## Sources

- Codebase analysis: `app/Services/GeminiService.php`, `app/Services/SupermemoryService.php`
- Authentication: `app/Http/Controllers/AuthController.php`, `config/sanctum.php`
- File handling: `app/Http/Controllers/IngestionController.php`, `app/Http/Requests/AddFilesRequest.php`
- Rate limiting: `app/Providers/AppServiceProvider.php`
- Frontend API: `resources/ts/composables/useApi.ts`, `resources/ts/utils/api.ts`

<?php

/**
 * Fase 9 smoke test — file attachment end-to-end.
 *
 * Bypass HTTP + Breeze (CSRF) yang flaky di sandpack CachyOS. Pakai
 * controller direct call via setUserResolver() + safeCall() wrap
 * HttpException. Lihat scripts/smoke_fase8.php untuk pola yang sama.
 *
 * Jalankan: cd /home/nyx/Documents/NyxChamp && /tmp/opencode/php-mysql.sh scripts/smoke_fase9.php
 *
 * Test plan (11 check):
 *  1.  Upload jpg + text → row + file di disk
 *  2.  Upload png + text → row + file di disk
 *  3.  Upload pdf + text → row + file di disk
 *  4.  Upload docx + text → row + file di disk
 *  5.  Reject svg (XSS) — validation error
 *  6.  Reject oversize image (6MB > 5MB) — 422
 *  7.  Reject oversize doc (11MB > 10MB) — 422
 *  8.  Reject > 5 files — validation error
 *  9.  Download via controller — content matches
 * 10.  Non-member download = 403
 * 11.  Soft-delete message: attachment disembunyi di show payload,
 *      file TETAP di disk
 */

use App\Http\Controllers\ChatController;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Pakai SQLite in-memory + temp storage disk
config()->set('database.default', 'testing');
config()->set('database.connections.testing', [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);

$tmpStorage = sys_get_temp_dir().'/nyxchamp_smoke_fase9_'.getmypid();
@mkdir($tmpStorage, 0777, true);
config()->set('filesystems.disks.chat', [
    'driver' => 'local',
    'root' => $tmpStorage,
    'throw' => true,
    'report' => true,
    'visibility' => 'private',
]);

DB::purge('testing');
DB::reconnect('testing');

Schema::create('users', function (Blueprint $t) {
    $t->id();
    $t->string('name');
    $t->string('email')->unique();
    $t->string('password');
    $t->string('role')->default('student');
    $t->string('institution')->nullable();
    $t->timestamp('email_verified_at')->nullable();
    $t->rememberToken();
    $t->timestamps();
});
Schema::create('chat_rooms', function (Blueprint $t) {
    $t->id();
    $t->unsignedBigInteger('competition_id')->nullable();
    $t->string('name');
    $t->boolean('is_group')->default(true);
    $t->unsignedBigInteger('created_by');
    $t->timestamps();
});
Schema::create('chat_room_members', function (Blueprint $t) {
    $t->unsignedBigInteger('chat_room_id');
    $t->unsignedBigInteger('user_id');
    $t->timestamp('joined_at');
    $t->primary(['chat_room_id', 'user_id']);
});
Schema::create('chat_room_reads', function (Blueprint $t) {
    $t->unsignedBigInteger('chat_room_id');
    $t->unsignedBigInteger('user_id');
    $t->unsignedBigInteger('last_read_message_id')->nullable();
    $t->timestamp('read_at')->nullable();
    $t->primary(['chat_room_id', 'user_id']);
});
Schema::create('chat_messages', function (Blueprint $t) {
    $t->id();
    $t->unsignedBigInteger('chat_room_id');
    $t->unsignedBigInteger('sender_id');
    $t->text('message_text')->nullable();
    $t->timestamp('edited_at')->nullable();
    $t->timestamp('deleted_at')->nullable();
    $t->unsignedBigInteger('deleted_by')->nullable();
    $t->timestamp('created_at')->nullable();
    $t->timestamp('updated_at')->nullable();
});
Schema::create('chat_attachments', function (Blueprint $t) {
    $t->id();
    $t->unsignedBigInteger('chat_message_id');
    $t->unsignedBigInteger('uploaded_by');
    $t->string('disk', 32);
    $t->string('file_path', 512);
    $t->string('original_name', 255);
    $t->string('mime_type', 127);
    $t->unsignedBigInteger('size_bytes');
    $t->timestamps();
});

function safeCall(callable $fn): array
{
    try {
        $result = $fn();
        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return [$result->getStatusCode(), $result->getContent()];
        }
        if ($result instanceof \Illuminate\Http\RedirectResponse) {
            return [$result->getStatusCode(), 'REDIRECT'];
        }
        return [200, is_scalar($result) ? (string) $result : 'OK'];
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        return [$e->getStatusCode(), $e->getMessage()];
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return [404, 'NOT_FOUND'];
    } catch (\Illuminate\Validation\ValidationException $e) {
        return [422, json_encode($e->errors())];
    } catch (\Throwable $e) {
        return [500, $e->getMessage()];
    }
}

function pass(string $label, bool $ok, string $detail = ''): void
{
    static $i = 0;
    $i++;
    $mark = $ok ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
    echo "  {$mark} #{$i} {$label}";
    if ($detail) echo "  \033[90m({$detail})\033[0m";
    echo PHP_EOL;
    if (! $ok) {
        global $failures;
        $failures[] = $label;
    }
}

$failures = [];
echo "\n\033[1m=== Fase 9 Smoke Test — File Attachment ===\033[0m\n\n";

// Setup: 1 student, 1 teacher (untuk owner), 1 outsider
$student = User::create(['name' => 'Siswa Uji', 'email' => 'student@test.local', 'password' => 'x', 'role' => 'student']);
$teacher = User::create(['name' => 'Guru Uji', 'email' => 'teacher@test.local', 'password' => 'x', 'role' => 'teacher']);
$outsider = User::create(['name' => 'Orang Luar', 'email' => 'out@test.local', 'password' => 'x', 'role' => 'student']);

$room = ChatRoom::create(['name' => 'Ruang Uji', 'is_group' => true, 'created_by' => $teacher->id]);
ChatRoomMember::create(['chat_room_id' => $room->id, 'user_id' => $teacher->id, 'joined_at' => now()]);
ChatRoomMember::create(['chat_room_id' => $room->id, 'user_id' => $student->id, 'joined_at' => now()]);

$controller = new ChatController;

function fakeFile(string $name, string $mime, int $sizeKb, string $content = null): UploadedFile
{
    if ($content === null) {
        $content = str_repeat('A', $sizeKb * 1024);
    }
    return UploadedFile::fake()->createWithContent($name, $content);
}

// ===== Test 1: Upload JPG + text =====
echo "[\033[36m1\033[0m] Upload JPG (200KB) + text\n";
[$code, $body] = safeCall(function () use ($controller, $student, $room) {
    $req = Request::create('/x', 'POST', [
        'message_text' => 'Lihat poster saya',
    ], [], [
        'attachments' => [fakeFile('poster.jpg', 'image/jpeg', 200)],
    ]);
    $req->setUserResolver(fn () => $student);
    return $controller->storeMessage($req, $room);
});
$msg1 = ChatMessage::where('chat_room_id', $room->id)->latest('id')->first();
$att1 = $msg1?->attachments()->first();
pass('Upload JPG → redirect 303', $code === 303);
pass('JPG: 1 attachment row', $att1 !== null);
pass('JPG: original_name', $att1?->original_name === 'poster.jpg');
pass('JPG: file exists on disk', $att1 && Storage::disk('chat')->exists($att1->file_path));
pass('JPG: size_bytes correct', $att1 && $att1->size_bytes > 100_000 && $att1->size_bytes < 300_000);
pass('JPG: mime image/jpeg', $att1?->mime_type === 'image/jpeg');

// ===== Test 2: Upload PNG =====
echo "\n[\033[36m2\033[0m] Upload PNG (150KB)\n";
[$code, $body] = safeCall(function () use ($controller, $student, $room) {
    $req = Request::create('/x', 'POST', [
        'message_text' => 'PNG bro',
    ], [], [
        'attachments' => [fakeFile('img.png', 'image/png', 150)],
    ]);
    $req->setUserResolver(fn () => $student);
    return $controller->storeMessage($req, $room);
});
$msg2 = ChatMessage::where('chat_room_id', $room->id)->latest('id')->first();
$att2 = $msg2?->attachments()->first();
pass('PNG: redirect 303', $code === 303);
pass('PNG: file exists', $att2 && Storage::disk('chat')->exists($att2->file_path));

// ===== Test 3: Upload PDF =====
echo "\n[\033[36m3\033[0m] Upload PDF (800KB)\n";
[$code, $body] = safeCall(function () use ($controller, $student, $room) {
    $req = Request::create('/x', 'POST', [
        'message_text' => 'Proposal',
    ], [], [
        'attachments' => [fakeFile('proposal.pdf', 'application/pdf', 800)],
    ]);
    $req->setUserResolver(fn () => $student);
    return $controller->storeMessage($req, $room);
});
$msg3 = ChatMessage::where('chat_room_id', $room->id)->latest('id')->first();
$att3 = $msg3?->attachments()->first();
pass('PDF: redirect 303', $code === 303);
pass('PDF: file exists', $att3 && Storage::disk('chat')->exists($att3->file_path));
pass('PDF: mime application/pdf', $att3?->mime_type === 'application/pdf');

// ===== Test 4: Upload DOCX =====
echo "\n[\033[36m4\033[0m] Upload DOCX (1MB)\n";
[$code, $body] = safeCall(function () use ($controller, $student, $room) {
    $req = Request::create('/x', 'POST', [
        'message_text' => 'CV',
    ], [], [
        'attachments' => [fakeFile('cv.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 1024)],
    ]);
    $req->setUserResolver(fn () => $student);
    return $controller->storeMessage($req, $room);
});
$msg4 = ChatMessage::where('chat_room_id', $room->id)->latest('id')->first();
$att4 = $msg4?->attachments()->first();
pass('DOCX: redirect 303', $code === 303);
pass('DOCX: file exists', $att4 && Storage::disk('chat')->exists($att4->file_path));

// ===== Test 5: Reject SVG (XSS) =====
echo "\n[\033[36m5\033[0m] Reject SVG (XSS attempt)\n";
[$code, $body] = safeCall(function () use ($controller, $student, $room) {
    $req = Request::create('/x', 'POST', [
        'message_text' => 'hack',
    ], [], [
        'attachments' => [fakeFile('x.svg', 'image/svg+xml', 1, '<svg onload=alert(1)></svg>')],
    ]);
    $req->setUserResolver(fn () => $student);
    return $controller->storeMessage($req, $room);
});
pass('SVG: rejected (422)', $code === 422, "got {$code}");

// ===== Test 6: Reject oversize image =====
echo "\n[\033[36m6\033[0m] Reject image > 5MB\n";
[$code, $body] = safeCall(function () use ($controller, $student, $room) {
    $req = Request::create('/x', 'POST', [
        'message_text' => 'big',
    ], [], [
        'attachments' => [fakeFile('huge.jpg', 'image/jpeg', 6000)],
    ]);
    $req->setUserResolver(fn () => $student);
    return $controller->storeMessage($req, $room);
});
pass('6MB image: rejected (422)', $code === 422, "got {$code}");

// ===== Test 7: Reject oversize doc =====
echo "\n[\033[36m7\033[0m] Reject PDF > 10MB\n";
[$code, $body] = safeCall(function () use ($controller, $student, $room) {
    $req = Request::create('/x', 'POST', [
        'message_text' => 'huge',
    ], [], [
        'attachments' => [fakeFile('huge.pdf', 'application/pdf', 11000)],
    ]);
    $req->setUserResolver(fn () => $student);
    return $controller->storeMessage($req, $room);
});
pass('11MB PDF: rejected (422)', $code === 422, "got {$code}");

// ===== Test 8: Reject > 5 files =====
echo "\n[\033[36m8\033[0m] Reject > 5 attachments\n";
[$code, $body] = safeCall(function () use ($controller, $student, $room) {
    $files = [];
    for ($i = 1; $i <= 6; $i++) {
        $files["file{$i}"] = fakeFile("p{$i}.jpg", 'image/jpeg', 50);
    }
    $req = Request::create('/x', 'POST', ['message_text' => 'banyak'], [], ['attachments' => $files]);
    $req->setUserResolver(fn () => $student);
    return $controller->storeMessage($req, $room);
});
pass('6 files: rejected (422)', $code === 422, "got {$code}");

// ===== Test 9: Download as member =====
echo "\n[\033[36m9\033[0m] Download attachment as member\n";
$downloadResp = null;
[$code, $body] = safeCall(function () use ($controller, $student, $room, $att1, &$downloadResp) {
    $req = Request::create('/x', 'GET');
    $req->setUserResolver(fn () => $student);
    $resp = $controller->downloadAttachment($req, $room, $att1);
    $downloadResp = $resp;
    return $resp;
});
pass('Download: returns 200', $code === 200, "got {$code}");
pass('Download: is StreamedResponse', $downloadResp instanceof StreamedResponse);
pass('Download: Content-Disposition=attachment', $downloadResp && str_contains($downloadResp->headers->get('Content-Disposition', ''), 'attachment'));
pass('Download: filename original_name', $downloadResp && str_contains($downloadResp->headers->get('Content-Disposition', ''), 'poster.jpg'));

// ===== Test 10: Non-member download =====
echo "\n[\033[36m10\033[0m] Non-member download blocked\n";
[$code, $body] = safeCall(function () use ($controller, $outsider, $room, $att1) {
    $req = Request::create('/x', 'GET');
    $req->setUserResolver(fn () => $outsider);
    return $controller->downloadAttachment($req, $room, $att1);
});
pass('Outsider: 403', $code === 403, "got {$code}");

// ===== Test 11: Soft-delete message — file tetap di disk =====
echo "\n[\033[36m11\033[0m] Soft-delete keeps file on disk\n";
$msgBeforeDelete = ChatMessage::where('chat_room_id', $room->id)->whereNotNull('message_text')->first();
$attBeforeDelete = $msgBeforeDelete->attachments()->first();
$pathBefore = $attBeforeDelete->file_path;
[$code, $body] = safeCall(function () use ($controller, $student, $room, $msgBeforeDelete) {
    $req = Request::create('/x', 'DELETE');
    $req->setUserResolver(fn () => $student);
    return $controller->deleteMessage($req, $room, $msgBeforeDelete);
});
pass('Soft-delete: redirect 303', $code === 303, "got {$code}");
$msgBeforeDelete->refresh();
pass('Soft-deleted: deleted_at set', $msgBeforeDelete->deleted_at !== null);
pass('Soft-deleted: file STILL on disk', Storage::disk('chat')->exists($pathBefore));

// Test 11b: Hard delete cascade hapus attachment rows (file di disk BISA di-cron)
// (Inertia payload inspection covered by PHPUnit: test_soft_deleted_message_hides_attachments_in_show)

// Cleanup
echo "\n\033[90mCleaning up temp storage at {$tmpStorage}...\033[0m";
exec('rm -rf '.escapeshellarg($tmpStorage));

echo "\n\n";
if (empty($failures)) {
    echo "\033[32;1m✓ ALL 11 CHECKS PASSED\033[0m\n";
    exit(0);
} else {
    echo "\033[31;1m✗ FAILED: ".count($failures)." check(s)\033[0m\n";
    foreach ($failures as $f) echo "  - {$f}\n";
    exit(1);
}

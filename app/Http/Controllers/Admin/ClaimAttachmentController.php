<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaimAttachmentController extends Controller
{
    public function __invoke(Request $request, ClaimAttachment $attachment): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && $user->isStaff() && $user->can('claims.manage'), 403);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

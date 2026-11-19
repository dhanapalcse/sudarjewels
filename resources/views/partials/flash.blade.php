@foreach (['success' => 'bg-mint text-teal-900 border-teal/20', 'status' => 'bg-mint text-teal-900 border-teal/20', 'error' => 'bg-red-50 text-danger border-danger/20'] as $key => $classes)
    @if (session($key))
        <div class="container-page mt-4">
            <div class="rounded-xl border px-4 py-3 text-sm {{ $classes }}" role="{{ $key === 'error' ? 'alert' : 'status' }}">
                {{ session($key) }}
            </div>
        </div>
    @endif
@endforeach

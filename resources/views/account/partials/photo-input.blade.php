<div x-data="{ files: [] }">
    <label for="photos" class="field-label">Photos <span class="font-normal text-muted">(up to 5, JPG/PNG/WebP, 8 MB each)</span></label>
    <input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="field py-2 text-sm" @change="files = Array.from($event.target.files).map(f => f.name)">
    <ul class="mt-2 text-xs text-muted" x-show="files.length"><template x-for="f in files" :key="f"><li x-text="f"></li></template></ul>
    @error('photos')<p class="field-error">{{ $message }}</p>@enderror
    @error('photos.*')<p class="field-error">{{ $message }}</p>@enderror
</div>

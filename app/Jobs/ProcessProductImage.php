<?php

namespace App\Jobs;

use App\Domain\Catalogue\Images\GoogleDriveClient;
use App\Domain\Catalogue\Images\ImageImportException;
use App\Domain\Catalogue\Images\ImageProcessor;
use App\Enums\ImageStatus;
use App\Models\ProductImage;
use App\Support\CatalogueCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessProductImage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $imageId)
    {
        $this->onQueue('media');
    }

    public function uniqueId(): string
    {
        return (string) $this->imageId;
    }

    public function handle(ImageProcessor $processor, GoogleDriveClient $drive): void
    {
        $image = ProductImage::find($this->imageId);
        if (! $image) {
            return;
        }
        $image->forceFill(['status' => ImageStatus::Processing, 'attempts' => $image->attempts + 1])->save();

        try {
            $binary = match ($image->source) {
                'google_drive' => $this->fromDrive($image, $drive),
                default => $this->fromStaging($image),
            };
            $processor->publish($image, $binary);
            if ($image->staging_path) {
                Storage::disk('local')->delete($image->staging_path);
                $image->forceFill(['staging_path' => null])->save();
            }
            CatalogueCache::flush();
        } catch (ImageImportException $e) {
            $this->markFailed($image, $e->getMessage());
        }
    }

    public function failed(?Throwable $e): void
    {
        if ($image = ProductImage::find($this->imageId)) {
            $this->markFailed($image, 'Import failed after several attempts. Please try again.');
        }
        Log::error('Product image import failed', ['image_id' => $this->imageId, 'error' => $e?->getMessage()]);
    }

    private function markFailed(ProductImage $image, string $message): void
    {
        // Renditions are untouched, so any previously published image stays live.
        $image->forceFill(['status' => ImageStatus::Failed, 'error' => $message])->save();
    }

    private function fromDrive(ProductImage $image, GoogleDriveClient $drive): string
    {
        $meta = $drive->metadata($image->drive_file_id);
        if (! in_array($meta['mimeType'], config('sudar.images.allowed_mimes'), true)) {
            throw new ImageImportException('The Drive file is not a JPEG, PNG or WebP image.');
        }
        if ($meta['size'] > config('sudar.images.max_bytes')) {
            throw new ImageImportException('The Drive file is larger than the allowed size.');
        }
        $image->forceFill(['drive_file_name' => mb_substr($meta['name'], 0, 250)])->save();

        return $drive->download($image->drive_file_id, config('sudar.images.max_bytes'));
    }

    private function fromStaging(ProductImage $image): string
    {
        if (! $image->staging_path || ! Storage::disk('local')->exists($image->staging_path)) {
            throw new ImageImportException('The uploaded file is no longer available. Upload it again.');
        }

        return Storage::disk('local')->get($image->staging_path);
    }
}

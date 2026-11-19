<?php

namespace App\Domain\Catalogue\Images;

use App\Enums\ImageStatus;
use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Audit;

class ProductImageService
{
    /** Queue a direct upload already stored on the private staging disk. */
    public function queueUpload(Product $product, string $stagingPath, ?string $alt = null, bool $isMain = false): ProductImage
    {
        $image = $product->images()->create([
            'source' => 'upload',
            'staging_path' => $stagingPath,
            'status' => ImageStatus::Queued,
            'alt' => $alt ?: $product->name,
            'is_main' => $isMain || ! $product->images()->exists(),
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
        ]);
        ProcessProductImage::dispatch($image->id);

        return $image;
    }

    public function queueDriveImport(Product $product, string $driveLinkOrId, ?string $alt = null, bool $isMain = false): ProductImage
    {
        $fileId = DriveReference::parse($driveLinkOrId);
        if (! $fileId) {
            throw new ImageImportException('Enter a Google Drive file link (drive.google.com/file/d/…) or file ID.');
        }
        $image = $product->images()->create([
            'source' => 'google_drive',
            'drive_file_id' => $fileId,
            'status' => ImageStatus::Queued,
            'alt' => $alt ?: $product->name,
            'is_main' => $isMain || ! $product->images()->exists(),
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
        ]);
        Audit::log('image.drive_import_queued', $image, ['product_id' => $product->id]);
        ProcessProductImage::dispatch($image->id);

        return $image;
    }

    /** Re-import from the saved Drive reference; the current renditions stay live until success. */
    public function reimport(ProductImage $image): void
    {
        if ($image->source !== 'google_drive' || ! $image->drive_file_id) {
            throw new ImageImportException('Only Google Drive images can be re-imported.');
        }
        $image->update(['status' => ImageStatus::Queued, 'error' => null]);
        ProcessProductImage::dispatch($image->id);
    }

    public function setMain(ProductImage $image): void
    {
        ProductImage::where('product_id', $image->product_id)->update(['is_main' => false]);
        $image->update(['is_main' => true]);
    }
}

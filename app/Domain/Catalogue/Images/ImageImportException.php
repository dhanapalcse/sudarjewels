<?php

namespace App\Domain\Catalogue\Images;

use RuntimeException;

/** Non-retryable import problem with a message safe to show to staff. */
class ImageImportException extends RuntimeException
{
}

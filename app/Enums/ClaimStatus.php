<?php

namespace App\Enums;

enum ClaimStatus: string
{
    use HasLabel, HasTransitions;

    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case MoreInfoRequired = 'more_info_required';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Resolved = 'resolved';

    /** @return array<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::UnderReview, self::MoreInfoRequired, self::Approved, self::Rejected],
            self::UnderReview => [self::MoreInfoRequired, self::Approved, self::Rejected],
            self::MoreInfoRequired => [self::UnderReview, self::Approved, self::Rejected],
            self::Approved => [self::Resolved],
            self::Rejected, self::Resolved => [],
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Rejected, self::Resolved], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::MoreInfoRequired => 'More information required',
            self::UnderReview => 'Under review',
            default => ucfirst($this->value),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::UnderReview, self::MoreInfoRequired => 'warning',
            self::Approved => 'primary',
            self::Rejected => 'danger',
            self::Resolved => 'success',
        };
    }
}

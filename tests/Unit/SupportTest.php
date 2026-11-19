<?php

namespace Tests\Unit;

use App\Domain\Catalogue\Images\DriveReference;
use App\Support\Money;
use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_money_formatting_uses_indian_grouping(): void
    {
        $this->assertSame('₹12,34,567', Money::format(123456700));
        $this->assertSame('₹999.50', Money::format(99950));
        $this->assertSame(129950, Money::toPaise('1,299.50'));
    }

    public function test_rates_round_half_up_in_paise(): void
    {
        $this->assertSame(20000, Money::applyRate(100000, 2000));
        $this->assertSame(2, Money::applyRate(12, 2000)); // 2.4 -> 2
        $this->assertSame(3, Money::applyRate(13, 2000)); // 2.6 -> 3
        $this->assertSame(0, Money::applyRate(100000, 0));
    }

    public function test_allocation_sums_exactly(): void
    {
        $parts = Money::allocate(1000, [1, 1, 1]);
        $this->assertSame(1000, array_sum($parts));
        $this->assertSame([334, 333, 333], array_values($parts));
    }

    public function test_phone_normalisation(): void
    {
        $this->assertSame('+919876543210', Phone::normalize('98765 43210'));
        $this->assertSame('+919876543210', Phone::normalize('+91-98765-43210'));
        $this->assertSame('+919876543210', Phone::normalize('09876543210'));
        $this->assertNull(Phone::normalize('12345'));
        $this->assertNull(Phone::normalize('5876543210'));
    }

    public function test_drive_reference_only_accepts_drive_sources(): void
    {
        $id = '1AbCdEfGhIjKlMnOpQrStUvWxYz012345';
        $this->assertSame($id, DriveReference::parse("https://drive.google.com/file/d/{$id}/view?usp=sharing"));
        $this->assertSame($id, DriveReference::parse("https://drive.google.com/open?id={$id}"));
        $this->assertSame($id, DriveReference::parse($id));
        $this->assertNull(DriveReference::parse("https://evil.example.com/file/d/{$id}/view"));
        $this->assertNull(DriveReference::parse("http://drive.google.com/file/d/{$id}/view"));
        $this->assertNull(DriveReference::parse("https://drive.google.com.evil.com/file/d/{$id}"));
        $this->assertNull(DriveReference::parse('http://169.254.169.254/latest/meta-data'));
        $this->assertNull(DriveReference::parse("https://user@drive.google.com/file/d/{$id}"));
    }
}

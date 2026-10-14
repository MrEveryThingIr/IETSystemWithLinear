<?php

namespace Tests\Unit;

use App\Support\MediaLimits;
use PHPUnit\Framework\TestCase;

class MediaLimitsTest extends TestCase
{
    public function test_php_ini_sizes_are_parsed(): void
    {
        $this->assertSame(2 * 1024 * 1024, MediaLimits::iniBytes('2M'));
        $this->assertSame(512 * 1024, MediaLimits::iniBytes('512k'));
        $this->assertSame(1024 * 1024 * 1024, MediaLimits::iniBytes('1G'));
        $this->assertSame(1000, MediaLimits::iniBytes('1000'));
        $this->assertNull(MediaLimits::iniBytes(''));
        $this->assertNull(MediaLimits::iniBytes(false));
        $this->assertNull(MediaLimits::iniBytes('unlimited'));
    }

    public function test_effective_limit_never_exceeds_the_host_limit(): void
    {
        // Host accepts 8 MB per upload: a 12 MB application limit becomes 8 MB so
        // oversize files fail validation instead of reaching PHP's hard limit.
        $this->assertSame(8192, MediaLimits::effective(12288, 8192));
        $this->assertSame(12288, MediaLimits::effective(12288, 102400));
        $this->assertSame(12288, MediaLimits::effective(12288, null));
    }
}

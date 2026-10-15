<?php

namespace NineteenNinetyFour\Wishlist\Tests\Unit;

use NineteenNinetyFour\Wishlist\ShareCodec;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ShareCodecTest extends TestCase
{
    #[Test]
    public function a_uuid_packs_into_22_characters(): void
    {
        $encoded = ShareCodec::encode(['0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a']);

        $this->assertSame(22, strlen($encoded));
        $this->assertSame(['0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a'], ShareCodec::decode($encoded));
    }

    #[Test]
    public function anything_else_round_trips_as_text(): void
    {
        $references = ['0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a', 'tags::linen', 'home', 'café::crème'];

        $encoded = ShareCodec::encode($references);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_.~-]+$/', $encoded);
        $this->assertSame($references, ShareCodec::decode($encoded));
    }

    #[Test]
    public function upper_case_uuids_come_back_lower_case(): void
    {
        $this->assertSame(['0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a'], ShareCodec::decode(ShareCodec::encode(['0B3E1C2A-9F4D-4E8B-A1C2-3D4E5F60718A'])));
    }

    #[Test]
    public function rubbish_repeats_and_extras_are_dropped(): void
    {
        $a = ShareCodec::encode(['0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a']);
        $b = ShareCodec::encode(['tags::linen']);

        $this->assertSame([], ShareCodec::decode(null));
        $this->assertSame([], ShareCodec::decode(''));
        $this->assertSame(['0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a'], ShareCodec::decode("{$a}.{$a}.short.~!!!.~"));
        $this->assertSame(['0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a'], ShareCodec::decode("{$a}.{$b}", limit: 1));
        $this->assertSame([], ShareCodec::decode('~'.rtrim(strtr(base64_encode("\xff\xfe"), '+/', '-_'), '=')));
    }

    #[Test]
    public function it_matches_what_the_script_makes(): void
    {
        // Written by resources/js/wishlist.js (see resources/js/codec.test.js).
        $this->assertSame(
            ['0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a', 'tags::linen', 'café::crème'],
            ShareCodec::decode('Cz4cKp9NTouhwj1OX2Bxig.~dGFnczo6bGluZW4.~Y2Fmw6k6OmNyw6htZQ'),
        );
    }
}

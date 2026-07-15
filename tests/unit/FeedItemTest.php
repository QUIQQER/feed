<?php

declare(strict_types=1);

namespace QUITests\Feed;

use PHPUnit\Framework\TestCase;
use QUI\Feed\Handler\CSV\Item;

class FeedItemTest extends TestCase
{
    public function testSettersUseAttributesConsumedByFeedRenderers(): void
    {
        $Item = new Item();
        $Item->setDate(1_704_067_200);
        $Item->setLanguage('de');

        self::assertSame(1_704_067_200, $Item->getAttribute('date'));
        self::assertSame('de', $Item->getAttribute('language'));
        self::assertFalse($Item->getAttribute('time'));
        self::assertFalse($Item->getAttribute('lang'));
    }
}

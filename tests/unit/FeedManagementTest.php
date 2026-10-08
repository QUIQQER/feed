<?php

declare(strict_types=1);

namespace QUITests\Feed;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Feed\Feed;
use QUI\Feed\Manager;

class FeedManagementTest extends TestCase
{
    public function testMissingProjectCanBeRepresentedWithoutAFeedObject(): void
    {
        $Manager = $this->createPartialMock(Manager::class, ['getFeed']);
        $Manager->method('getFeed')->willThrowException(new QUI\Exception('Project not found', 804));

        self::assertNull($Manager->getFeedForList(1));
    }

    public function testHealthyFeedIsReturnedUnchanged(): void
    {
        $Feed = $this->createMock(Feed::class);
        $Manager = $this->createPartialMock(Manager::class, ['getFeed']);
        $Manager->method('getFeed')->willReturn($Feed);

        self::assertSame($Feed, $Manager->getFeedForList(1));
    }

    public function testOtherErrorsAreNotMisreportedAsMissingProjects(): void
    {
        $Exception = new QUI\Exception('Language missing', 806);
        $Manager = $this->createPartialMock(Manager::class, ['getFeed']);
        $Manager->method('getFeed')->willThrowException($Exception);

        $this->expectExceptionObject($Exception);
        $Manager->getFeedForList(1);
    }
}

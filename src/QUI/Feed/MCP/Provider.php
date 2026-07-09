<?php

/**
 * This file contains \QUI\Feed\MCP\Provider
 */

namespace QUI\Feed\MCP;

use Mcp\Server\Builder;
use QUI\AI\MCP\ProviderInterface;
use QUI\Feed\MCP\Feeds\CreateFeed;
use QUI\Feed\MCP\Feeds\DeleteFeed;
use QUI\Feed\MCP\Feeds\GetFeed;
use QUI\Feed\MCP\Feeds\ListFeeds;
use QUI\Feed\MCP\Feeds\RefreshFeed;
use QUI\Feed\MCP\Feeds\UpdateFeed;
use QUI\Feed\MCP\FeedTypes\ListFeedTypes;
use QUI\MCP\ToolInterface;
use Throwable;

/**
 * Feed MCP provider
 */
class Provider extends AbstractTool implements ProviderInterface
{
    /**
     * @var array<ToolInterface>
     */
    protected array $tools;

    public function __construct()
    {
        $this->tools = [
            new ListFeedTypes(),
            new ListFeeds(),
            new GetFeed(),
            new CreateFeed(),
            new UpdateFeed(),
            new DeleteFeed(),
            new RefreshFeed()
        ];
    }

    public function register(Builder $serverBuilder): void
    {
        if (!$this->canUseMcp()) {
            return;
        }

        foreach ($this->tools as $Tool) {
            $Tool->register($serverBuilder);
        }
    }

    protected function canUseMcp(): bool
    {
        try {
            self::checkFeedPermission();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}

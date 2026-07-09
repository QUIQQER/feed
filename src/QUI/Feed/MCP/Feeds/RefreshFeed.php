<?php

/**
 * This file contains \QUI\Feed\MCP\Feeds\RefreshFeed
 */

namespace QUI\Feed\MCP\Feeds;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\ToolHelper;
use QUI\Feed\MCP\AbstractTool;
use Throwable;

class RefreshFeed extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (
                int $id
            ): CallToolResult | array {
                try {
                    self::checkFeedPermission();

                    $Manager = self::getManager();
                    $Feed = $Manager->getFeed($id);

                    $Manager->buildFeed($Feed);

                    return [
                        'refreshed' => true,
                        'feed' => self::parseFeed($Manager->getFeed($id), true)
                    ];
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_feed_refresh',
            description: 'Regenerates one existing QUIQQER feed and writes the current output to the feed cache.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['id'],
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Feed ID.', 'minimum' => 1]
                ]
            ]
        );
    }
}

<?php

/**
 * This file contains \QUI\Feed\MCP\Feeds\GetFeed
 */

namespace QUI\Feed\MCP\Feeds;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\ToolHelper;
use QUI\Feed\MCP\AbstractTool;
use Throwable;

class GetFeed extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (
                int $id
            ): CallToolResult | array {
                try {
                    self::checkFeedPermission();

                    return self::parseFeed(
                        self::getManager()->getFeed($id),
                        true
                    );
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_feed_get',
            description: 'Returns one QUIQQER feed with all attributes and cache status.',
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

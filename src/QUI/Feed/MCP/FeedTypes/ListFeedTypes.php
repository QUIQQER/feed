<?php

/**
 * This file contains \QUI\Feed\MCP\FeedTypes\ListFeedTypes
 */

namespace QUI\Feed\MCP\FeedTypes;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\ToolHelper;
use QUI\Feed\MCP\AbstractTool;
use Throwable;

class ListFeedTypes extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (
                bool | null $withSettingsHtml = null,
                string | null $query = null,
                int | null $limit = null,
                int | null $offset = null
            ): CallToolResult | array {
                try {
                    self::checkFeedPermission();

                    $feedTypes = [];

                    foreach (self::getManager()->getTypes() as $feedType) {
                        if (!self::feedTypeMatchesQuery($feedType, $query)) {
                            continue;
                        }

                        $feedTypes[] = self::parseFeedType(
                            $feedType,
                            $withSettingsHtml === true
                        );
                    }

                    return self::applyLimit($feedTypes, $limit, $offset);
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_feed_types_list',
            description: 'Lists available QUIQQER feed types, including the type IDs required to create feeds.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'withSettingsHtml' => [
                        'type' => 'boolean',
                        'default' => false,
                        'description' => 'Include raw settings HTML for the feed type.'
                    ],
                    'query' => [
                        'type' => 'string',
                        'description' => 'Optional search term for feed type ID, title or description.'
                    ],
                    'limit' => ['type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 100],
                    'offset' => ['type' => 'integer', 'default' => 0, 'minimum' => 0]
                ]
            ]
        );
    }
}

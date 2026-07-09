<?php

/**
 * This file contains \QUI\Feed\MCP\Feeds\ListFeeds
 */

namespace QUI\Feed\MCP\Feeds;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\ToolHelper;
use QUI\Feed\MCP\AbstractTool;
use Throwable;

use function array_values;

class ListFeeds extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (
                string | null $project = null,
                string | null $lang = null,
                string | null $query = null,
                int | null $limit = null,
                int | null $offset = null
            ): CallToolResult | array {
                try {
                    self::checkFeedPermission();

                    $Manager = self::getManager();
                    $feeds = [];

                    foreach ($Manager->getList() as $feedRow) {
                        if (!self::feedRowMatchesProject($feedRow, $project, $lang)) {
                            continue;
                        }

                        if (!self::feedRowMatchesQuery($feedRow, $query)) {
                            continue;
                        }

                        $feeds[] = self::parseFeed(
                            $Manager->getFeed((int)$feedRow['id'])
                        );
                    }

                    return [
                        'feeds' => self::applyLimit(array_values($feeds), $limit, $offset)
                    ];
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_feeds_list',
            description: 'Lists existing QUIQQER feeds. Use project and lang to narrow the result.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'project' => ['type' => 'string', 'description' => 'Optional project name filter.'],
                    'lang' => ['type' => 'string', 'description' => 'Optional project language filter.'],
                    'query' => [
                        'type' => 'string',
                        'description' => 'Optional search term for feed ID, name, description or type ID.'
                    ],
                    'limit' => ['type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 100],
                    'offset' => ['type' => 'integer', 'default' => 0, 'minimum' => 0]
                ]
            ]
        );
    }
}

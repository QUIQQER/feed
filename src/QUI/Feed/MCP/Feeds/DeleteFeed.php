<?php

/**
 * This file contains \QUI\Feed\MCP\Feeds\DeleteFeed
 */

namespace QUI\Feed\MCP\Feeds;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\ToolHelper;
use QUI\Feed\MCP\AbstractTool;
use Throwable;

class DeleteFeed extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (
                array $ids
            ): CallToolResult | array {
                try {
                    self::checkFeedPermission();

                    $Manager = self::getManager();
                    $deleted = [];

                    foreach ($ids as $id) {
                        $feedId = (int)$id;

                        if ($feedId < 1) {
                            continue;
                        }

                        $Manager->getFeed($feedId);
                        $Manager->deleteFeed($feedId);
                        $deleted[] = $feedId;
                    }

                    return [
                        'deleted' => $deleted
                    ];
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_feed_delete',
            description: 'Deletes one or more existing QUIQQER feeds.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['ids'],
                'properties' => [
                    'ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer', 'minimum' => 1],
                        'description' => 'Feed IDs to delete.'
                    ]
                ]
            ]
        );
    }
}

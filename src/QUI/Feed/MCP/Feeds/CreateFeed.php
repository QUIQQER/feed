<?php

/**
 * This file contains \QUI\Feed\MCP\Feeds\CreateFeed
 */

namespace QUI\Feed\MCP\Feeds;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\ToolHelper;
use QUI\Feed\MCP\AbstractTool;
use Throwable;

class CreateFeed extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (
                string $project,
                string $lang,
                string $typeId,
                string | null $feedName = null,
                string | null $feedDescription = null,
                int | null $feedlimit = null,
                int | null $pageSize = null,
                bool | null $publish = null,
                array | string | null $publishSites = null,
                string | null $feedImage = null,
                bool | null $directOutput = null,
                array | null $settings = null
            ): CallToolResult | array {
                try {
                    self::checkFeedPermission();

                    $Manager = self::getManager();
                    $params = self::prepareFeedParams(
                        $project,
                        $lang,
                        $typeId,
                        [
                            'feedName' => $feedName ?? '',
                            'feedDescription' => $feedDescription ?? '',
                            'feedlimit' => $feedlimit ?? 0,
                            'pageSize' => $pageSize ?? 0,
                            'publish' => $publish === true ? 1 : 0,
                            'publish_sites' => self::normalizeStringOrJson($publishSites),
                            'feedImage' => $feedImage ?? '',
                            'directOutput' => $directOutput === true ? 1 : 0
                        ],
                        $settings
                    );

                    $Feed = $Manager->addFeed($typeId, $params);

                    return self::parseFeed($Feed, true);
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_feed_create',
            description: 'Creates a new QUIQQER feed for a project and language.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['project', 'lang', 'typeId'],
                'properties' => [
                    'project' => ['type' => 'string', 'description' => 'QUIQQER project name.'],
                    'lang' => ['type' => 'string', 'description' => 'Project language.'],
                    'typeId' => [
                        'type' => 'string',
                        'description' => 'Feed type ID from quiqqer_feed_types_list.'
                    ],
                    'feedName' => ['type' => 'string', 'default' => ''],
                    'feedDescription' => ['type' => 'string', 'default' => ''],
                    'feedlimit' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
                    'pageSize' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
                    'publish' => ['type' => 'boolean', 'default' => false],
                    'publishSites' => [
                        'oneOf' => [
                            ['type' => 'string'],
                            ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]]
                        ],
                        'description' => 'Optional site selection data for header publishing.'
                    ],
                    'feedImage' => ['type' => 'string', 'default' => ''],
                    'directOutput' => ['type' => 'boolean', 'default' => false],
                    'settings' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                        'description' => 'Feed-type-specific settings, for example feedsites or feedsites_exclude.'
                    ]
                ]
            ]
        );
    }
}

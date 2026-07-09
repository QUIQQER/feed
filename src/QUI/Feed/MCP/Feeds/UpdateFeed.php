<?php

/**
 * This file contains \QUI\Feed\MCP\Feeds\UpdateFeed
 */

namespace QUI\Feed\MCP\Feeds;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\ToolHelper;
use QUI\Feed\MCP\AbstractTool;
use Throwable;

use function is_string;

class UpdateFeed extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (
                int $id,
                string | null $project = null,
                string | null $lang = null,
                array | null $attributes = null,
                array | null $settings = null
            ): CallToolResult | array {
                try {
                    self::checkFeedPermission();

                    $Manager = self::getManager();
                    $Feed = $Manager->getFeed($id);
                    $currentAttributes = $Feed->getAttributes();

                    $targetProject = is_string($project) && $project !== ''
                        ? $project
                        : (string)$Feed->getAttribute('project');
                    $targetLang = is_string($lang) && $lang !== ''
                        ? $lang
                        : (string)$Feed->getAttribute('lang');

                    $params = self::prepareFeedParams(
                        $targetProject,
                        $targetLang,
                        $Feed->getTypeId(),
                        $currentAttributes,
                        $settings
                    );

                    foreach (self::normalizeAttributes($attributes) as $attribute => $value) {
                        if ($attribute === 'project' || $attribute === 'lang' || $attribute === 'type_id') {
                            continue;
                        }

                        if ($attribute === 'publish_sites') {
                            $value = self::normalizeStringOrJson($value);
                        }

                        $params[$attribute] = $value;
                    }

                    unset($params['id']);

                    return self::parseFeed(
                        self::saveFeed($Feed, $params),
                        true
                    );
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_feed_update',
            description: 'Updates an existing QUIQQER feed. Only supplied attributes are changed.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['id'],
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Feed ID.', 'minimum' => 1],
                    'project' => ['type' => 'string', 'description' => 'Optional new project name.'],
                    'lang' => ['type' => 'string', 'description' => 'Optional new project language.'],
                    'attributes' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                        'description' => 'General feed attributes such as feedName, feedDescription, feedlimit, pageSize, publish, publish_sites, feedImage or directOutput.'
                    ],
                    'settings' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                        'description' => 'Feed-type-specific settings to replace or add, for example feedsites or feedsites_exclude.'
                    ]
                ]
            ]
        );
    }
}

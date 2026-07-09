<?php

/**
 * This file contains \QUI\Feed\MCP\AbstractTool
 */

namespace QUI\Feed\MCP;

use QUI\AI\MCP\Server;
use QUI\Exception;
use QUI\Feed\Feed;
use QUI\Feed\Manager;
use QUI\MCP\ToolInterface;
use QUI\Permissions\Permission;

use function array_merge;
use function array_slice;
use function is_array;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use function json_encode;
use function max;
use function min;
use function str_contains;
use function strtolower;
use function trim;

abstract class AbstractTool implements ToolInterface
{
    public const FEED_MCP_PERMISSION = 'quiqqer.feed.mcp';

    protected static function checkFeedPermission(): void
    {
        Permission::checkPermission(
            self::FEED_MCP_PERMISSION,
            Server::getRequestUser()
        );

        Permission::checkPermission(
            'quiqqer.admin',
            Server::getRequestUser()
        );
    }

    protected static function getManager(): Manager
    {
        return new Manager();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function parseFeed(Feed $Feed, bool $withAttributes = false): array
    {
        $attributes = $Feed->getAttributes();
        $FeedType = $Feed->getFeedType();

        $result = [
            'id' => $Feed->getId(),
            'typeId' => $Feed->getTypeId(),
            'typeTitle' => $FeedType->getAttribute('title'),
            'project' => (string)$Feed->getAttribute('project'),
            'lang' => (string)$Feed->getAttribute('lang'),
            'feedName' => (string)$Feed->getAttribute('feedName'),
            'feedDescription' => (string)$Feed->getAttribute('feedDescription'),
            'feedlimit' => (int)$Feed->getAttribute('feedlimit'),
            'pageSize' => (int)$Feed->getAttribute('pageSize'),
            'publish' => (bool)$Feed->getAttribute('publish'),
            'publishSites' => $Feed->getAttribute('publish_sites'),
            'feedImage' => $Feed->getAttribute('feedImage'),
            'directOutput' => (bool)$Feed->getAttribute('directOutput'),
            'url' => $Feed->getUrl()
        ];

        if ($withAttributes) {
            $result['attributes'] = $attributes;
            $result['built'] = self::getManager()->isFeedBuilt($Feed);
            $result['pageCount'] = $Feed->getPageCount();
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $type
     * @return array<string, mixed>
     */
    protected static function parseFeedType(array $type, bool $withSettingsHtml = false): array
    {
        $result = [
            'id' => (string)($type['id'] ?? ''),
            'title' => (string)($type['title'] ?? ''),
            'description' => $type['description'] ?? false,
            'attributes' => is_array($type['attributes'] ?? null) ? $type['attributes'] : [],
            'publishable' => (bool)($type['publishable'] ?? false),
            'pagination' => (bool)($type['pagination'] ?? false),
            'mimeType' => (string)($type['mimeType'] ?? 'application/xml')
        ];

        if ($withSettingsHtml) {
            $result['settingsHtml'] = (string)($type['settingsHtml'] ?? '');
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $list
     * @return array<int, array<string, mixed>>
     */
    protected static function applyLimit(array $list, ?int $limit, ?int $offset): array
    {
        return array_slice(
            $list,
            (int)max(0, $offset ?? 0),
            self::sanitizeLimit($limit)
        );
    }

    protected static function sanitizeLimit(?int $limit): int
    {
        if (empty($limit)) {
            return 50;
        }

        return (int)min(100, max(1, $limit));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected static function prepareFeedParams(
        string $project,
        string $lang,
        string $typeId,
        array $params,
        ?array $settings = null
    ): array {
        if ($settings !== null) {
            $params = array_merge($params, $settings);
        }

        $params['project'] = self::encodeProject($project, $lang);
        $params['feedtype'] = $typeId;

        return $params;
    }

    protected static function encodeProject(string $project, string $lang): string
    {
        return (string)json_encode([
            [
                'project' => $project,
                'lang' => $lang
            ]
        ]);
    }

    /**
     * @param array<string, mixed>|null $attributes
     * @return array<string, mixed>
     */
    protected static function normalizeAttributes(?array $attributes): array
    {
        $result = [];

        foreach ($attributes ?? [] as $attribute => $value) {
            if (!is_string($attribute) || $attribute === '') {
                continue;
            }

            $result[$attribute] = self::normalizeAttributeValue($value);
        }

        return $result;
    }

    protected static function normalizeAttributeValue(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value) || is_string($value) || $value === null || is_array($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string)$value;
        }

        return null;
    }

    protected static function normalizeStringOrJson(mixed $value): string
    {
        if (is_array($value)) {
            return (string)json_encode($value);
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $params
     * @throws Exception
     */
    protected static function saveFeed(Feed $Feed, array $params): Feed
    {
        $Manager = self::getManager();

        $Feed->setAttributes(
            $Manager->filterFeedParams($Feed->getTypeId(), $params)
        );

        $Feed->save();

        return $Manager->getFeed($Feed->getId());
    }

    /**
     * @param array<string, mixed> $feedType
     */
    protected static function feedTypeMatchesQuery(array $feedType, ?string $query): bool
    {
        $query = is_string($query) ? trim($query) : '';

        if ($query === '') {
            return true;
        }

        $haystack = strtolower(
            (string)($feedType['id'] ?? '')
            . ' '
            . (string)($feedType['title'] ?? '')
            . ' '
            . (string)($feedType['description'] ?? '')
        );

        return str_contains($haystack, strtolower($query));
    }

    /**
     * @param array<string, mixed> $feedRow
     */
    protected static function feedRowMatchesProject(array $feedRow, ?string $project, ?string $lang): bool
    {
        if (is_string($project) && $project !== '' && ($feedRow['project'] ?? '') !== $project) {
            return false;
        }

        if (is_string($lang) && $lang !== '' && ($feedRow['lang'] ?? '') !== $lang) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $feedRow
     */
    protected static function feedRowMatchesQuery(array $feedRow, ?string $query): bool
    {
        $query = is_string($query) ? trim($query) : '';

        if ($query === '') {
            return true;
        }

        $haystack = strtolower(
            (string)($feedRow['id'] ?? '')
            . ' '
            . (string)($feedRow['feedName'] ?? '')
            . ' '
            . (string)($feedRow['feedDescription'] ?? '')
            . ' '
            . (string)($feedRow['type_id'] ?? '')
        );

        return str_contains($haystack, strtolower($query));
    }
}

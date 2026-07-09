<?php

/**
 * This file contains \QUI\Feed\FeedItemCollection
 */

namespace QUI\Feed;

use QUI\Feed\Interfaces\ChannelInterface;
use QUI\Projects\Media\Image;

use function array_values;
use function count;
use function is_int;
use function is_numeric;
use function is_string;
use function rtrim;
use function trim;
use function uasort;

class FeedItemCollection
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $items = [];

    /**
     * @param array<string, mixed> $item
     */
    public function add(array $item): void
    {
        $link = $item['link'] ?? '';

        if (!is_string($link) || trim($link) === '') {
            return;
        }

        if (empty($item['permalink'])) {
            $item['permalink'] = $link;
        }

        if (empty($item['e_date'])) {
            $item['e_date'] = $item['date'] ?? 0;
        }

        $this->items[] = $item;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function sortByDate(): void
    {
        /**
         * @param array<string, mixed> $left
         * @param array<string, mixed> $right
         */
        $sortByDate = static function (array $left, array $right): int {
            return self::getSortDate($right) <=> self::getSortDate($left);
        };

        uasort($this->items, $sortByDate);

        $this->items = array_values($this->items);
    }

    public function deduplicate(): void
    {
        $known = [];
        $items = [];

        foreach ($this->items as $item) {
            $key = $this->getDeduplicationKey($item);

            if ($key === '') {
                $items[] = $item;
                continue;
            }

            if (isset($known[$key])) {
                continue;
            }

            $known[$key] = true;
            $items[] = $item;
        }

        $this->items = $items;
    }

    public function addToChannel(ChannelInterface $Channel): void
    {
        foreach ($this->items as $item) {
            $Image = $item['image'] ?? null;
            unset($item['image']);

            $Item = $Channel->createItem($item);

            if ($Image instanceof Image) {
                $Item->setImage($Image);
            }
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function getDeduplicationKey(array $item): string
    {
        foreach (['permalink', 'link'] as $field) {
            if (empty($item[$field]) || !is_string($item[$field])) {
                continue;
            }

            return rtrim($item[$field], '/');
        }

        return '';
    }

    /**
     * @param array<string, mixed> $item
     */
    protected static function getSortDate(array $item): int
    {
        foreach (['date', 'e_date'] as $field) {
            if (isset($item[$field]) && is_int($item[$field])) {
                return $item[$field];
            }

            if (isset($item[$field]) && is_numeric($item[$field])) {
                return (int)$item[$field];
            }
        }

        return 0;
    }
}

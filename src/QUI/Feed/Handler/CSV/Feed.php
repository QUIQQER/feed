<?php

/**
 * This file contains \QUI\Feed\Handler\CSV\Feed
 */

namespace QUI\Feed\Handler\CSV;

use DateTimeInterface;
use QUI;
use QUI\Feed\Feed as FeedInstance;
use QUI\Feed\Handler\AbstractSiteFeedType;
use QUI\Feed\Interfaces\ChannelInterface;
use QUI\Feed\Interfaces\FeedItemInterface;
use QUI\Feed\Utils\SimpleXML;

use function date;
use function fclose;
use function fopen;
use function fputcsv;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_resource;
use function is_scalar;
use function is_string;
use function rewind;
use function stream_get_contents;
use function time;
use function trim;

/**
 * Class Feed - CSV feed
 */
class Feed extends AbstractSiteFeedType
{
    /**
     * Create a channel.
     *
     * @return ChannelInterface
     */
    public function createChannel(): ChannelInterface
    {
        $Channel = new Channel();
        $this->addChannel($Channel);

        return $Channel;
    }

    /**
     * Return the Feed as a CSV string.
     *
     * @param FeedInstance $Feed - The Feed that shall be created
     * @param int|null $page (optional) - Not used for CSV feeds
     * @return string - Feed as CSV string
     * @throws QUI\Exception
     */
    public function create(FeedInstance $Feed, ?int $page = null): string
    {
        $Project = $Feed->getProject();
        $projectHost = $Project->getVHost(true, true);

        if (!is_string($projectHost)) {
            $projectHost = '';
        }

        $feedUrl = $projectHost . URL_DIR . 'feed=' . $Feed->getId() . '.csv';

        $Channel = $this->createChannel();
        $Channel->setLanguage($Project->getLang());
        $Channel->setHost($projectHost . URL_DIR);

        $Channel->setAttribute('link', $feedUrl);
        $Channel->setAttribute('description', $Feed->getAttribute('feedDescription'));
        $Channel->setAttribute('title', $Feed->getAttribute('feedName'));
        $Channel->setDate(time());

        $this->addItemsToChannel($Feed, $Channel);

        return $this->getCSV();
    }

    /**
     * CSV feeds do not provide XML output.
     *
     * @return SimpleXML
     * @throws QUI\Exception
     */
    public function getXML(): SimpleXML
    {
        throw new QUI\Exception('CSV feeds do not provide XML output.');
    }

    /**
     * Return CSV output of the feed.
     *
     * @return string
     * @throws QUI\Exception
     */
    public function getCSV(): string
    {
        $Handle = fopen('php://temp', 'r+');

        if (!is_resource($Handle)) {
            throw new QUI\Exception('Could not create temporary CSV stream.');
        }

        fputcsv($Handle, [
            'title',
            'description',
            'language',
            'date',
            'editDate',
            'link',
            'permalink',
            'author',
            'image'
        ]);

        foreach ($this->getChannels() as $Channel) {
            $host = $Channel->getHost();

            foreach ($Channel->getItems() as $Item) {
                fputcsv($Handle, $this->getCsvRow($Item, $host));
            }
        }

        rewind($Handle);
        $csv = stream_get_contents($Handle);
        fclose($Handle);

        if ($csv === false) {
            throw new QUI\Exception('Could not read temporary CSV stream.');
        }

        return $csv;
    }

    /**
     * @param FeedItemInterface $Item
     * @param string $host
     * @return array<int, string>
     */
    protected function getCsvRow(FeedItemInterface $Item, string $host): array
    {
        $imageUrl = '';
        $Image = $Item->getImage();

        if ($Image && $Image->isActive()) {
            $imageUrl = $host . trim($Image->getUrl(), '/');
        }

        return [
            $this->stringify($Item->getAttribute('title')),
            $this->stringify($Item->getAttribute('description')),
            $this->stringify($Item->getAttribute('language')),
            $this->formatDate($Item->getAttribute('date')),
            $this->formatDate($Item->getAttribute('e_date')),
            $this->stringify($Item->getAttribute('link')),
            $this->stringify($Item->getAttribute('permalink')),
            $this->stringify($Item->getAttribute('author')),
            $imageUrl
        ];
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (!is_scalar($value)) {
            return '';
        }

        return (string)$value;
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function formatDate(mixed $value): string
    {
        if (is_int($value)) {
            return date(DateTimeInterface::ATOM, $value);
        }

        if (is_numeric($value)) {
            return date(DateTimeInterface::ATOM, (int)$value);
        }

        return '';
    }
}

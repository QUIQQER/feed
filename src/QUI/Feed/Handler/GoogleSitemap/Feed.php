<?php

/**
 * This file contains \QUI\Feed\Handler\GoogleSitemapFeed
 */

namespace QUI\Feed\Handler\GoogleSitemap;

use DateTimeInterface;
use Exception;
use QUI;
use QUI\ERP\Products\Handler\Products;
use QUI\Feed\Feed as FeedInstance;
use QUI\Feed\Handler\AbstractSiteFeedType;
use QUI\Feed\Interfaces\ChannelInterface;
use QUI\Feed\Interfaces\FeedItemInterface;
use QUI\Feed\Utils\SimpleXML;

use function array_filter;
use function count;
use function mb_strpos;
use function strtotime;

/**
 * Class Feed
 */
class Feed extends AbstractSiteFeedType
{
    /**
     * @var int
     */
    protected int $pageSize = 0;

    /**
     * @var int
     */
    protected int $page = 0;

    /**
     * Create a channel
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
     * Return the Feed as an XML string
     *
     * @param FeedInstance $Feed - The Feed that shall be created
     * @param int|null $page (optional) - Get a specific page of the feed (only required if feed is paginated)
     * @return string - Feed as XML string
     * @throws Exception
     */
    public function create(FeedInstance $Feed, ?int $page = null): string
    {
        $this->setPage($page);
        $this->setPageSize((int)$Feed->getAttribute('pageSize'));

        return parent::create($Feed, $page);
    }

    /**
     * Return XML of the feed
     *
     * @return SimpleXML
     */
    public function getXML(): SimpleXML
    {
        /** @var array<int, FeedItemInterface> $Items */
        $Items = [];
        $Channels = $this->getChannels();

        foreach ($Channels as $Channel) {
            $ChannelItems = $Channel->getItems();
            $Items = array_merge($ChannelItems, $Items);
        }

        // Filter items
        $Items = array_filter($Items, function ($Item) {
            /** @var FeedItemInterface $Item */
            $seoDirective = $Item->getAttribute('seoDirective');

            if (!empty($seoDirective) && mb_strpos($seoDirective, 'noindex') !== false) {
                return false;
            }

            return true;
        });

        if ($this->pageSize == 0) {
            return $this->createSitemapXML($Items);
        }

        // Pagination - index

        // The link attribute ends on .rss - we need to strip that
        $baseURL = (string)$Channels[0]->getAttribute("link");

        if (str_ends_with($baseURL, ".rss")) {
            $baseURL = substr($baseURL, 0, -4);
        }

        // Calculate the pages
        $itemCount = count($Items);
        $pageCount = (int)ceil($itemCount / $this->pageSize);

        // Return the sitemap index for page 0
        if ($this->page == 0) {
            return $this->createSitemapIndexXML($pageCount, $baseURL);
        }

        # Check if the site can exist
        if ($this->page < 0 || $this->page > $pageCount) {
            return $this->createSitemapXML([]);
        }

        // Pagination - page
        $startIndex = ($this->page - 1) * $this->pageSize;
        $pageItems = array_slice($Items, $startIndex, $this->pageSize);

        return $this->createSitemapXML($pageItems);
    }

    /**
     * @param array<int, FeedItemInterface> $items
     *
     * @return SimpleXML
     */
    protected function createSitemapXML(array $items): SimpleXML
    {
        $XML = new SimpleXML(
            '<?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" />'
        );

        foreach ($items as $Item) {
            /* @var $Item FeedItemInterface */
            $ItemXml = $XML->addChild('url');
            $date = $Item->getAttribute('e_date');

            $ItemXml->addChild('loc', $Item->getAttribute('link'));

            $ItemXml->addChild(
                'lastmod',
                date(DateTimeInterface::ATOM, (int)$date)
            );
        }

        return $XML;
    }

    /**
     * @param int $pages
     * @param string $baseURL
     * @return SimpleXML
     */
    protected function createSitemapIndexXML(int $pages, string $baseURL): SimpleXML
    {
        $XML = new SimpleXML(
            '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" />'
        );

        for ($i = 1; $i <= $pages; $i++) {
            $sitemapURL = $baseURL . "-" . $i . ".xml";
            $SitemapXML = $XML->addChild("sitemap");
            $SitemapXML->addChild("loc", $sitemapURL);
        }

        return $XML;
    }

    /**
     * @param int $pageSize
     */
    protected function setPageSize(int $pageSize): void
    {
        $this->pageSize = $pageSize;
    }

    /**
     * @param int|null $page
     */
    protected function setPage(?int $page): void
    {
        $this->page = $page ?? 0;
    }

    /**
     * @return array<int, int|string>
     */
    protected function getFeedProductIds(): array
    {
        if (!class_exists('QUI\ERP\Products\Handler\Products')) {
            return [];
        }

        return Products::getProductIds([
            'where' => [
                'active' => 1,
                'parent' => null
            ]
        ]);
    }

    /**
     * Get total item count for a feed.
     *
     * @param FeedInstance $Feed
     * @return int
     * @throws QUI\Exception
     */
    protected function getTotalItemCount(FeedInstance $Feed): int
    {
        $count = parent::getTotalItemCount($Feed);
        $count += count($this->getFeedProductIds());

        return $count;
    }

    /**
     * Add all relevant items to a feed channel.
     *
     * @param FeedInstance $Feed
     * @param ChannelInterface $Channel
     * @return void
     * @throws QUI\Exception
     */
    protected function addItemsToChannel(FeedInstance $Feed, ChannelInterface $Channel): void
    {
        parent::addItemsToChannel($Feed, $Channel);

        if (!QUI::getPackageManager()->isInstalled('quiqqer/products')) {
            return;
        }

        if (!class_exists('QUI\ERP\Products\Handler\Products')) {
            return;
        }

        if (empty($Feed->getAttribute('includeProductUrls'))) {
            return;
        }

        $productIds = $this->getFeedProductIds();
        $Project = $Feed->getProject();
        $lang = $Project->getLang();
        $Locale = new QUI\Locale();
        $Locale->setCurrent($lang);

        foreach ($productIds as $productId) {
            if (!is_numeric($productId)) {
                continue;
            }

            $pid = (int)$productId;

            if ($pid <= 0) {
                continue;
            }

            try {
                $Product = Products::getProduct($pid);
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                continue;
            }

            $Channel->createItem([
                'title' => $Product->getTitle($Locale),
                'description' => $Product->getDescription($Locale),
                'language' => $Project->getLang(),
                'date' => strtotime($Product->getAttribute('c_date')),
                'e_date' => strtotime($Product->getAttribute('e_date')),
                'link' => $Product->getUrlRewrittenWithHost($Project),
                'permalink' => null,
                'seoDirective' => null
            ]);
        }
    }
}

<?php

namespace QUI\Feed\Handler\RSS;

use DateTimeInterface;
use QUI;
use QUI\Exception;
use QUI\Feed\Handler\AbstractSiteFeedType;
use QUI\Feed\Interfaces\ChannelInterface;
use QUI\Feed\Utils\SimpleXML;
use QUI\Feed\Utils\Utils;
use QUI\Projects\Media\Image;
use SimpleXMLElement;

/**
 * Class Feed - RSS Feed 2.0
 */
class Feed extends AbstractSiteFeedType
{
    /**
     * Creat a channel
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
     * Return XML of the feed
     *
     * @throws Exception
     */
    public function getXML(): SimpleXML
    {
        $XML = new SimpleXML(
            '<?xml version="1.0" encoding="UTF-8" ?>
             <rss version="2.0" xmlns:atom="https://www.w3.org/2005/Atom" />'
        );

        $channels = $this->getChannels();

        foreach ($channels as $Channel) {
            /* @var $Channel Channel */
            $ChannelXml = $XML->addChild('channel');

            $host = $Channel->getHost();
            $date = date(
                DateTimeInterface::RFC2822,
                (int)$Channel->getAttribute('timestamp')
            );

            $Atomlink = $ChannelXml->addChild(
                'link',
                '',
                'http://www.w3.org/2005/Atom'
            );

            $Atomlink->addAttribute('href', Utils::fixLinkProtocol($Channel->getAttribute('link')));
            $Atomlink->addAttribute('rel', "self");
            $Atomlink->addAttribute('type', "application/rss+xml");

            $ChannelXml->addChild('link', Utils::fixLinkProtocol($Channel->getAttribute('link')));
            $ChannelXml->addChild('lastBuildDate', $date);
            $ChannelXml->addChild('language', $Channel->getAttribute('language'));
            $ChannelXml->addChild('description')->addCData($Channel->getAttribute('description'));
            $ChannelXml->addChild('title')->addCData($Channel->getAttribute('title'));

            // channel feed items
            $items = $Channel->getItems();

            foreach ($items as $Item) {
                /* @var $Item Item */
                $date = date(
                    DateTimeInterface::RFC2822,
                    (int)$Item->getAttribute('date')
                );

                $ItemXml = $ChannelXml->addChild('item');
                $ItemXml->addChild('link', Utils::fixLinkProtocol($Item->getAttribute('link')));
                $ItemXml->addChild('pubDate', $date);
                $ItemXml->addChild('guid', Utils::fixLinkProtocol($Item->getAttribute('permalink')));
                $ItemXml->addChild('title')->addCData($Item->getAttribute('title'));
                $ItemXml->addChild('description')->addCData($Item->getAttribute('description'));

                /* @var $Image Image */
                $Image = $Item->getImage();

                if (!$Image) {
                    continue;
                }

                if (!$Image->isActive()) {
                    continue;
                }

                $maxSize = QUI::getPackage("quiqqer/feed")->getConfig()?->get("images", "maxsize");

                if ($maxSize !== null) {
                    $Image->setAttribute("maxheight", $maxSize);
                    $Image->setAttribute("maxwidth", $maxSize);
                }

                $EnclosureDom = $ItemXml->addChild('enclosure');
                $EnclosureDom->addAttribute(
                    'url',
                    Utils::fixLinkProtocol($host . trim($Image->getUrl(), '/'))
                );

                $EnclosureDom->addAttribute(
                    'length',
                    $Image->getAttribute('filesize')
                );

                $EnclosureDom->addAttribute(
                    'type',
                    $Image->getAttribute('mime_type')
                );
            }
        }

        return $XML;
    }
}

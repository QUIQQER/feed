/**
 * Feed settings
 */
define('package/quiqqer/feed/bin/Feed', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',

    'qui/utils/Form',
    'utils/Controls',
    'Ajax',
    'Locale',
    'Mustache',

    'text!package/quiqqer/feed/bin/Feed.html',
    'css!package/quiqqer/feed/bin/Feed.css'

], function (QUI, QUIControl, QUILoader, QUIFormUtils, ControlUtils, QUIAjax, QUILocale, Mustache,
             template) {
    'use strict';

    const lg = 'quiqqer/feed';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/feed/bin/Feed',

        Binds: [
            '$toogglePageSizeVisibility',
            '$togglePublishSitesVisibility',
            '$detectSplitOption',
            '$onFeedTypeChange',
            '$onProjectChange',
            '$getFeedData'
        ],

        initialize: function (options) {
            // defaults
            this.setAttributes({
                feedId   : false,
                project  : '',
                lang     : '',
                feedsites: '',
                feedtype : 'rss',
                feedlimit: '10',
                pageSize : 0,
                publish  : 0
            });

            this.parent(options);

            this.$FeedTypeSelect            = null;
            this.$FeedTypeDescription       = null;
            this.$FeedTypeSettingsContainer = null;
            this.$EditFeedData              = null;

            this.$Project              = null;
            this.$ProjectSelectControl = null;

            this.$Limit                      = null;
            this.$Name                       = null;
            this.$Desc                       = null;
            this.$PageSize                   = null;
            this.$SplitCheckbox              = null;
            this.$Image                      = null;
            this.$PublishSiteSelectContainer = null;
            this.$FeedTypes                  = {};

            this.Loader = new QUILoader();

        },

        /**
         * create the DOMNode
         *
         * @return {HTMLElement}
         */
        create: function () {
            this.$Elm = new Element('div', {
                'class': 'qui-control-feed',
                html   : Mustache.render(template, {
                    locales: {
                        feedType                   : QUILocale.get(lg, 'quiqqer.feed.feedtype'),
                        feedName                   : QUILocale.get(lg, 'quiqqer.feed.feedName'),
                        feedDescription            : QUILocale.get(lg, 'quiqqer.feed.feedDescription'),
                        project                    : QUILocale.get('quiqqer/system', 'project'),
                        feedlimit                  : QUILocale.get(lg, 'quiqqer.feed.feedlimit'),
                        directOutput               : QUILocale.get(lg, 'quiqqer.feed.directOutput'),
                        directOutputDescription    : QUILocale.get(lg, 'quiqqer.feed.directOutputDescription'),
                        feedSites                  : QUILocale.get(lg, 'quiqqer.feed.feedSites'),
                        feedSitesPlaceholder       : QUILocale.get(lg, 'quiqqer.feed.sites.placeholder'),
                        feedSitesExcludePlaceholder: QUILocale.get(lg, 'quiqqer.feed.sites_exclude.placeholder'),
                        split                      : QUILocale.get(lg, 'quiqqer.feed.split'),
                        pageSize                   : QUILocale.get(lg, 'quiqqer.feed.pageSize'),
                        publish                    : QUILocale.get(lg, 'quiqqer.feed.publish'),
                        publishSitesPlaceholder    : QUILocale.get(lg, 'quiqqer.feed.publish.sites.placeholder'),
                        feedImage                  : QUILocale.get(lg, 'quiqqer.feed.image'),
                        publishSitesLabel          : QUILocale.get(lg, 'quiqqer.feed.publishSitesLabel'),
                        headerGeneralSettings      : QUILocale.get(lg, 'quiqqer.feed.headerGeneralSettings')
                    }
                })
            });

            this.Loader.inject(this.$Elm);

            this.$FeedTypeSelect            = this.$Elm.getElement('[name="feedtype"]');
            this.$FeedTypeDescription       = this.$Elm.getElement('.qui-control-feed-type-description');
            this.$FeedTypeSettingsContainer = this.$Elm.getElement('.qui-control-feed-type-settings');

            this.$Project                    = this.$Elm.getElement('[name="project"]');
            this.$Limit                      = this.$Elm.getElement('[name="feedlimit"]');
            this.$Name                       = this.$Elm.getElement('[name="feedName"]');
            this.$Desc                       = this.$Elm.getElement('[name="feedDescription"]');
            this.$SplitCheckbox              = this.$Elm.getElement('[name="split"]');
            this.$PageSize                   = this.$Elm.getElement('[name="pageSize"]');
            this.$PublishCheckbox            = this.$Elm.getElement('[name="publish"]');
            this.$Image                      = this.$Elm.getElement('[name="feedImage"]');
            this.$PublishSiteSelectContainer = this.$Elm.getElement('.qui-feed-publish-sites-container');

            var self = this;
            // Split Feed event
            //self.$Elm.getElement('.qui-feed-feedwindow-split-label').addEvent('click', function () {
            //    self.$SplitCheckbox.checked = !self.$SplitCheckbox.checked;
            //    self.$toogglePageSizeVisibility();
            //});
            this.$SplitCheckbox.addEvent('change', self.$toogglePageSizeVisibility);

            // Publish sites event
            this.$PublishCheckbox.addEvent('change', self.$togglePublishSitesVisibility);

            //this.$Elm.getElement('.qui-feed-feedwindow-publish-label').addEvent('click', function () {
            //    self.$PublishCheckbox.checked = !self.$PublishCheckbox.checked;
            //    self.$togglePublishSitesVisibility();
            //});

            // Project select event
            this.$Project.addEvent('change', this.$onProjectChange);

            // Hide the split option, if it is not supported for the feed type
            //this.$FeedTypeSelect.addEvent('change', self.$detectSplitOption);
            this.$FeedTypeSelect.addEvent('change', this.$onFeedTypeChange);

            this.Loader.show();

            this.$getFeedTypes().then((feedTypes) => {
                for (const FeedType of Object.values(feedTypes)) {
                    this.$FeedTypes[FeedType.id] = FeedType;

                    new Element('option', {
                        value: FeedType.id,
                        html : FeedType.title
                    }).inject(this.$FeedTypeSelect);
                }

                if (this.getAttribute('feedId')) {
                    return this.$getFeedData().then((FeedData) => {
                        this.$EditFeedData = FeedData;

                        FeedData.project = JSON.encode([{
                            project: FeedData.project,
                            lang   : FeedData.lang
                        }]);

                        const FeedType = this.$FeedTypes[this.$EditFeedData.type_id];

                        this.$FeedTypeSettingsContainer.set('html', FeedType.settingsHtml);

                        const PublishOption = this.$Elm.getElement('.qui-feed-feetwindow-publish');

                        if (!FeedType.publishable) {
                            PublishOption.addClass('qui-control-feed__hidden');
                        } else {
                            PublishOption.removeClass('qui-control-feed__hidden');
                        }

                        QUIFormUtils.setDataToNode(this.$EditFeedData, this.$Elm);

                        this.$FeedTypeSelect.value = this.$EditFeedData.type_id;

                        if (FeedType.description) {
                            this.$FeedTypeDescription.set('html', FeedType.description);
                            this.$FeedTypeDescription.removeClass('qui-control-feed__hidden');
                        }
                    });
                } else {
                    return this.$onFeedTypeChange();
                }
            }).then(() => {
                return ControlUtils.parse(this.$Elm);
            }).then(() => {
                return QUI.parse(this.$Elm);
            }).then(() => {
                this.$ProjectSelectControl = QUI.Controls.getById(
                    this.$Project.get('data-quiid')
                );

                this.$onProjectChange();
                this.$detectSplitOption();

                if (this.$EditFeedData && this.$EditFeedData.publish) {
                    this.$togglePublishSitesVisibility();
                }

                this.Loader.hide();
            });

            return this.$Elm;
        },

        /**
         * Return the feed attributes
         *
         * @returns {Object}
         */
        getFeedData: function () {
            const Data     = QUIFormUtils.getDataFromNode(this.$Elm);
            const FeedType = this.$FeedTypes[Data.feedtype];

            const allowedAttributes = [
                'feedDescription',
                'feedImage',
                'feedName',
                'feedlimit',
                'feedtype',
                'pageSize',
                'project',
                'publish',
                'publish-sites',
                'split',
                'directOutput'
            ];

            allowedAttributes.append(FeedType.attributes);

            const FeedData = {};

            allowedAttributes.forEach((feedAttribute) => {
                if (feedAttribute in Data) {
                    FeedData[feedAttribute] = Data[feedAttribute];
                }
            });

            return FeedData;
        },

        /**
         * Save the feed
         *
         * @return {Promise}
         */
        save: function () {
            return new Promise((resolve, reject) => {
                QUIAjax.post('package_quiqqer_feed_ajax_setFeed', resolve, {
                    'package': lg,
                    feedId   : this.getAttribute('feedId'),
                    params   : JSON.encode(this.getFeedData()),
                    onError  : reject
                });
            });
        },

        /**
         * Toggles the visibility of the page size input field
         */
        $toogglePageSizeVisibility: function () {
            var pageSizeContainer = this.$Elm.getElement('.qui-feed-feetwindow-pageSize');

            if (this.$SplitCheckbox.checked) {
                pageSizeContainer.style.display = 'block';
            } else {
                pageSizeContainer.style.display = 'none';
            }
        },

        /**
         * Toggles the visibility of the input to select sites on which the feed should get displayed
         */
        $togglePublishSitesVisibility: function () {
            if (this.$PublishCheckbox.checked) {
                this.$PublishSiteSelectContainer.style.display = 'block';
            } else {
                this.$PublishSiteSelectContainer.style.display = 'none';
            }
        },

        /**
         * Hides/Shows the visiblity of thec checkbox to split the feed into pages, depending o the current selected feeds support for pagination
         */
        $detectSplitOption: function () {
            const FeedType = this.$FeedTypes[this.$FeedTypeSelect.value];

            const PageSizeInputSetting = this.$Elm.getElement('.qui-feed-feetwindow-pageSize');
            const PaginationSetting    = this.$Elm.getElement('.qui-feed-feedwindow-split');

            if (!FeedType.pagination) {
                this.$SplitCheckbox.checked = false;

                PageSizeInputSetting.addClass('qui-control-feed__hidden');
                PaginationSetting.addClass('qui-control-feed__hidden');
            } else {
                PaginationSetting.removeClass('qui-control-feed__hidden');

                if (this.$EditFeedData && parseInt(this.$EditFeedData.pageSize)) {
                    this.$SplitCheckbox.checked = true;

                    this.$Elm.getElement('.qui-feed-feetwindow-pageSize').value = this.$EditFeedData.pageSize;

                    PageSizeInputSetting.removeClass('qui-control-feed__hidden');
                }
            }
        },

        /**
         * If the user changes the project
         */
        $onProjectChange: function () {
            if (!this.$ProjectSelectControl) {
                return;
            }

            const siteSelectControlElements = this.$Elm.getElements(
                '[data-qui="controls/projects/project/site/Select"]'
            );

            this.$ProjectSelectControl.getProjects().each(function (Project) {
                siteSelectControlElements.forEach((ControlElm) => {
                    const Control = QUI.Controls.getById(ControlElm.get('data-quiid'));

                    Control.setProject(
                        Project.getName(),
                        Project.getLang()
                    );
                });
            });
        },

        /**
         * Event: on feed type change
         */
        $onFeedTypeChange: function () {
            const FeedType = this.$FeedTypes[this.$FeedTypeSelect.value];

            if (FeedType.description) {
                this.$FeedTypeDescription.set('html', FeedType.description);
                this.$FeedTypeDescription.removeClass('qui-control-feed__hidden');
            } else {
                this.$FeedTypeDescription.addClass('qui-control-feed__hidden');
            }

            this.$FeedTypeSettingsContainer.set('html', FeedType.settingsHtml);

            this.Loader.show();

            const PublishOption = this.$Elm.getElement('.qui-feed-feetwindow-publish');

            if (!FeedType.publishable) {
                PublishOption.addClass('qui-control-feed__hidden');
            } else {
                PublishOption.removeClass('qui-control-feed__hidden');
            }

            return QUI.parse(this.$FeedTypeSettingsContainer).then(() => {
                this.Loader.hide();
                this.$detectSplitOption();
            });
        },

        /**
         * Get data for feed
         *
         * @return {Promise}
         */
        $getFeedData: function () {
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_feed_ajax_getFeed', resolve, {
                    'package': lg,
                    feedId   : this.getAttribute('feedId'),
                    onError  : reject
                });
            });
        },

        /**
         * Get all feed types installed on this system
         *
         * @return {Promise}
         */
        $getFeedTypes: function () {
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_feed_ajax_backend_getTypes', resolve, {
                    'package': lg,
                    onError  : reject
                });
            });
        }
    });
});
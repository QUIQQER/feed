define('package/quiqqer/feed/bin/frontend/controls/FeedList', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax',
    'Mustache',
    'Locale',

    'text!package/quiqqer/feed/bin/frontend/controls/FeedList.html',
    'text!package/quiqqer/feed/bin/frontend/controls/FeedListEntry.html',
    'css!package/quiqqer/feed/bin/frontend/controls/FeedList.css'

], function (QUI, QUIControl, QUIAjax, Mustache, QUILocale, template, entryTemplate) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/feed/bin/frontend/controls/FeedList',


        Binds: [
            "$onInject"
        ],

        options: {},

        initialize: function (options) {
            // This bsiacally is the Controls constructor
            var self = this;
            this.parent(options);


            this.$List = null;

            this.addEvents({
                onInject: self.$onInject
            });
        },

        $onInject: function () {
            this.refresh();
        },

        /**
         * Return the DOMNode Element
         *
         * @returns {Element}
         */
        create: function () {

            var self = this;

            this.parent();

            this.$Elm = new Element('div', {
                html: Mustache.render(template, {})
            });

            this.$List = this.$Elm.getElement(".quiqqer-feeds-feedlist-list");

            return this.$Elm;
        },

        /**
         * Refreshes the controls content
         * @returns {*}
         */
        refresh: function () {

            var self = this;

            return new Promise(function (resolve, reject) {

                QUIAjax.get("package_quiqqer_feed_ajax_frontend_controls_feedlist_getFeeds", function (result) {

                    self.$List.empty();
                    for (var index in result) {
                        if (!result.hasOwnProperty(index)) {
                            continue;
                        }

                        var row = result[index];
                        
                        var ListElement = new Element("article", {
                            'class': "qui-feed-feedList-default-entry",
                            html   : Mustache.render(entryTemplate, {
                                name   : row.feedName,
                                url    : row.url,
                                img    : row.feedImage,
                                desc   : row.feedDescription,
                                locales: {
                                    subscribe: QUILocale.get("quiqqer/feed", "quiqqer.feed.button.subscribe")
                                }
                            })
                        });

                        ListElement.inject(self.$List);
                    }

                    resolve();
                }, {
                    'package': "quiqqer/feed",
                    onError  : reject
                })
            });
        }

    });
});

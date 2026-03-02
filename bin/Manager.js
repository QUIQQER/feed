/**
 * Feed manager
 */
define('package/quiqqer/feed/bin/Manager', [

    'qui/controls/desktop/Panel',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',

    'controls/grid/Grid',
    'package/quiqqer/feed/bin/FeedWindow',
    'Locale',
    'Ajax',
    'Projects',

    'css!package/quiqqer/feed/bin/Manager.css'

], function (QUIPanel, QUIConfirm, QUIButton, Grid, FeedWindow, QUILocale, QUIAjax, Projects) {
    "use strict";

    var lg = 'quiqqer/feed';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/feed/bin/Manager',

        Binds: [
            '$onCreate',
            '$onResize',
            '$onClickDownload',
            '$onClickRecreate',
            '$recreateFeed'
        ],

        options: {
            title: QUILocale.get(lg, 'feed.manager.title')
        },

        initialize: function (options) {
            this.parent(options);

            this.$Grid = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onResize: this.$onResize
            });
        },

        /**
         * Create the panel
         */
        $onCreate: function () {
            var self = this;

            this.addButton({
                name     : 'add',
                text     : QUILocale.get(lg, 'quiqqer.feed.btn.create'),
                textimage: 'fa fa-plus',
                events   : {
                    onClick: function () {
                        self.openFeedWindow();
                    }
                }
            });

            this.addButton({
                type: 'seperator'
            });

            this.addButton({
                name     : 'delete',
                text     : QUILocale.get(lg, 'quiqqer.feed.btn.delete'),
                textimage: 'fa fa-trash',
                disabled : true,
                events   : {
                    onClick: function () {
                        var ids = self.$Grid.getSelectedData().map(function (entry) {
                            return entry.id;
                        });

                        self.openFeedDeleteWindow(ids);
                    }
                }
            });

            // Grid
            var Content   = this.getContent(),
                Container = new Element('div').inject(Content);

            this.$Grid = new Grid(Container, {
                columnModel          : [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'string',
                    width    : 40
                }, {
                    header   : QUILocale.get('quiqqer/core', 'title'),
                    dataIndex: 'feedName',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'quiqqer.feed.feedtype'),
                    dataIndex: 'feedtype_title',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get('quiqqer/system', 'project'),
                    dataIndex: 'project',
                    dataType : 'string',
                    width    : 120
                }, {
                    header   : QUILocale.get('quiqqer/system', 'language'),
                    dataIndex: 'lang',
                    dataType : 'string',
                    width    : 80
                }, {
                    header   : QUILocale.get(lg, 'quiqqer.feed.feedlimit'),
                    dataIndex: 'feedlimit',
                    dataType : 'string',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'quiqqer.feed.pageSize'),
                    dataIndex: 'pageSize',
                    dataType : 'string',
                    width    : 80
                }, {
                    header   : QUILocale.get(lg, 'quiqqer.feed.displayInHeader'),
                    dataIndex: 'displayInHeader',
                    dataType : 'string',
                    width    : 75
                }, {
                    header   : QUILocale.get(lg, 'quiqqer.feed.actions'),
                    dataIndex: 'actions',
                    dataType : 'node',
                    width    : 150
                }],
                pagination           : true,
                multipleSelection    : true,
                accordion            : true,
                openAccordionOnClick : false,
                accordionLiveRenderer: function (data) {
                    var GridObj = data.grid,
                        Parent  = data.parent,
                        row     = data.row,
                        rowData = GridObj.getDataByRow(row);

                    Parent.set('html', '');

                    var Project = Projects.get(rowData.project, rowData.lang);

                    Project.getHost(function (host) {

                        var url = host + '/feed=' + rowData.id + '.xml';

                        new Element('div', {
                            html  : 'Feed URL: <a href="' + url + '" target="_blank">' + url + '</a>',
                            styles: {
                                padding: 10
                            }
                        }).inject(Parent);
                    });
                }
            });

            this.$Grid.addEvents({

                onRefresh: function () {
                    self.refresh();
                },

                onDblClick: function (event) {
                    self.openFeedWindow(
                        self.$Grid.getDataByRow(event.row).id
                    );
                },

                onClick: function () {
                    self.getButtons('delete').enable();
                }
            });

            this.refresh();
        },

        /**
         * Load the feeds
         */
        refresh: function () {
            if (!this.$Grid) {
                return;
            }

            var self = this;

            QUIAjax.get('package_quiqqer_feed_ajax_getList', (result) => {
                result.data = result.data.map((Row) => {
                    Row.pageSize = parseInt(Row.pageSize) ? Row.pageSize : '-';

                    Row.actions = new Element('div', {
                        'class': 'quiqqer-feeds-manager-actions'
                    });

                    new QUIButton({
                        'class': 'quiqqer-feeds-manager-actions-btn',
                        icon   : 'fa fa-download',
                        title  : QUILocale.get(lg, 'quiqqer.feed.action.download'),
                        feedId : Row.id,
                        events : {
                            onClick: this.$onClickDownload
                        }
                    }).inject(Row.actions);

                    new QUIButton({
                        'class': 'quiqqer-feeds-manager-actions-btn',
                        icon   : 'fa fa-repeat',
                        title  : QUILocale.get(lg, 'quiqqer.feed.action.recreate'),
                        feedId : Row.id,
                        events : {
                            onClick: this.$onClickRecreate
                        }
                    }).inject(Row.actions);

                    return Row;
                });

                self.$Grid.setData(result);
            }, {
                'package' : 'quiqqer/feed',
                gridParams: JSON.encode(this.$Grid.getPaginationData())
            });
        },

        /**
         * panel resize
         */
        $onResize: function () {
            if (!this.$Grid) {
                return;
            }

            var Body = this.getContent();

            if (!Body) {
                return;
            }

            var size = Body.getSize();

            this.$Grid.setHeight(size.y - 40);
            this.$Grid.setWidth(size.x - 40);
        },

        /**
         * Open the feed window
         *
         * @param {Number} [feedId] - (optional) ID of the Feed, if no ID a new Feed would be added
         */
        openFeedWindow: function (feedId) {
            var self = this;

            new FeedWindow({
                feedId: feedId || false,
                events: {
                    onClose: function () {
                        self.refresh();
                    }
                }
            }).open();
        },

        /**
         * Open the feed deletion
         *
         * @param {Array} feedIds - ID of the Feed
         */
        openFeedDeleteWindow: function (feedIds) {
            if (typeOf(feedIds) !== 'array') {
                return;
            }

            const self = this;

            // #locale
            new QUIConfirm({
                title      : QUILocale.get(lg, 'quiqqer.feed.action.delete.title'),
                icon       : 'fa fa-trash',
                text       : QUILocale.get(lg, 'quiqqer.feed.action.delete.text'),
                information: feedIds.join(', '),
                autoclose  : false,
                events     : {
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        QUIAjax.post('package_quiqqer_feed_ajax_delete', function () {
                            self.refresh();
                            Win.close();

                        }, {
                            'package': 'quiqqer/feed',
                            feedIds  : JSON.encode(feedIds)
                        });
                    }
                }
            }).open();
        },

        /**
         * Download feed file
         *
         * @param {Object} Btn - QUIButton
         */
        $onClickDownload: function (Btn) {
            const feedId = Btn.getAttribute('feedId');

            this.Loader.show();

            this.$downloadCheck(feedId).then((isDownloadable) => {
                this.Loader.hide();

                if (!isDownloadable) {
                    new QUIConfirm({
                        maxHeight: 300,
                        maxWidth : 500,

                        autoclose         : true,
                        backgroundClosable: false,

                        information: QUILocale.get(lg, 'quiqqer.feed.prompot.not_downloadable.information'),
                        title      : QUILocale.get(lg, 'quiqqer.feed.prompot.not_downloadable.title'),
                        texticon   : 'fa fa-download',
                        text       : QUILocale.get(lg, 'quiqqer.feed.prompot.not_downloadable.text'),
                        icon       : 'fa fa-download',

                        cancel_button: false,
                        ok_button    : {
                            text     : QUILocale.get(lg, 'quiqqer.feed.prompot.not_downloadable.ok'),
                            textimage: 'icon-ok fa fa-check'
                        }
                    }).open();

                    return;
                }

                const uid = String.uniqueID();
                const id  = 'download-feed-file-' + uid;

                new Element('iframe', {
                    src   : URL_OPT_DIR + 'quiqqer/feed/bin/backend/download.php?' + Object.toQueryString({
                        id: feedId,
                    }),
                    id    : id,
                    styles: {
                        position: 'absolute',
                        top     : -200,
                        left    : -200,
                        width   : 50,
                        height  : 50
                    }
                }).inject(document.body);

                (function () {
                    document.getElements('#' + id).destroy();
                }).delay(20000, this);
            });
        },

        /**
         * Re-create feed
         *
         * @param {Object} Btn - QUIButton
         */
        $onClickRecreate: function (Btn) {
            const feedId = Btn.getAttribute('feedId');

            new QUIConfirm({
                maxHeight: 300,
                maxWidth : 500,

                autoclose         : false,
                backgroundClosable: true,

                information: QUILocale.get(lg, 'quiqqer.feed.dialog.recreate.information', {
                    feedId: feedId
                }),
                title      : QUILocale.get(lg, 'quiqqer.feed.dialog.recreate.title'),
                texticon   : 'fa fa-repeat',
                text       : QUILocale.get(lg, 'quiqqer.feed.dialog.recreate.text'),
                icon       : 'fa fa-repeat',

                cancel_button: {
                    text     : false,
                    textimage: 'icon-remove fa fa-remove'
                },
                ok_button    : {
                    text     : QUILocale.get(lg, 'quiqqer.feed.dialog.recreate.btn.confirm'),
                    textimage: 'icon-ok fa fa-check'
                },
                events       : {
                    onSubmit: (Win) => {
                        Win.Loader.show();

                        this.$recreateFeed(feedId).then(() => {
                            Win.close();
                        }).catch(() => {
                            Win.Loader.hide();
                        });
                    }
                }
            }).open();
        },

        /**
         * Recreate feed
         *
         * @param {Number} feedId
         * @return {Promise}
         */
        $recreateFeed: function (feedId) {
            return new Promise((resolve, reject) => {
                QUIAjax.post('package_quiqqer_feed_ajax_backend_recreate', resolve, {
                    'package': lg,
                    feedId   : feedId,
                    onError  : reject
                });
            });
        },

        /**
         * Check if a feed is downloadable
         *
         * @param {Number} feedId
         * @return {Promise}
         */
        $downloadCheck: function (feedId) {
            return new Promise((resolve, reject) => {
                QUIAjax.post('package_quiqqer_feed_ajax_backend_downloadCheck', resolve, {
                    'package': lg,
                    feedId   : feedId,
                    onError  : reject
                });
            });
        },
    });
});

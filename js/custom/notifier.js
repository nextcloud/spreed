/**
 * Web Browser Notification.
 *
 * Responsible for firing web browser notification,
 * for every new message received.
 *
 * @author Oozman <oomarusman@gmail.com>
 */
(function (OCA, OC, $, moment, _, localforage) {
    'use strict';

    /**
     * Set notification audio file path.
     *
     * @type {string}
     */
    let basePath = OC.generateUrl("").replace("/index.php/", "");
    let audioFile = basePath + "/apps/spreed/audio/notify.mp3";

    // if in localhost, do the right audio path.
    if ($(location).attr("href").search("//localhost") > 0) {
        audioFile = basePath + "/custom_apps/spreed/audio/notify.mp3";
    }

    OCA.Talk = OCA.Talk || {};
    OCA.Talk.Notifier = {

        /**
         * App instance.
         */
        app: null,

        /**
         * Current user.
         */
        currentUser: "",

        /**
         * iNotify instance.
         */
        notifier: new Notify({
            effect: 'scroll',
            interval: 300,
            audio: {
                file: audioFile
            },
            updateFavicon: {
                textColor: "#222",
                backgroundColor: "#FDCD4D"
            }
        }).setFavicon("..."),

        /**
         * Notifications queue.
         */
        notificationQueue: [],

        /**
         * Initialize.
         *
         * @param a
         */
        init(a) {

            let self = this;

            self.app = a;

            self.currentUser = OC.getCurrentUser().uid;

            self.app.signaling.on("fireNotifier", () => {
                self.fetchRoomInfo();
            });

            console.log("Notifier is up!");
        },

        /**
         * Fetch room info.
         */
        fetchRoomInfo() {

            const self = this;
            const roomsInfo = self.roomsInfo();

            roomsInfo.getValue().then(val => {

                val = roomsInfo.cleanRoomData(val);

                const currentRooms = _.isEmpty(val) ? roomsInfo.getInitialRooms() : val;

                $.ajax({
                    url: OC.linkToOCS('apps/spreed/api/v1', 2) + "chat/roomsInfo",
                    headers: {'Accept': 'application/json'},
                    type: 'POST',
                    data: {
                        roomsInfo: currentRooms
                    }
                }).done(function (response) {

                    // Fire notifications, if any.
                    self.fireNotifications(response.ocs.data.roomsInfo.comments);

                    // Update current room info.
                    roomsInfo.setValue(response.ocs.data.roomsInfo.rooms);
                });
            });
        },

        /**
         * Fire notifications.
         *
         * @param comments
         */
        fireNotifications(comments) {

            const self = this;

            _.each(comments, comment => {
                if (self.currentUser !== comment.actor_id) {
                    self.notify("New message from " + comment.actor_id, comment.message);
                }
            });
        },

        /**
         * Show notification.
         *
         * @param title
         * @param message
         */
        notify(title, message) {

            // Clear everything first.
            this.notifier.setTitle();

            this.notifier.setTitle(message);
            this.notifier.notify({
                title: title,
                onclick: function () {

                    // Do nothing.
                    return false;
                },
                body: message
            }).player();
        },

        /**
         * Rooms info.
         *
         * @returns {{getValue(): *, keys: {roomsKey: string}, setValue(*=): *, getInitialRooms(): *, cleanRoomData(*=): *}|Array|void | *|SVGPoint | SVGTransform | SVGNumber | string | T | SVGLength | SVGPathSeg | *}
         */
        roomsInfo() {

            const self = this;

            return {
                keys: {
                    roomsKey: '_roomsinfo_rooms'
                },

                /**
                 * Get initial rooms joined to.
                 *
                 * @returns {Array}
                 */
                getInitialRooms() {

                    let rooms = [];

                    if (_.has(self.app._rooms, "models")) {
                        _.each(self.app._rooms.models, model => {
                            let val = {token: model.attributes.token, last_message: 0};
                            rooms.push(val);
                        });
                    }

                    return rooms;
                },

                /**
                 * Clean room data.
                 *
                 * @param val
                 * @returns {Array}
                 */
                cleanRoomData(val) {

                    let value = [];

                    _.each(val, item => {
                        if (_.has(item, "token") && !_.isEmpty(item.token)) {
                            value.push(item);
                        }
                    });

                    return value;
                },

                /**
                 * Get local storage room info.
                 *
                 * @returns {SVGPoint | SVGTransform | SVGNumber | string | T | SVGLength | SVGPathSeg | *}
                 */
                getValue() {
                    return localforage.getItem(this.keys.roomsKey);
                },

                /**
                 * Set local storage room info.
                 *
                 * @param val
                 * @returns {void | *}
                 */
                setValue(val) {
                    return localforage.setItem(this.keys.roomsKey, val);
                }
            }
        }
    }
})(OCA, OC, $, moment, _, localforage);

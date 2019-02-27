/**
 * Web Browser Notification.
 *
 * Responsible for firing web browser notification,
 * for every new message received.
 *
 * @author Oozman <hi@oozman>
 */
(function (OCA, OC, $) {
    'use strict';

    /**
     * Set notification audio file path.
     *
     * @type {string}
     */
    var basePath = OC.generateUrl("").replace("/index.php/", "");
    var audioFile = basePath + "/custom_apps/spreed/audio/notify.mp3";

    // if not in localhost, do the right audio path.
    if ($(location).attr("href").search("//localhost") < 0) {
        audioFile = basePath + "/apps/spreed/audio/notify.mp3";
    }

    var app = null;

    /**
     * Timeout interval in seconds.
     * @type {*|{}}
     */
    var interval = 3000;

    var previousTokenUrlKey = "_prt";
    var previousRoomToken = "";

    OCA.Talk = OCA.Talk || {};
    OCA.Talk.Notifier = {
        // iNotify instance.
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

        // The last rooms data. Useful for not firing duplicate notifications.
        rooms: [],

        // The list of rooms in call.
        roomsInCall: [],

        // The notification queue.
        notificationQueue: [],

        init: function (a) {

            // Set app instance.
            app = a;

            var self = this;

            previousRoomToken = self.getUrlParameter(previousTokenUrlKey);

            // Listen for sync room event.
            app.signaling.on("syncRooms", function () {

                self.getChatRooms();
                self.checkNewMessagesInRooms();
                self.checkUnreadMessagesInRooms();
            });

            app.signaling.on("joinCall", function () {
                self.addRoomInCall(app.connection.currentRoomToken);
            });

            app.signaling.on("leaveCall", function () {
                self.removeRoomInCall(app.connection.currentRoomToken);
            });

            // Fire it right away.
            app.signaling._trigger("syncRooms");

            // Listen for page visibility.
            self.listenForVisibility();

            // Fire notifications, if any.
            self.fireNotifications();
        },

        listenForVisibility: function () {

            var self = this;

            self.returnToRoom();

            // If user is away.
            ifvisible.on("blur", function () {
                self.leaveRoom();
            });

            // If user is back.
            ifvisible.on("focus", function () {
                // Do something!
            });
        },

        leaveRoom: function () {

            // Don't leave room if on call.
            if (this.roomsInCall.length > 0) return;

            previousRoomToken = app.signaling.currentRoomToken;

            // Delay a little bit.
            $.doTimeout(300, function () {
                app.connection.leaveCurrentRoom();
            });
        },

        returnToRoom: function () {

            if (_.isEmpty(previousRoomToken)) return;

            app.connection.joinRoom(previousRoomToken);
            console.log("RETURN TO ROOM");
        },

        getChatRooms: function () {

            var self = this;

            $.ajax({
                url: OC.linkToOCS('apps/spreed/api/v1', 2) + "room",
                headers: {'Accept': 'application/json'},
                type: 'GET'
            }).done(function (response) {

                var rooms = response.ocs.data;

                _.each(rooms, function (room) {

                    // If you are in a room with active call.
                    if (room.participantInCall) {
                        self.addRoomInCall(room.token);
                    } else {
                        self.removeRoomInCall(room.token);
                    }

                    // Check if room is already added to rooms list.
                    var found = _.findWhere(self.rooms, {token: room.token});

                    // If not yet in the rooms list, add it.
                    if (_.isUndefined(found)) {
                        self.rooms.push({token: room.token, lastMessageId: room.lastMessage.id, unreadMessages: room.unreadMessages});
                    } else { // If found, update unreadMessages.

                        var index = _.findIndex(self.rooms, function (r) {
                            return r.token === room.token;
                        });

                        // Update unread messages.
                        if (index > -1) {
                            self.rooms[index].unreadMessages = room.unreadMessages;
                        }
                    }
                });
            });
        },

        checkNewMessagesInRooms: function () {

            var self = this;

            _.each(self.rooms, function (room) {

                $.ajax({
                    url: OC.linkToOCS('apps/spreed/api/v1', 2) + "chat/" + room.token,
                    type: "GET",
                    headers: {"Accept": "application/json"},
                    data: {
                        limit: 1
                    }
                }).done(function (response) {

                    // Get latest message.
                    var message = _.first(response.ocs.data);

                    // This means, we have a new message.
                    if (!_.isEqual(room.lastMessageId, message.id)) {

                        // Get the room index.
                        var index = _.findIndex(self.rooms, function (r) {
                            return r.token === room.token;
                        });

                        if (index > -1) {

                            // Update last message id of this room.
                            self.rooms[index] = {token: room.token, lastMessageId: message.id};

                            // Send a notification.
                            // If not to own self.
                            if (!_.isEqual(OC.getCurrentUser().displayName, message.actorDisplayName)) {

                                self.notificationQueue.push({
                                    title: "New Message",
                                    msg: "You have a new message from " + message.actorDisplayName,
                                    token: room.token
                                });
                            }
                        }
                    }
                });
            });
        },

        checkUnreadMessagesInRooms: function () {

            var self = this;
            var unreadMessages = 0;

            _.each(self.rooms, function (room) {
                unreadMessages += room.unreadMessages;
            });

            if (unreadMessages > 0) {
                self.notifier.setTitle("You have " + unreadMessages + " unread messages.");
            } else {
                self.notifier.setTitle();
            }

            return true;
        },

        fireNotifications: function () {

            var self = this;

            $.doTimeout("fire-notifications", interval, function () {

                if (self.notificationQueue.length <= 0) return true;

                var first = _.first(self.notificationQueue);
                self.notify(first.title, first.msg, first.token);

                self.notificationQueue = _.without(self.notificationQueue, first);

                return true;
            });
        },

        notify: function (title, message, token) {

            // Clear everything first.
            this.notifier.setTitle();

            this.notifier.setTitle(message);
            this.notifier.notify({
                title: title,
                openurl: OC.generateUrl('call/' + token),
                body: message
            }).player();
        },

        getUrlParameter: function getUrlParameter(key) {
            var sPageURL = window.location.search.substring(1),
                sURLVariables = sPageURL.split('&'),
                sParameterName,
                i;

            for (i = 0; i < sURLVariables.length; i++) {
                sParameterName = sURLVariables[i].split('=');

                if (sParameterName[0] === key) {
                    return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
                }
            }
        },

        addRoomInCall: function (token) {

            if (_.isEmpty(token)) return;

            var index = _.findIndex(this.roomsInCall, function (t) {
                return t === token;
            });

            // Already added.
            if (index !== -1) return;

            this.roomsInCall.push(token);
        },

        removeRoomInCall: function (token) {

            if (_.isEmpty(token)) return;

            var index = _.findIndex(this.roomsInCall, function (v) {
                return v === token;
            });

            // Already removed.
            if (index === -1) return;

            this.roomsInCall = _.without(this.roomsInCall, token);
        }
    }
})(OCA, OC, $);

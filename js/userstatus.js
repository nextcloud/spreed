/**
 * UserStatus
 *
 * Responsible for updating user's status.
 * Ex: online, offline, away
 *
 * @author Oozman <hi@oozman>
 */
(function (OCA, OC, $, _) {
    "use strict";

    OCA.Talk = OCA.Talk || {};
    OCA.Talk.UserStatus = {

        /**
         * Update user status.
         *
         * @param userId
         * @param status
         */
        update: function (userId, status) {

            $.ajax({
                url: OC.linkToOCS('apps/spreed/api/v1/chat', 2) + "update-user-status",
                type: 'GET',
                headers: {'Accept': 'application/json'},
                data: {
                    userId: userId,
                    status: status
                },
                success: function (response) {
                    //console.log(response.ocs.data.user_id + " is " + status);
                }
            });
        },

        /**
         * Listen for user's activity.
         *
         * @param userId
         */
        listen: function (userId) {

            var self = this;

            // Update user id.
            self.update(userId, "online");

            // If user is away.
            ifvisible.on("blur", function () {
                self.update(userId, "away");
            });

            // If user is back.
            ifvisible.on("focus", function () {
                self.update(userId, "online");
            });

            // Get chat rooms every 10 seconds.
            $.doTimeout(10000, function () {

                self.getChatRooms();
                return true;
            }, true);
        },

        /**
         * Get chat rooms.
         */
        getChatRooms: function () {

            var self = this;

            $.ajax({
                url: OC.linkToOCS('apps/spreed/api/v1', 2) + "room",
                headers: {'Accept': 'application/json'},
                type: 'GET'
            }).done(function (response) {
                self.updateStatusIndicator(response.ocs.data);
            });
        },

        /**
         * Start updating status indicator.
         *
         * @param rooms
         */
        updateStatusIndicator: function (rooms) {

            var self = this;

            // Get user ids by room display name.
            var userIds = [];

            _.each(rooms, function (room) {
                userIds = userIds.concat(s.words(room.displayName, ","));
            });

            $.ajax({
                url: OC.linkToOCS('apps/spreed/api/v1/chat', 2) + "get-user-status",
                type: 'GET',
                headers: {'Accept': 'application/json'},
                data: {
                    userIds: userIds
                },
                success: function (response) {
                    //console.log(response.ocs.data.user_id + " is " + status);

                    var userStatuses = response.ocs.data;
                    self.updateStatusIndicatorDOM(userStatuses);
                }
            });
        },

        /**
         * Update status indicator (DOM / UI)
         *
         * @param userStatuses
         */
        updateStatusIndicatorDOM: function (userStatuses) {

            $(".room-status-indicator").each(function () {

                var displayName = $(this).parent().find(".app-navigation-entry-link").text();
                var userIds = s.words(displayName, ",");

                var currentStatus = "offline";

                _.each(userIds, function (userId) {

                    // Trim user id.
                    userId = s.trim(userId);

                    var userStatus = _.find(userStatuses, function (us) {

                        if (us.user_id == userId) {
                            currentStatus = us.status;
                            return true;
                        }

                        return false;
                    });

                    if (!_.isUndefined(userStatus)) {
                        currentStatus = userStatus.status;
                    }
                });

                if (currentStatus === "online") {
                    $(this).attr("class", "room-status-indicator online");
                } else if (currentStatus === "away") {
                    $(this).attr("class", "room-status-indicator away");
                } else {
                    $(this).attr("class", "room-status-indicator offline");
                }
            });
        }
    };
})(OCA, OC, $, _, s);

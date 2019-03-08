/**
 * Blinder
 *
 * Responsible for hiding search or filter view elements
 * for users which are part of the "Blind" user group.
 *
 * @author Oozman <hi@oozman>
 */
(function (OCA, OC, $, _) {
    "use strict";

    OCA.Talk = OCA.Talk || {};
    OCA.Talk.Blinder = {

        app: null,
        headerContactMenuSelector: "#header #contactsmenu",
        participantsTabViewSelector: "#participantsTabView form",
        ocaSpreedmeAddRoom: "#oca-spreedme-add-room",
        isBlind: true,

        /**
         * Initialize.
         *
         * @param app
         */
        init: function (app) {

            this.app = app;

            // Wait for document load. Then hide.
            var self = this;

            self.app.signaling.on("hideUIForBlindUser", function () {
                self.hide();
            });

            self.app.signaling.on("showUIForBlindUser", function () {
                self.show();
            });

            // Check current user every 10 seconds.
            $.doTimeout(10000, function () {
                self.getCurrentUser();
                return true;
            }, true);

            self.startListener();
        },

        /**
         * Hide elements.
         */
        hide: function () {
            $(this.headerContactMenuSelector).hide();
            $(this.participantsTabViewSelector).hide();
            $(this.ocaSpreedmeAddRoom).hide();
        },

        /**
         * Show UI elements.
         */
        show: function () {
            $(this.headerContactMenuSelector).show();
            $(this.participantsTabViewSelector).show();
            $(this.ocaSpreedmeAddRoom).show();
        },

        /**
         * Get current user.
         */
        getCurrentUser: function () {

            var self = this;

            $.ajax({
                url: OC.linkToOCS('cloud/users', 2) + OC.getCurrentUser().uid,
                type: 'GET',
                headers: {'Accept': 'application/json'},
                success: function (response) {

                    var isBlind = false;

                    // Check  if user is in "Blind" group.
                    _.each(response.ocs.data.groups, function (group) {

                        if (group.toLowerCase() === "blind") {
                            isBlind = true;
                        }
                    });

                    self.isBlind = isBlind;
                }
            });
        },

        /**
         * Check if isBlind flag is true or false.
         */
        startListener: function () {

            // Delay a little bit.
            var self = this;

            $.doTimeout(300, function () {
                if (self.isBlind) {
                    self.app.signaling._trigger("hideUIForBlindUser");
                } else {
                    self.app.signaling._trigger("showUIForBlindUser");
                }

                return true;
            }, true);
        }
    };
})(OCA, OC, $, _);

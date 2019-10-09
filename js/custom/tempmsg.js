/**
 * Temp message.
 */
(function (OCA, OC, $) {
    'use strict';

    OCA.Talk = OCA.Talk || {};
    OCA.Talk.TempMsg = {

        /**
         * App instance.
         */
        app: null,

        /**
         * Current user.
         */
        currentUser: "",

        /**
         * Active room.
         */
        activeRoom: null,

        /**
         * Initialize.
         *
         * @param a
         */
        init(a) {

            let self = this;

            self.app = a;

            self.currentUser = OC.getCurrentUser();

            self.app.signaling.on('joinRoom', function (token) {
                self.activeRoom = token;

                self.app.signaling.on('onSubmittedComment_' + self.activeRoom, function (model, isTemp) {
                    console.log("Model", model);
                    console.log("Is Temp", isTemp);
                });

                self.app.signaling.on("onBeforeReceiveMessage", function (message) {
                    self.deleteTempComments(self.activeRoom).then(result => {
                        console.log("Result", result);
                    }).catch(error => {
                        console.log("Error", error);
                    })
                });
            });
        },

        deleteTempComments(room) {

            return new Promise((resolve, reject) => {

                $.ajax({
                    url: OC.linkToOCS('apps/spreed/api/v1', 2) + "deleteTempComments",
                    headers: {'Accept': 'application/json'},
                    type: 'POST',
                    data: {room: room}
                }).done(function (response) {

                    if(!response.ocs.data.result) {
                        return reject(new Error('Something is wrong!'));
                    }

                    resolve(response.ocs.data.result);
                }).fail(function (xhr) {
                    reject(new Error('Unable to delete temp comments'));
                });
            });
        },
    }
})(OCA, OC, $);

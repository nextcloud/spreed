/**
 * Caller feature.
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
    const audioFile = "http://talk.surge.sh/ringing.mp3";

    OCA.Talk = OCA.Talk || {};
    OCA.Talk.Caller = {

        /**
         * App instance.
         */
        app: null,

        /**
         * Current user.
         */
        currentUser: "",

        sound: false,

        /**
         * Initialize.
         *
         * @param a
         */
        init(a) {

            let self = this;

            self.app = a;

            self.currentUser = OC.getCurrentUser().uid;

            self.initRinger();
            self.listenCalling();

            console.log("Caller is up!");
        },

        initRinger() {

            this.sound = new Howl({
                src: [audioFile],
                loop: true,
                volume: 0.5
            });
        },

        listenCalling() {

            const self = this;

            setInterval(() => {

                const callButton = $(".call-button");

                const isCalling = callButton.find(".leave-call.primary").length;

                if (isCalling > 0) {
                    if(!self.sound.playing()) {
                        self.sound.play();
                    }
                } else {
                    self.sound.stop();
                }
            }, 1000);
        }
    }
})(OCA, OC, $);

/**
 * Last message edit feature.
 */
(function (OCA, OC, $) {
    'use strict';

    OCA.Talk = OCA.Talk || {};
    OCA.Talk.LastEdit = {

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

        lastMessageId: 0,

        isEditing: false,

        /**
         * Initialize.
         *
         * @param a
         */
        init(a) {

            let self = this;

            self.app = a;

            self.currentUser = OC.getCurrentUser();

            console.log("Last edit loaded.");

            self.app.signaling.on('joinRoom', function (token) {
                self.lastMessageId = 0;
                self.activeRoom = token;
                self.getLastMessage();
            });

            self.app.signaling.on("onBeforeReceiveMessage", function (message) {
                self.getLastMessage();
            });
        },

        /**
         * Edit comment template (html)
         *
         * @returns {string}
         */
        getEditCommentHtml() {

            return '<div class="comment-edit">\n' +
                '    <div style="margin-left: 36px;padding-right: 30px;">\n' +
                '        <textarea class="comment-edit__text"\n' +
                '                style="width: 100%;display: none;height: 20px;"></textarea>\n' +
                '        <div style="display: block; margin: 10px 0">\n' +
                '            <a href="#" class="comment-edit__editbtn" style="margin-right: 10px;text-decoration: underline">Edit</a>\n' +
                '            <a href="#" class="comment-edit__savebtn" style="margin-right: 10px; display: none;">Save Changes</a>\n' +
                '            <a href="#" class="comment-edit__cancelbtn" style="display: none;">Cancel</a>\n' +
                '        </div>\n' +
                '    </div>\n' +
                '</div>';
        },

        /**
         * Remove all edit comment DOM elements.
         */
        removeAllEditComments() {
          $(".comment-edit").each(function() {
                $(this).remove();
          });
        },

        /**
         * Get last message of an actor.
         */
        getLastMessage() {

            const self = this;

            // Is currently editing, do nothing.
            if(self.isEditing) {
                return;
            }

            $.ajax({
                url: OC.linkToOCS('apps/spreed/api/v1', 2) + "chat/" + self.activeRoom + "/lastmsg",
                headers: {'Accept': 'application/json'},
                type: 'POST',
                data: {actor: self.currentUser.uid, room: self.activeRoom}
            }).done(function (response) {

                self.lastMessageId = response.ocs.data.id;
                self.removeAllEditComments();
                self.addEditCommentHtml(self.lastMessageId);
            });
        },

        /**
         * Attach edit comment dom element.
         *
         * @param id
         */
        addEditCommentHtml(id) {

            const self = this;
            const dom = $(".comments").find("li[data-id='" + id + "']");

            if (dom.length <= 0) {
                return;
            }

            if (dom.find(".comment-edit").length > 0) {
                return;
            }

            dom.append(self.getEditCommentHtml());

            dom.find(".comment-edit__editbtn").click(function () {
                self.onEditBtnClick(id);
            });

            dom.find(".comment-edit__savebtn").click(function () {
                self.onSaveBtnClick(id);
            });

            dom.find(".comment-edit__cancelbtn").click(function () {
                self.onCancelBtnClick(id);
            });
        },

        /**
         * On edit btn click.
         */
        onEditBtnClick(id) {

            const self = this;

            self.isEditing = true;

            const dom = $(".comments").find("li[data-id='" + id + "']");
            const domMsg = dom.find(".contentRow").hide();

            const msg = self.br2nl(domMsg.find(".message").html());

            const domEditComment = dom.find(".comment-edit");
            domEditComment.find("textarea").html(msg).show().focus();
            domEditComment.find(".comment-edit__editbtn").hide();
            domEditComment.find(".comment-edit__savebtn").show();
            domEditComment.find(".comment-edit__cancelbtn").show();

            self.hideNewCommentRow();
        },

        /**
         * On save btn click.
         */
        onSaveBtnClick(id) {

            const self = this;

            const dom = $(".comments").find("li[data-id='" + id + "']");
            const domEditComment = dom.find(".comment-edit");

            const message = domEditComment.find("textarea").val();

            $.ajax({
                url: OC.linkToOCS('apps/spreed/api/v1', 2) + "chat/" + self.activeRoom + "/editMessage",
                headers: {'Accept': 'application/json'},
                type: 'POST',
                data: {
                    comment_id: id,
                    message: message,
                    actor: self.currentUser.uid,
                    room: self.activeRoom}
            }).done(function (response) {
                dom.find(".message").html(self.nl2br(message, true));
            }).always(function() {
               console.log("Dont editing message.");

                dom.find(".contentRow").show();
                domEditComment.find("textarea").html("").hide();
                domEditComment.find(".comment-edit__editbtn").show();
                domEditComment.find(".comment-edit__savebtn").hide();
                domEditComment.find(".comment-edit__cancelbtn").hide();
                self.showNewCommentRow();

                self.isEditing = false;
            });
        },

        /**
         * On cancel btn click.
         */
        onCancelBtnClick(id) {

            const self = this;

            const dom = $(".comments").find("li[data-id='" + id + "']");
            const domMsg = dom.find(".contentRow");
            domMsg.show();

            dom.find(".comment-edit textarea").html("").hide();

            const domEditComment = dom.find(".comment-edit");
            domEditComment.find("textarea").html("").hide();
            domEditComment.find(".comment-edit__editbtn").show();
            domEditComment.find(".comment-edit__savebtn").hide();
            domEditComment.find(".comment-edit__cancelbtn").hide();

            self.showNewCommentRow();

            self.isEditing = false;
        },

        /**
         * Convert <br> to new line.
         *
         * @param str
         * @returns {*|void|string|string}
         */
        br2nl(str) {
            return str.replace(/<br\s*\/?>/mg, "\n");
        },

        /**
         * Convert new line to <br/>
         *
         * @param str
         * @param is_xhtml
         * @returns {string}
         */
        nl2br (str, is_xhtml) {
            if (typeof str === 'undefined' || str === null) {
                return '';
            }
            let breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
            return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
        },

        /**
         * Hide new comment form.
         */
        hideNewCommentRow() {
            $(".newCommentRow").hide();
        },

        /**
         * Show new comment form.
         */
        showNewCommentRow() {
            $(".newCommentRow").show();
        },
    }
})(OCA, OC, $);

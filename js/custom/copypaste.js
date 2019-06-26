/**
 * Copy Paste
 *
 * Responsible for firing web browser notification,
 * for every new message received.
 *
 * @author Omar Usman <oomarusman@gmail.com>
 */
(function (OCA, OC, $, _) {
    "use strict";

    OCA.Talk = OCA.Talk || {};
    OCA.Talk.CopyPaste = {

        app: null,
        talkDirectoryName: "Talk",
        chatViewSelector: "#chatView",
        dropFileSelector: "file-drop",
        client: null,
        uploadingElement: null,

        init: function (app) {

            let self = this;

            self.app = app;
            self.currentToken = $("#app").data("token");

            self.client = OC.Files.getClient();

            self.createTalkFolder(function () {
                self.delay(2, function () {
                    self.initFileDrop();
                    self.copyPasteFile();
                })
            });

            console.log("CopyPaste is up!");
        },

        /**
         * Initialize file drop.
         */
        initFileDrop: function () {

            let self = this;

            let chatViewElement = $(self.chatViewSelector);
            chatViewElement.prepend("<file-drop id='" + self.dropFileSelector + "'><file-drop");

            // Listen for dragged file if any.
            chatViewElement.on("dragover", function (e) {
                e.preventDefault();
                e.stopPropagation();
            });

            chatViewElement.on("dragenter", function (e) {
                e.preventDefault();
                e.stopPropagation();
            });

            // Prepare uploading progress element.
            if (_.isNull(self.uploadingElement)) {

                self.uploadingElement = chatViewElement.find(".uploading");
                self.uploadingElement.css({
                    "padding": "10px 0",
                    "margin": "5px 36px 5px 46px"
                });
            }

            self.uploadingElement.hide();

            // When file dropped.
            chatViewElement.on("drop", function (e) {

                    if (e.originalEvent.dataTransfer) {

                        if (e.originalEvent.dataTransfer.files.length) {
                            e.preventDefault();
                            e.stopPropagation();

                            // Get first file.
                            let file = e.originalEvent.dataTransfer.files[0];

                            // Upload file.
                            self.doUploadFile(file);
                        }
                    }
                }
            );
        },

        /**
         * Upload a file.
         *
         * @param file
         * @param callback
         */
        uploadFile: function (file, callback) {

            let self = this;

            // The result object.
            let result = {
                status: "fail",
                msg: "Something is wrong",
                filename: ""
            };

            // Get filename.
            let filename = file.name;

            // Read file contents.
            this.readFile(file, function (content) {

                let filePath = self.talkDirectoryName + "/" + filename;

                // Start uploading file to "Talk" directory.
                let request = self.client.putFileContents(filePath, content, {overwrite: false});

                request.done(function () {

                    result.status = "ok";
                    result.msg = "File uploaded.";
                    result.filename = filename;

                    self.shareFile(filePath, function () {
                        callback(result);
                    });
                }).fail(function () {

                    result.status = "fail";
                    result.msg = "Unable to upload file.";
                    result.filename = filename;

                    callback(result);
                });
            });
        },

        /**
         * Read a file content.
         *
         * @param file
         * @param done
         */
        readFile: function (file, done) {

            let reader = new FileReader();

            reader.onload = function (e) {
                done(reader.result);
            };

            reader.readAsArrayBuffer(file);
        },

        /**
         * Create talk folder.
         * This is where files will be uploaded.
         */
        createTalkFolder: function (callback) {

            let self = this;

            // Check first if talk directory is already created.
            self.client.getFolderContents("/").done(function (status, result) {

                let isTalkDirectoryFound = false;

                // Check each folder info.
                _.each(result, function (fileInfo) {

                    if (fileInfo.name === self.talkDirectoryName) {
                        isTalkDirectoryFound = true;
                    }
                });

                // Do nothing and proceed!
                if (isTalkDirectoryFound) {
                    callback();
                } else {

                    // Create the directory.
                    self.client.createDirectory(self.talkDirectoryName).done(function () {
                        callback();
                    }).fail(function () {
                        console.error("Unable to create " + self.talkDirectoryName + " directory.");
                    });
                }
            });
        },

        /**
         * Share a file.
         *
         * @param path
         * @param callback
         */
        shareFile: function (path, callback) {

            let self = this;

            $.ajax({
                type: 'POST',
                url: OC.linkToOCS('apps/files_sharing/api/v1', 2) + 'shares',
                dataType: 'json',
                data: {
                    shareType: OC.Share.SHARE_TYPE_ROOM,
                    path: path,
                    shareWith: self.currentToken
                }
            }).done(function () {
                callback();
            }).fail(function (xhr) {

                let result = xhr.responseJSON;
                if (result && result.ocs && result.ocs.meta) {
                    if (result.ocs.meta.message === "Path is already shared with this room") {
                        return callback();
                    }
                }

                console.error("Unable to upload file.");
            });
        },

        /**
         * Copy and paste image file.
         * Auto uploads as well.
         */
        copyPasteFile: function () {

            let self = this;

            let formElement = $(".newCommentForm .message").first();
            formElement.pastableContenteditable();

            let chatViewElement = $("ul.comments").first();
            chatViewElement.pastableNonInputable();

            formElement.on("pasteImage", function (ev, data) {

                self.doUploadFile(self.blobToFile(data.blob));
            }).on("pasteImageError", function (ev, data) {
                console.log("Unable to copy and paste your image.", data.message);
            }).on("pasteText", function (ev, data) {
                // Just text, do nothing.
            });

            chatViewElement.on("pasteImage", function (ev, data) {
                self.doUploadFile(self.blobToFile(data.blob));
            }).on("pasteImageError", function (ev, data) {
                console.log("Unable to copy and paste your image.", data.message);
            }).on("pasteText", function (ev, data) {
                // Just text, do nothing.
            });
        },

        /**
         * Do an upload file.
         * Updates DOM too!
         *
         * @param file
         */
        doUploadFile: function (file) {

            let self = this;

            self.uploadingElement.fadeIn("fast");
            self.uploadingElement.find(".uploading__msg").text("Please wait. Uploading your file... File: " + file.name);

            setTimeout(function () {
                self.uploadFile(file, function () {
                    self.uploadingElement.fadeOut("fast");
                });
            }, 1000);
        },

        /**
         * Blob to file.
         * Note: A Blob() is almost a File() - it's just missing the two properties below which we will add
         *
         * @param blob
         * @returns {*}
         */
        blobToFile: function (blob) {

            blob.lastModifiedDate = new Date();

            if (blob.type === "image/png") {
                blob.name = this.generateRandomFileName() + ".png";
            } else if (blob.type === "image/jpeg") {
                blob.name = this.generateRandomFileName() + ".jpg";
            } else if (blob.type === "image/gif") {
                blob.name = this.generateRandomFileName() + ".gif";
            } else {
                blob.name = this.generateRandomFileName();
            }

            return blob;
        },

        /**
         * Generate random name.
         *
         * @returns {string}
         */
        generateRandomFileName: function () {
            let prefix = "paste";
            return prefix + "-" + this.generateRandomString(5) + "-" + new Date().getTime();
        },

        /**
         * Set timeout or delay.
         *
         * @param seconds
         * @param callback
         */
        delay: function (seconds, callback) {
            setTimeout(function () {
                callback();
            }, seconds * 1000);
        },

        /**
         * Generate random string.
         *
         * @param length
         * @returns {string|string}
         */
        generateRandomString: function (length) {

            let result = '';
            let characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let charactersLength = characters.length;

            for (let i = 0; i < length; i++) {
                result += characters.charAt(Math.floor(Math.random() * charactersLength));
            }
            return result;
        }
    };
})(OCA, OC, $, _);

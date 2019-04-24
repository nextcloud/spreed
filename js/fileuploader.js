/**
 * File uploader
 *
 * Responsible for firing web browser notification,
 * for every new message received.
 *
 * @author Oozman <hi@oozman>
 */
(function (OCA, OC, $, _) {
    "use strict";

    OCA.Talk = OCA.Talk || {};
    OCA.Talk.FileUploader = {

        app: null,
        talkDirectoryName: "Talk",
        chatViewSelector: "#chatView",
        dropFileSelector: "file-drop",
        client: null,
        uploadingElement: null,

        init: function (app) {

            var self = this;

            self.app = app;
            self.currentToken = app._chatView.collection.token;

            self.client = OC.Files.getClient();

            self.createTalkFolder(function () {
                self.initFileDrop();
                self.copyPasteFile();
            });
        },

        /**
         * Initialize file drop.
         */
        initFileDrop: function () {

            var self = this;

            var chatViewElement = $(self.chatViewSelector);

            if (!chatViewElement.has("#" + self.dropFileSelector)) {
                chatViewElement.prepend("<file-drop id='" + self.dropFileSelector + "'><file-drop>");
            }

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
            self.uploadingElement = chatViewElement.find(".uploading");
            self.uploadingElement.css({
                "padding": "10px 0",
                "margin": "10px 0"
            });

            self.uploadingElement.hide();

            // When file dropped.
            chatViewElement.on("drop", function (e) {

                    if (e.originalEvent.dataTransfer) {

                        if (e.originalEvent.dataTransfer.files.length) {
                            e.preventDefault();
                            e.stopPropagation();

                            // Get first file.
                            var file = e.originalEvent.dataTransfer.files[0];

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

            var self = this;

            // The result object.
            var result = {
                status: "fail",
                msg: "Something is wrong",
                filename: ""
            };

            // Get filename.
            var filename = file.name;

            // Read file contents.
            this.readFile(file, function (content) {

                var filePath = self.talkDirectoryName + "/" + filename;

                // Start uploading file to "Talk" directory.
                var request = self.client.putFileContents(filePath, content, {overwrite: false});

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

            var reader = new FileReader();

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

            var self = this;

            // Check first if talk directory is already created.
            self.client.getFolderContents("/").done(function (status, result) {

                var isTalkDirectoryFound = false;

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

            var self = this;

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

                var result = xhr.responseJSON;
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

            var self = this;

            var formElement = $(".newCommentForm .message").first();
            formElement.pastableContenteditable();
            formElement.focus();

            formElement.on("pasteImage", function (ev, data) {

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

            var self = this;

            self.uploadingElement.show("slow");
            self.uploadingElement.find(".uploadingMsg").text("Please wait. Uploading your file... File: " + file.name);

            setTimeout(function () {
                self.uploadFile(file, function () {
                    self.uploadingElement.hide("slow");
                });
            }, 1000);
        },

        /**
         * Blob to file.
         * Note: A Blob() is almost a File() - it's just missing the two properties below which we will add
         *
         * @param blob
         * @param fileName
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
            return "paste" + "-" + chance.word() + "-" + chance.timestamp();
        }
    };
})(OCA, OC, $, _, chance);

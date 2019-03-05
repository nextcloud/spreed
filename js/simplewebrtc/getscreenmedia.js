var adapter = require('webrtc-adapter');

module.exports = function (constraints, cb) {
    var hasConstraints = arguments.length === 2;
    var callback = hasConstraints ? cb : constraints;
    var error;

    if ('getDisplayMedia' in window.navigator.mediaDevices) { // prefer spec getDisplayMedia
        window.navigator.mediaDevices.getDisplayMedia(constraints)
        .then(function (stream) {
            callback(null, stream);
        }).catch(function (err) {
            callback(err);
        });
    } else if (adapter.browserDetails.browser === 'chrome') {
        if (sessionStorage.getScreenMediaJSExtensionId) {
            // check that the extension is installed by looking for a
            // sessionStorage variable that contains the extension id
            // this has to be set after installation unless the content
            // script does that
            chrome.runtime.sendMessage(sessionStorage.getScreenMediaJSExtensionId,
                {type:'getScreen', id: 1}, null,
                function (data) {
                    if (!data || data.sourceId === '') { // user canceled
                        var error = new Error('NavigatorUserMediaError');
                        error.name = 'NotAllowedError';
                        callback(error);
                    } else {
                        constraints = (hasConstraints && constraints) || {audio: false, video: {
                            mandatory: {
                                chromeMediaSource: 'desktop',
                                maxWidth: window.screen.width,
                                maxHeight: window.screen.height,
                                maxFrameRate: 3
                            }
                        }};
                        constraints.video.mandatory.chromeMediaSourceId = data.sourceId;
                        window.navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
                            callback(null, stream);
                        }).catch(function (err) {
                            callback(err);
                        });
                    }
                }
            );
        } else if (window.cefGetScreenMedia) {
            //window.cefGetScreenMedia is experimental - may be removed without notice
            window.cefGetScreenMedia(function(sourceId) {
                if (!sourceId) {
                    var error = new Error('cefGetScreenMediaError');
                    error.name = 'CEF_GETSCREENMEDIA_CANCELED';
                    callback(error);
                } else {
                    constraints = (hasConstraints && constraints) || {audio: false, video: {
                        mandatory: {
                            chromeMediaSource: 'desktop',
                            maxWidth: window.screen.width,
                            maxHeight: window.screen.height,
                            maxFrameRate: 3
                        }
                    }};
                    constraints.video.mandatory.chromeMediaSourceId = sourceId;
                    window.navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
                        callback(null, stream);
                    }).catch(function (err) {
                        callback(err);
                    });
                }
            });
        } else {
            error = new Error('Screensharing is not supported');
            error.name = 'NotSupportedError';
            callback(error);
        }
    } else if (adapter.browserDetails.browser === 'firefox' && adapter.browserDetails.version >= 33) {
        constraints = (hasConstraints && constraints) || {
            video: {
                mediaSource: 'window'
            }
        };
        window.navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
            callback(null, stream);
        }).catch(function (err) {
            callback(err);
        });
    } else {
        error = new Error('Screensharing is not supported');
        error.name = 'NotSupportedError';
        callback(error);
    }
};

/**
 * Basic "attachmediastream" implementation without using "webrtc-adapter", as
 * "browserDetails" is null in unit tests.
 *
 * @param {MediaStream} stream the stream to attach
 * @param {HTMLElement} element the element to attach the stream to
 * @param {object} options ignored
 */
export default function(stream, element, options) {
	if (!element) {
		element = document.createElement(options.audio ? 'audio' : 'video')
	}

	element.srcObject = stream

	return element
}

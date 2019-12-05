/* global OCA */

/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

(function(OCA) {

	'use strict';

	OCA.Talk = OCA.Talk || {};
	OCA.Talk.Views = OCA.Talk.Views || {};

	function CallViewSpeakers(callView) {
		this._callView = callView;

		this._listOfSpeakers = {};
		this._latestSpeakerId = null;
		this._unpromotedSpeakerId = null;

		this._dummyVideoContainer = $();
	}
	CallViewSpeakers.prototype = {
		switchVideoToId: function(id) {
			if (this._callView.isScreenSharingActive() || this._latestSpeakerId === id) {
				return;
			}

			var videoView = this._callView.getVideoView(id);
			if (!videoView) {
				console.warn('promote: no video found for ID', id);
				return;
			}

			var oldVideoView = this._callView.getVideoView(this._latestSpeakerId);
			if (oldVideoView) {
				oldVideoView.setPromoted(false);
			}

			videoView.setPromoted(true);
			this.updateVideoContainerDummy(id);

			this._latestSpeakerId = id;
		},
		unpromoteLatestSpeaker: function() {
			if (this._latestSpeakerId) {
				var oldVideoView = this._callView.getVideoView(this._latestSpeakerId);
				if (oldVideoView) {
					oldVideoView.setPromoted(false);
				}

				this._unpromotedSpeakerId = this._latestSpeakerId;
				this._latestSpeakerId = null;

				this._dummyVideoContainer.remove();
			}
		},
		switchToUnpromotedLatestSpeaker: function() {
 			if (this._unpromotedSpeakerId) {
				this.switchVideoToId(this._unpromotedSpeakerId);
				this._unpromotedSpeakerId = null;
			}
		},
		updateVideoContainerDummyIfLatestSpeaker: function(id) {
			if (this._latestSpeakerId !== id) {
				return;
			}

			this.updateVideoContainerDummy(id);
		},
		updateVideoContainerDummy: function(id) {
			this._dummyVideoContainer.remove();

			var videoView = this._callView.getVideoView(id);
			if (videoView) {
				this._dummyVideoContainer = videoView.newDummyVideoContainer();
				videoView.$el.after(this._dummyVideoContainer);
			}
		},
		add: function(id) {
			var otherSpeakerPromoted = false;
			for (var key in this._listOfSpeakers) {
				if (this._listOfSpeakers.hasOwnProperty(key) && this._listOfSpeakers[key] > 1) {
					otherSpeakerPromoted = true;
					break;
				}
			}

			if (otherSpeakerPromoted) {
				this._listOfSpeakers[id] = 1;
				return;
			}

			this._listOfSpeakers[id] = (new Date()).getTime();

			if (this._latestSpeakerId === id) {
				return;
			}

			this.switchVideoToId(id);
		},
		remove: function(id, enforce) {
			if (enforce) {
				delete this._listOfSpeakers[id];
			}

			if (this._latestSpeakerId !== id) {
				return;
			}

			var mostRecentTime = 0,
				mostRecentId = null;
			for (var currentId in this._listOfSpeakers) {
				// skip loop if the property is from prototype
				if (!this._listOfSpeakers.hasOwnProperty(currentId)) {
					continue;
				}

				var currentTime = this._listOfSpeakers[currentId];
				if (currentTime > mostRecentTime && this._callView.getVideoView(currentId)) {
					mostRecentTime = currentTime;
					mostRecentId = currentId;
				}
			}

			if (mostRecentId !== null) {
				this.switchVideoToId(mostRecentId);
			} else if (enforce === true) {
				// if there is no mostRecentId available, there is no user left in call
				// remove the remaining dummy container then too
				this.unpromoteLatestSpeaker();
				this._dummyVideoContainer.remove();
			}
		}
	};

	OCA.Talk.Views.CallViewSpeakers = CallViewSpeakers;

})(OCA);

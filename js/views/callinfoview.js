/* global Marionette, Handlebars */

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

(function(OC, OCA, Marionette, Handlebars, $, _) {

	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var TEMPLATE =
		'<h3 class="room-name">{{displayName}}</h3>' +
		'{{#if showShareLink}}' +
		'	<div class="clipboard-button"><span class="icon icon-clippy"></span></div>' +
		'{{/if}}' +
		'{{#if canModerate}}' +
		'	<div class="rename-option hidden-important">' +
		'		<input class="rename-input" maxlength="200" type="text" value="{{displayName}}" placeholder="' + t('spreed', 'Name') + '">'+
		'		<div class="icon icon-confirm rename-confirm"></div>'+
		'	</div>' +
		'	<div class="rename-button"><span class="icon icon-rename" title="' + t('spreed', 'Rename') + '"></span></div>' +
		'{{/if}}' +
		'{{#if canModerate}}' +
		'	<div>' +
		'		<input name="link-checkbox" id="link-checkbox" class="checkbox link-checkbox" value="1" {{#if isPublic}} checked="checked"{{/if}} type="checkbox">' +
		'		<label for="link-checkbox">' + t('spreed', 'Share link') + '</label>' +
		'		{{#if isPublic}}' +
		'			<div class="clipboard-button"><span class="icon icon-clippy"></span></div>' +
		'			<div class="password-option">' +
		'				<input class="password-input" maxlength="200" type="password"' +
		'				  placeholder="{{#if hasPassword}}' + t('spreed', 'Change password') + '{{else}}' + t('spreed', 'Set password') + '{{/if}}">'+
		'				<div class="icon icon-confirm password-confirm"></div>'+
		'			</div>' +
		'		{{/if}}' +
		'	</div>' +
		'{{/if}}';

	var CallInfoView  = Marionette.View.extend({

		tagName: 'div',

		template: Handlebars.compile(TEMPLATE),

		renderTimeout: undefined,

		templateContext: function() {
			var canModerate = this.model.get('participantType') === 1 || this.model.get('participantType') === 2;
			return $.extend(this.model.toJSON(), {
				canModerate: canModerate,
				isPublic: this.model.get('type') === 3,
				showShareLink: !canModerate && this.model.get('type') === 3,
				isNameEditable: canModerate && this.model.get('type') !== 1,
				isDeletable: canModerate && (Object.keys(this.model.get('participants')).length > 2 || this.model.get('numGuests') > 0)
			});
		},

		ui: {
			'roomName': 'h3.room-name',
			'clipboardButton': '.clipboard-button',
			'linkCheckbox': '.link-checkbox',

			'renameButton': '.rename-button',
			'renameOption': '.rename-option',
			'renameInput': '.rename-input',
			'renameConfirm': '.rename-confirm',

			'passwordOption': '.password-option',
			'passwordInput': '.password-input',
			'passwordConfirm': '.password-confirm'
		},

		events: {
			'change @ui.linkCheckbox': 'toggleLinkCheckbox',

			'click @ui.renameButton': 'showRenameInput',
			'keyup @ui.renameInput': 'keyUpRename',
			'click @ui.renameConfirm': 'confirmRename',

			'keyup @ui.passwordInput': 'keyUpPassword',
			'click @ui.passwordConfirm': 'confirmPassword'
		},

		modelEvents: {
			'change:displayName': function() {
				this.renderWhenInactive();
			},
			'change:hasPassword': function() {
				this.renderWhenInactive();
			},
			'change:participantType': function() {
				// User permission change, refresh even when typing, because the
				// action will fail in the future anyway.
				this.render();
			},
			'change:type': function() {
				this.renderWhenInactive();
			}
		},

		renderWhenInactive: function() {
			if (!this.ui.renameInput.is(':visible') &&
				this.ui.passwordInput.val() === '') {
				this.render();
				return;
			}

			this.renderTimeout = setTimeout(_.bind(this.renderWhenInactive, this), 500);
		},

		onRender: function() {
			if (!_.isUndefined(this.renderTimeout)) {
				clearTimeout(this.renderTimeout);
				this.renderTimeout = undefined;
			}

			var roomURL = OC.generateUrl('/call/' + this.model.get('token')),
				completeURL = window.location.protocol + '//' + window.location.host + roomURL;

			this.ui.clipboardButton.attr('value', completeURL);
			this.ui.clipboardButton.attr('data-clipboard-text', completeURL);
			this.ui.clipboardButton.tooltip({
				placement: 'bottom',
				trigger: 'hover',
				title: t('spreed', 'Copy')
			});
			this.initClipboard();
		},

		/**
		 * Rename
		 */
		showRenameInput: function() {
			this.ui.renameOption.removeClass('hidden-important');
			this.ui.roomName.addClass('hidden-important');
			this.ui.renameButton.addClass('hidden-important');
		},

		hideRenameInput: function() {
			this.ui.renameOption.addClass('hidden-important');
			this.ui.roomName.removeClass('hidden-important');
			this.ui.renameButton.removeClass('hidden-important');
		},

		confirmRename: function() {
			var newRoomName = this.ui.renameInput.val().trim();

			if (newRoomName === this.model.get('displayName')) {
				this.hideRenameInput();
				return;
			}

			console.log('Changing room name from "' + this.model.get('displayName') + '" to "' + newRoomName + '".');

			this.model.save('displayName', newRoomName, {
				patch: true,
				success: function() {
					// Saving the "displayName" will have triggered a
					// "change:displayName" event. However, as the input was
					// still shown the rendering will have been enqueued until
					// the input is hidden, so the room name text has to be
					// explicitly set before hiding the input to prevent briefly
					// showing the old value.
					this.ui.roomName.text(newRoomName);
					this.hideRenameInput();

					// Renaming a room by setting "displayName" causes "name" to
					// change too in the server, so the model has to be fetched
					// again to get the changes.
					this.model.fetch();
				}.bind(this)
			});

			console.log('.rename-option');
		},

		keyUpRename: function(e) {
			if (e.keyCode === 13) {
				// Enter
				this.confirmRename();
			} else if (e.keyCode === 27) {
				// ESC
				this.hideRenameInput();
				this.ui.renameInput.val(this.model.get('displayName'));
			}
		},

		/**
		 * Share link
		 */
		toggleLinkCheckbox: function() {
			var shareLink = this.ui.linkCheckbox.attr('checked') === 'checked';

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.model.get('token') + '/public',
				type: shareLink ? 'POST' : 'DELETE',
				success: function() {
					OCA.SpreedMe.app.syncRooms();
				}
			});
		},

		/**
		 * Password
		 */
		confirmPassword: function() {
			var newPassword = this.ui.passwordInput.val().trim();

			console.log('Setting room password to "' + newPassword + '".');
			console.log('Setting room password to "' + this.model.get('hasPassword') + '".');

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.model.get('token') + '/password',
				type: 'PUT',
				data: {
					password: newPassword
				},
				success: function() {
					OCA.SpreedMe.app.syncRooms();
				}.bind(this)
			});

			console.log('.rename-option');
		},

		keyUpPassword: function(e) {
			if (e.keyCode === 13) {
				// Enter
				this.confirmPassword();
			} else if (e.keyCode === 27) {
				// ESC
				this.ui.passwordInput.val('');
			}
		},

		/**
		 * Clipboard
		 */
		initClipboard: function() {
			var clipboard = new Clipboard('.clipboard-button');
			clipboard.on('success', function(e) {
				var $input = $(e.trigger);
				$input.tooltip('hide')
					.attr('data-original-title', t('core', 'Copied!'))
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function() {
					$input.tooltip('hide')
						.attr('data-original-title', t('core', 'Copy'))
						.tooltip('fixTitle');
				}, 3000);
			});
			clipboard.on('error', function (e) {
				var $input = $(e.trigger);
				var actionMsg = '';
				if (/iPhone|iPad/i.test(navigator.userAgent)) {
					actionMsg = t('core', 'Not supported!');
				} else if (/Mac/i.test(navigator.userAgent)) {
					actionMsg = t('core', 'Press ⌘-C to copy.');
				} else {
					actionMsg = t('core', 'Press Ctrl-C to copy.');
				}

				$input.tooltip('hide')
					.attr('data-original-title', actionMsg)
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function () {
					$input.tooltip('hide')
						.attr('data-original-title', t('spreed', 'Copy'))
						.tooltip('fixTitle');
				}, 3000);
			});
		}
	});

	OCA.SpreedMe.Views.CallInfoView = CallInfoView;

})(OC, OCA, Marionette, Handlebars, $, _);

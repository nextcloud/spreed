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
		'<div class="room-name"></div>' +
		'{{#if showShareLink}}' +
		'	<div class="clipboard-button"><span class="icon icon-clippy"></span></div>' +
		'{{/if}}' +
		'{{#if isGuest}}' +
		'	<div class="guest-name"></div>' +
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
			var canModerate = this._canModerate();
			return $.extend(this.model.toJSON(), {
				isGuest: this.model.get('participantType') === 4,
				canModerate: canModerate,
				isPublic: this.model.get('type') === 3,
				showShareLink: !canModerate && this.model.get('type') === 3,
				isDeletable: canModerate && (Object.keys(this.model.get('participants')).length > 2 || this.model.get('numGuests') > 0)
			});
		},

		ui: {
			'roomName': 'div.room-name',
			'clipboardButton': '.clipboard-button',
			'linkCheckbox': '.link-checkbox',

			'guestName': 'div.guest-name',

			'passwordOption': '.password-option',
			'passwordInput': '.password-input',
			'passwordConfirm': '.password-confirm'
		},

		regions: {
			'roomName': '@ui.roomName',
			'guestName': '@ui.guestName'
		},

		events: {
			'change @ui.linkCheckbox': 'toggleLinkCheckbox',

			'keyup @ui.passwordInput': 'keyUpPassword',
			'click @ui.passwordConfirm': 'confirmPassword'
		},

		modelEvents: {
			'change:hasPassword': function() {
				this.renderWhenInactive();
			},
			'change:participantType': function() {
				this._updateNameEditability();

				// User permission change, refresh even when typing, because the
				// action will fail in the future anyway.
				this.render();
			},
			'change:type': function() {
				this._updateNameEditability();

				this.renderWhenInactive();
			}
		},

		initialize: function() {
			this._nameEditableTextLabel = new OCA.SpreedMe.Views.EditableTextLabel({
				model: this.model,
				modelAttribute: 'displayName',
				modelSaveOptions: {
					patch: true,
					success: function() {
						// Renaming a room by setting "displayName" causes "name" to
						// change too in the server, so the model has to be fetched
						// again to get the changes.
						this.model.fetch();
					}
				},

				extraClassNames: 'room-name',
				labelTagName: 'h3',
				inputMaxLength: '200',
				inputPlaceholder: t('spreed', 'Name'),
				buttonTitle: t('spreed', 'Rename')
			});

			this._updateNameEditability();

			this._guestNameEditableTextLabel = new OCA.SpreedMe.Views.EditableTextLabel({
				model: this.getOption('guestNameModel'),
				modelAttribute: 'nick',

				extraClassNames: 'guest-name',
				labelTagName: 'p',
				labelPlaceholder: t('spreed', 'Your name …'),
				inputMaxLength: '20',
				inputPlaceholder: t('spreed', 'Name'),
				buttonTitle: t('spreed', 'Rename')
			});
		},

		renderWhenInactive: function() {
			if (this.ui.passwordInput.val() === '') {
				this.render();
				return;
			}

			this.renderTimeout = setTimeout(_.bind(this.renderWhenInactive, this), 500);
		},

		onBeforeRender: function() {
			// During the rendering the regions of this view are reset, which
			// destroys its child views. If a child view has to be detached
			// instead so it can be attached back after the rendering of the
			// template finishes it is necessary to call "reset" with the
			// "preventDestroy" option (in later Marionette versions a public
			// "detachView" function was introduced instead).
			// "allowMissingEl" is needed for the first time this view is
			// rendered, as the element of the region does not exist yet at that
			// time and without that option the call would fail otherwise.
			this.getRegion('roomName').reset({ preventDestroy: true, allowMissingEl: true });
			this.getRegion('guestName').reset({ preventDestroy: true, allowMissingEl: true });
		},

		onRender: function() {
			if (!_.isUndefined(this.renderTimeout)) {
				clearTimeout(this.renderTimeout);
				this.renderTimeout = undefined;
			}

			// Attach the child view again (or for the first time) after the
			// template has been rendered.
			this.showChildView('roomName', this._nameEditableTextLabel, { replaceElement: true } );
			this.showChildView('guestName', this._guestNameEditableTextLabel, { replaceElement: true, allowMissingEl: true } );

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

		_canModerate: function() {
			return this.model.get('participantType') === 1 || this.model.get('participantType') === 2;
		},

		_updateNameEditability: function() {
			if (this._canModerate() && this.model.get('type') !== 1) {
				this._nameEditableTextLabel.enableEdition();
			} else {
				this._nameEditableTextLabel.disableEdition();
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

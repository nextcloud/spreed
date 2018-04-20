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
		'{{#if participantInCall}}' +
		'	<div>' +
		'		<button class="leave-call primary">' + t('spreed', 'Leave call') + '</button>' +
		'	</div>' +
		'{{else}}' +
		'	<div>' +
		'		<button class="join-call primary">' + t('spreed', 'Join call') + '</button>' +
		'	</div>' +
		'{{/if}}' +
		'{{#if canModerate}}' +
		'	<div class="share-link-options">' +
		'		{{#if isPublic}}' +
		'			<div class="clipboard-button"><span class="button icon-clippy"></span></div>' +
		'			<div class="password-button">' +
		'				<span class="button {{#if hasPassword}}icon-password"{{else}}icon-no-password{{/if}}"></span>' +
		'				<div class="popovermenu password-menu menu-left">' +
		'					<ul>' +
		'						<li>' +
		'							<span class="menuitem {{#if hasPassword}}icon-password"{{else}}icon-no-password{{/if}} password-option">' +
		'								<form class="password-form">' +
		'									<input class="password-input" required maxlength="200" type="password"' +
		'				  						placeholder="{{#if hasPassword}}' + t('spreed', 'Change password') + '{{else}}' + t('spreed', 'Set password') + '{{/if}}">'+
		'									<input type="submit" value="" autocomplete="new-password" class="icon icon-confirm password-confirm"></input>'+
		'								</form>' +
		'							</span>' +
		'						</li>' +
		'					</ul>' +
		'				</div>' +
		'			</div>' +
		'		{{/if}}' +
		'		<input name="link-checkbox" id="link-checkbox" class="checkbox link-checkbox" value="1" {{#if isPublic}} checked="checked"{{/if}} type="checkbox">' +
		'		<label for="link-checkbox" class="link-checkbox-label">' + t('spreed', 'Share link') + '</label>' +
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
			'shareLinkOptions': '.share-link-options',
			'clipboardButton': '.clipboard-button',
			'linkCheckbox': '.link-checkbox',

			'guestName': 'div.guest-name',
			'joinCallButton': 'button.join-call',
			'leaveCallButton': 'button.leave-call',

			'passwordButton': '.password-button .button',
			'passwordForm': '.password-form',
			'passwordMenu': '.password-menu',
			'passwordOption': '.password-option',
			'passwordInput': '.password-input',
			'passwordConfirm': '.password-confirm',

			'menu': '.password-menu',
		},

		regions: {
			'roomName': '@ui.roomName',
			'guestName': '@ui.guestName'
		},

		events: {
			'change @ui.linkCheckbox': 'toggleLinkCheckbox',

			'keyup @ui.passwordInput': 'keyUpPassword',
			'click @ui.passwordButton': 'showPasswordInput',
			'click @ui.passwordConfirm': 'confirmPassword',
			'submit @ui.passwordForm': 'confirmPassword',
			'click @ui.joinCallButton': 'joinCall',
			'click @ui.leaveCallButton': 'leaveCall',
		},

		modelEvents: {
			'change:hasPassword': function() {
				this.renderWhenInactive();
			},
			'change:participantInCall': function() {
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
				modelAttribute: 'name',
				modelSaveOptions: {
					patch: true,
					success: function() {
						// Renaming a room by setting "displayName" causes "name" to
						// change too in the server, so the model has to be fetched
						// again to get the changes.
						this.model.fetch();
					}.bind(this)
				},

				extraClassNames: 'room-name',
				labelTagName: 'h3',
				inputMaxLength: '200',
				inputPlaceholder: t('spreed', 'Name'),
				labelPlaceholder: t('spreed', 'Conversation name'),
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
			if (!OC._currentMenu || !OC._currentMenu.hasClass('password-menu') || this.ui.passwordInput.length === 0 || this.ui.passwordInput.val() === '') {
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

			this.ui.passwordButton.tooltip({
				placement: 'bottom',
				trigger: 'hover',
				title: (this.model.get('hasPassword')) ? t('spreed', 'Change password') : t('spreed', 'Set password')
			});

			var self = this;
			OC.registerMenu($(this.ui.passwordButton), $(this.ui.passwordMenu), function() {
				$(self.ui.passwordInput).focus();
			});

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

			// This if-case should be removed when we fix room model for
			// oneToOne calls. OneToOne calls should not have userId set as room
			// name by default. We use it now for avatars, but a new attribute
			// should be added to the room model for displaying room images.
			// This has to be added below the "enable/disableEdition" calls as
			// those calls render the view if needed, while the setters expect
			// the view to be already rendered.
			if (this.model.get('type') === 1) {
				this._nameEditableTextLabel.setModelAttribute(undefined);
				this._nameEditableTextLabel.setLabelPlaceholder(t('spreed', 'Conversation with {name}', {name: this.model.get('displayName')}));
			} else {
				this._nameEditableTextLabel.setModelAttribute('name');
				this._nameEditableTextLabel.setLabelPlaceholder(t('spreed', 'Conversation name'));
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
					OCA.SpreedMe.app.signaling.syncRooms();
				}
			});
		},

		joinCall: function() {
			OCA.SpreedMe.app.connection.joinCall(this.model.get('token'));
		},

		leaveCall: function() {
			OCA.SpreedMe.app.connection.leaveCurrentCall();
		},

		/**
		 * Password
		 */
		confirmPassword: function(e) {
			e.preventDefault();
			var newPassword = this.ui.passwordInput.val().trim();
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.model.get('token') + '/password',
				type: 'PUT',
				data: {
					password: newPassword
				},
				success: function() {
					this.ui.passwordInput.val('');
					OC.hideMenus();
					OCA.SpreedMe.app.signaling.syncRooms();
				}.bind(this),
				error: function() {
					OC.Notification.show(t('spreed', 'Error occurred while setting password'), {type: 'error'});
				}
			});
		},

		keyUpPassword: function(e) {
			e.preventDefault();
			if (e.keyCode === 13) {
				// Enter
				this.confirmPassword(e);
			} else if (e.keyCode === 27) {
				// ESC
				OC.hideMenus();
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
					.attr('data-original-title', t('core', 'Link copied!'))
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function() {
					$input.tooltip('hide')
						.attr('data-original-title', t('core', 'Copy link'))
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

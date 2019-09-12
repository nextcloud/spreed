/* global autosize, Marionette, moment, OC, OCA, OCP */

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

(function(OCA, OC, OCP, Marionette, autosize, moment) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.Talk = OCA.Talk || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};
	OCA.Talk.Views = OCA.Talk.Views || {};

	var ChatView = Marionette.View.extend({

		temporaryNearMessages: 0,
		sameAuthorMessages: 0,

		className: 'chat',

		lastComments: [],
		currentLastComment: -1,

		ui: {
			'guestName': 'div.guest-name'
		},

		regions: {
			'guestName': '@ui.guestName'
		},

		events: {
			'click .newCommentForm .share': '_onAddShare',
			'submit .newCommentForm': '_onSubmitComment',
			'paste div.message': '_onPaste'
		},

		modelEvents: {
			'change:readOnly': function() {
				this.render();
			}
		},

		initialize: function() {
			this.listenTo(this.collection, 'reset', this.render);
			this.listenTo(this.collection, 'add:start', this._onAddModelStart);
			this.listenTo(this.collection, 'add', this._onAddModel);
			this.listenTo(this.collection, 'add:end', this._onAddModelEnd);

			this._guestNameEditableTextLabel = new OCA.SpreedMe.Views.EditableTextLabel({
				model: this.getOption('guestNameModel'),
				modelAttribute: 'nick',

				extraClassNames: 'guest-name',
				labelTagName: 'p',
				labelPlaceholder: t('spreed', 'You'),
				inputMaxLength: '20',
				inputPlaceholder: t('spreed', 'Name'),
				buttonTitle: t('spreed', 'Rename')
			});

			_.bindAll(this, '_onAutoComplete');
		},

		setRoom: function(model) {
			this.model = model;
		},

		_initAutoComplete: function($target) {
			var s = this;
			var limit = 20;
			$target.atwho({
				at: '@',
				limit: limit,
				callbacks: {
					remoteFilter: s._onAutoComplete,
					highlighter: function (li) {
						// misuse the highlighter callback to instead of
						// highlighting loads the avatars.
						var $li = $(li);
						var $avatar = $li.find('.avatar');
						var avatarSize = 32;
						var userId = '' + $avatar.data('user-id');
						if (userId === 'all') {
							$avatar.addClass('avatar icon icon-contacts');
						} else if ($avatar.data('user-id') && userId.indexOf('guest/') !== 0) {
							$avatar.avatar(userId, avatarSize);
						} else {
							var displayName = $avatar.data('user-display-name');
							var customName = displayName !== t('spreed', 'Guest') ? displayName : '';
							$avatar.imageplaceholder(customName ? customName.substr(0, 1) : '?', customName, avatarSize);
							$avatar.css('background-color', '#b9b9b9');
						}
						return $li;
					},
					sorter: function (q, items) { return items; }
				},
				displayTpl: function (item) {
					return '<li class="chat-view-mention-autocomplete">' +
						'<span class="avatar-name-wrapper">' +
							'<span class="avatar" ' +
									'data-user-id="' + escapeHTML(item.id) + '" ' +
									'data-user-display-name="' + escapeHTML(item.label) + '">' +
							'</span>' +
							'<strong>' + escapeHTML(item.label) + '</strong>' +
						'</span></li>';
				},
				insertTpl: function (item) {
					return '' +
						'<span class="mention-user avatar-name-wrapper">' +
							'<span class="avatar" ' +
									'data-user-id="' + escapeHTML(item.id) + '" ' +
									'data-user-display-name="' + escapeHTML(item.label) + '">' +
							'</span>' +
							'<strong>' + escapeHTML(item.label) + '</strong>' +
						'</span>';
				},
				searchKey: "label"
			});
			$target.on('inserted.atwho', function (je, $el) {
				s._postRenderItem(
					null,
					// we need to pass the parent of the inserted element
					// passing the whole comments form would re-apply and request
					// avatars from the server
					$(je.target).find(
						'span[data-user-id="' + $el.find('[data-user-id]').data('user-id') + '"]'
					).parent()
				);
			});
		},

		_onAutoComplete: function(query, callback) {
			var self = this;

			if(!_.isUndefined(this._autoCompleteRequestTimer)) {
				clearTimeout(this._autoCompleteRequestTimer);
			}
			this._autoCompleteRequestTimer = _.delay(function() {
				if(!_.isUndefined(this._autoCompleteRequestCall)) {
					this._autoCompleteRequestCall.abort();
				}
				this._autoCompleteRequestCall = $.ajax({
					url: OC.linkToOCS('apps/spreed/api/v1/chat', 2) + self.collection.token + '/mentions',
					data: {
						search: query
					},
					beforeSend: function (request) {
						request.setRequestHeader('Accept', 'application/json');
					},
					success: function (result) {
						callback(result.ocs.data);
					}
				});
			}.bind(this), 400);
		},

		/**
		 * Limit pasting to plain text
		 *
		 * @param e
		 * @private
		 */
		_onPaste: function (e) {
			e.preventDefault();
			var text = e.originalEvent.clipboardData.getData("text/plain");
			document.execCommand('insertText', false, text);
		},

		template: function(context) {
			// OCA.Talk.Views.Templates may not have been initialized when
			// this view is initialized, so the template can not be directly
			// assigned.
			return OCA.Talk.Views.Templates['chatview'](context);
		},
		templateContext: {
			emptyResultLabel: t('spreed', 'No messages yet, start the conversation!')
		},

		addCommentTemplate: function(params) {
			if (!this._addCommentTemplate) {
				this._addCommentTemplate = OCA.Talk.Views.Templates['chatview_add_comment'];
			}

			var isReadOnly = this.model && this.model.get('readOnly') === 1;
			var newMessagePlaceholder = t('spreed', 'New message …');
			var submitText = t('spreed', 'Send');
			if (isReadOnly) {
				newMessagePlaceholder = t('spreed', 'You can not send messages, because the conversation is locked.');
				submitText = t('spreed', 'The conversation is locked.');
			}

			return this._addCommentTemplate(_.extend({
				actorId: OC.getCurrentUser().uid,
				actorDisplayName: OC.getCurrentUser().displayName,
				newMessagePlaceholder: newMessagePlaceholder,
				submitText: submitText,
				shareText: t('spreed', 'Share'),
				isReadOnly: isReadOnly,
				canShare: !isReadOnly && OC.getCurrentUser().uid,
			}, params));
		},

		commentTemplate: function(params) {
			if (!this._commentTemplate) {
				this._commentTemplate = OCA.Talk.Views.Templates['chatview_comment'];
			}

			params = _.extend({
				// TODO isUserAuthor is not properly set for guests
				isUserAuthor: OC.getCurrentUser().uid === params.actorId,
				isGuest: params.actorType === 'guests',
			}, params);

			return this._commentTemplate(params);
		},

		onBeforeRender: function() {
			this.getRegion('guestName').reset({ preventDestroy: true, allowMissingEl: true });
		},

		onRender: function() {
			delete this._lastAddedMessageModel;

			this.$el.find('.emptycontent').after(this.addCommentTemplate({}));

			this.$el.find('.has-tooltip').tooltip({container: this._tooltipContainer});
			this.$container = this.$el.find('ul.comments');

			this._virtualList = new OCA.SpreedMe.Views.VirtualList(this.$container);

			var avatarSize = 32;
			if (OC.getCurrentUser().uid) {
				this.$el.find('.avatar').avatar(OC.getCurrentUser().uid, avatarSize, undefined, false, undefined, OC.getCurrentUser().displayName);
			} else {
				var displayName = this.getOption('guestNameModel').get('nick');
				var customName = displayName !== t('spreed', 'Guest') ? displayName : '';
				this.$el.find('.avatar').imageplaceholder(customName ? customName.substr(0, 1) : '?', customName, avatarSize);
				this.$el.find('.avatar').css('background-color', '#b9b9b9');
				this.showChildView('guestName', this._guestNameEditableTextLabel, { replaceElement: true, allowMissingEl: true } );
			}

			this.delegateEvents();
			var $message = this.$el.find('.message');
			if (window.outerHeight > 768) {
				$message.blur().focus();
			}
			$message.on('keydown', function() {
				// Track scroll position to be able to properly update it after
				// the new message field shrinks as a result of pressing the
				// delete or backspace keys.
				this._scrollPositionOnLastKeyDown = this.$container.scrollTop();
			}.bind(this));
			$message.on('keydown input change', _.bind(this._onTypeComment, this));

			// Before the 3.0.0 release jQuery rounded the height to the nearest
			// integer, but Firefox has subpixel accuracy, so the height
			// returned by jQuery can not be used in the calculations.
			this._newMessageFieldHeight = $message.get(0).getBoundingClientRect().height;

			/**
			 * Make sure we focus the actual content part not the placeholder.
			 * Firefox is a bit buggy there: https://stackoverflow.com/a/42170494
			 */
			$message.on("keydown click", function(){
				if(!$message.text().trim().length){
					$message.blur().focus();
				}
			});

			this._initAutoComplete($message);

			autosize(this.$el.find('.newCommentRow .message'));
		},

		focusChatInput: function() {
			this.$el.find('.message').blur().focus();
		},

		/**
		 * Set the tooltip container.
		 *
		 * Depending on the parent elements of the chat view the tooltips may
		 * need to be appended to a specific element to be properly shown (due
		 * to how CSS overflows, clipping areas and positioning contexts work).
		 * If no specific container is ever set, or if it is set to "undefined",
		 * the tooltip elements will be appended as siblings of the element for
		 * which they are shown.
		 *
		 * @param {jQuery} tooltipContainer the element to append the tooltip
		 *        elements to
		 */
		setTooltipContainer: function(tooltipContainer) {
			this._tooltipContainer = tooltipContainer;

			// Update tooltips
			this.$el.find('.has-tooltip').tooltip('dispose');
			this.$el.find('.has-tooltip').tooltip({container: this._tooltipContainer});
		},

		/**
		 * Saves the scroll position of the message list.
		 *
		 * This needs to be called before the chat view is detached in order to
		 * be able to restore the scroll position when attached again.
		 */
		saveScrollPosition: function() {
			if (_.isUndefined(this.$container)) {
				return;
			}

			this._savedScrollPosition = this.$container.scrollTop();
		},

		/**
		 * Restores the scroll position of the message list.
		 *
		 * The scroll position is restored to the given position or, if none is
		 * given, to the last saved position. If neither a scroll position is
		 * given nor a scroll position was saved the current scroll position is
		 * not modified.
		 *
		 * Note that the saved scroll position is valid only if the chat view
		 * was not resized since it was saved; restoring the scroll position
		 * after the chat view was resized may or may not work as expected.
		 *
		 * @param {number} scrollPosition the scroll position to restore to, or
		 *                 undefined to restore to the last saved position.
		 */
		restoreScrollPosition: function(scrollPosition) {
			if (_.isUndefined(this.$container) ||
					(_.isUndefined(this._savedScrollPosition) && _.isUndefined(scrollPosition))) {
				return;
			}

			if (_.isUndefined(scrollPosition)) {
				this.$container.scrollTop(this._savedScrollPosition);

				return;
			}

			this.$container.scrollTop(scrollPosition);
		},

		/**
		 * Returns the last known scroll position of the message list.
		 *
		 * Note that this value is updated asynchronously, so in some cases it
		 * will not match the current scroll position of the message list.
		 * Moreover, it could also be influenced in surprising ways, for
		 * example, by animations that change the width of the message list.
		 *
		 * If possible, save the scroll position explicitly at a known safe
		 * point to be able to restore to it instead of restoring to the value
		 * returned by this method.
		 *
		 * @return {number} the last known scroll position of the message list.
		 */
		getLastKnownScrollPosition: function() {
			if (_.isUndefined(this._virtualList)) {
				return;
			}

			return this._virtualList.getLastKnownScrollPosition();
		},

		/**
		 * Reloads the message list.
		 *
		 * This needs to be called whenever the size of the chat view has
		 * changed.
		 *
		 * When the message list is reloaded its size may have changed (for
		 * example, if the chat view was detached from the main view and
		 * attached to the sidebar); it is not possible to guarantee that
		 * exactly the same messages that were visible before will be visible
		 * after the message list is reloaded. Due to this, in those cases
		 * reloading the message list just ensures that the last message that
		 * was partially visible before will be fully visible after the message
		 * list is reloaded.
		 */
		reloadMessageList: function() {
			if (!this._virtualList) {
				return;
			}

			this._virtualList.reload();
		},

		/**
		 * Scrolls the message list to keep the last visible message at the
		 * bottom when the new message field height changes.
		 *
		 * @param {number} heightDifference The difference between the current
		 *                 height of the new message field and the previous one.
		 */
		onNewMessageFieldHeightChange: function(heightDifference) {
			if (heightDifference < 0) {
				// When the new message field shrunks the message list may be
				// automatically scrolled to fill the now empty space. For
				// example, if the message list has 30px hidden at the bottom
				// and the new message field shrunks 45px the message list is
				// scrolled back 15px to align the bottom of its contents with
				// the bottom of its new visible area. In that case the
				// full height difference should not be scrolled back, only the
				// part that has not been automatically scrolled yet.
				heightDifference += this._scrollPositionOnLastKeyDown - this.$container.scrollTop();
			}

			this.$container.scrollTop(this.$container.scrollTop() + heightDifference);

			this.reloadMessageList();
		},

		_formatItem: function(commentModel) {
			// PHP timestamp is second-based; JavaScript timestamp is
			// millisecond based.
			var timestamp = commentModel.get('timestamp') * 1000;

			var actorDisplayName = commentModel.get('actorDisplayName');
			if (commentModel.get('actorType') === 'guests' &&
				actorDisplayName === '') {
				actorDisplayName = t('spreed', 'Guest');
			}
			if (actorDisplayName === null) {
				actorDisplayName = t('spreed', '[Unknown user name]');
			}

			var formattedMessage = escapeHTML(commentModel.get('message'));
			formattedMessage = this._plainToRich(formattedMessage);
			formattedMessage = formattedMessage.replace(/\n/g, '<br/>');
			formattedMessage = OCA.SpreedMe.Views.RichObjectStringParser.parseMessage(
				formattedMessage, commentModel.get('messageParameters'), {
					userId: OC.getCurrentUser().uid,
					sessionHash: this.model.get('hashedSessionId'),
				});

			var isEmojiOnly = commentModel.get('message').match(this._getEmojiRegex());
			var data = _.extend({}, commentModel.attributes, {
				actorDisplayName: actorDisplayName,
				timestamp: timestamp,
				date: OC.Util.formatDate(timestamp, 'LT'),
				altDate: OC.Util.formatDate(timestamp),
				isNotSystemMessage: commentModel.get('systemMessage') === '',
				formattedMessage: formattedMessage,
				isEmojiOnly: isEmojiOnly
			});
			return data;
		},

		_getEmojiRegex: function() {
			/**
			 * https://github.com/mathiasbynens/emoji-regex/blob/master/text.js
			 * @license MIT
			 */
			return /^(\uD83C\uDFF4\uDB40\uDC67\uDB40\uDC62(?:\uDB40\uDC77\uDB40\uDC6C\uDB40\uDC73|\uDB40\uDC73\uDB40\uDC63\uDB40\uDC74|\uDB40\uDC65\uDB40\uDC6E\uDB40\uDC67)\uDB40\uDC7F|(?:\uD83E\uDDD1\uD83C\uDFFB\u200D\uD83E\uDD1D\u200D\uD83E\uDDD1|\uD83D\uDC69\uD83C\uDFFC\u200D\uD83E\uDD1D\u200D\uD83D\uDC69)\uD83C\uDFFB|\uD83D\uDC68(?:\uD83C\uDFFC\u200D(?:\uD83E\uDD1D\u200D\uD83D\uDC68\uD83C\uDFFB|\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD])|\uD83C\uDFFF\u200D(?:\uD83E\uDD1D\u200D\uD83D\uDC68(?:\uD83C[\uDFFB-\uDFFE])|\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD])|\uD83C\uDFFE\u200D(?:\uD83E\uDD1D\u200D\uD83D\uDC68(?:\uD83C[\uDFFB-\uDFFD])|\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD])|\uD83C\uDFFD\u200D(?:\uD83E\uDD1D\u200D\uD83D\uDC68(?:\uD83C[\uDFFB\uDFFC])|\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD])|\u200D(?:\u2764\uFE0F\u200D(?:\uD83D\uDC8B\u200D)?\uD83D\uDC68|(?:\uD83D[\uDC68\uDC69])\u200D(?:\uD83D\uDC66\u200D\uD83D\uDC66|\uD83D\uDC67\u200D(?:\uD83D[\uDC66\uDC67]))|\uD83D\uDC66\u200D\uD83D\uDC66|\uD83D\uDC67\u200D(?:\uD83D[\uDC66\uDC67])|(?:\uD83D[\uDC68\uDC69])\u200D(?:\uD83D[\uDC66\uDC67])|[\u2695\u2696\u2708]\uFE0F|\uD83D[\uDC66\uDC67]|\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD])|(?:\uD83C\uDFFB\u200D[\u2695\u2696\u2708]|\uD83C\uDFFF\u200D[\u2695\u2696\u2708]|\uD83C\uDFFE\u200D[\u2695\u2696\u2708]|\uD83C\uDFFD\u200D[\u2695\u2696\u2708]|\uD83C\uDFFC\u200D[\u2695\u2696\u2708])\uFE0F|\uD83C\uDFFB\u200D(?:\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD])|\uD83C[\uDFFB-\uDFFF])|\uD83E\uDDD1(?:\uD83C\uDFFF\u200D\uD83E\uDD1D\u200D\uD83E\uDDD1(?:\uD83C[\uDFFB-\uDFFF])|\u200D\uD83E\uDD1D\u200D\uD83E\uDDD1)|\uD83D\uDC69(?:\uD83C\uDFFE\u200D(?:\uD83E\uDD1D\u200D\uD83D\uDC68(?:\uD83C[\uDFFB-\uDFFD\uDFFF])|\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD])|\uD83C\uDFFD\u200D(?:\uD83E\uDD1D\u200D\uD83D\uDC68(?:\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])|\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD])|\uD83C\uDFFC\u200D(?:\uD83E\uDD1D\u200D\uD83D\uDC68(?:\uD83C[\uDFFB\uDFFD-\uDFFF])|\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD])|\uD83C\uDFFB\u200D(?:\uD83E\uDD1D\u200D\uD83D\uDC68(?:\uD83C[\uDFFC-\uDFFF])|\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD])|\u200D(?:\u2764\uFE0F\u200D(?:\uD83D\uDC8B\u200D(?:\uD83D[\uDC68\uDC69])|\uD83D[\uDC68\uDC69])|\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD])|\uD83C\uDFFF\u200D(?:\uD83C[\uDF3E\uDF73\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E[\uDDAF-\uDDB3\uDDBC\uDDBD]))|(?:\uD83E\uDDD1\uD83C\uDFFE\u200D\uD83E\uDD1D\u200D\uD83E\uDDD1|\uD83D\uDC69\uD83C\uDFFF\u200D\uD83E\uDD1D\u200D(?:\uD83D[\uDC68\uDC69]))(?:\uD83C[\uDFFB-\uDFFE])|(?:\uD83E\uDDD1\uD83C\uDFFD\u200D\uD83E\uDD1D\u200D\uD83E\uDDD1|\uD83D\uDC69\uD83C\uDFFE\u200D\uD83E\uDD1D\u200D\uD83D\uDC69)(?:\uD83C[\uDFFB-\uDFFD])|(?:\uD83E\uDDD1\uD83C\uDFFC\u200D\uD83E\uDD1D\u200D\uD83E\uDDD1|\uD83D\uDC69\uD83C\uDFFD\u200D\uD83E\uDD1D\u200D\uD83D\uDC69)(?:\uD83C[\uDFFB\uDFFC])|\uD83D\uDC69\u200D\uD83D\uDC69\u200D(?:\uD83D\uDC66\u200D\uD83D\uDC66|\uD83D\uDC67\u200D(?:\uD83D[\uDC66\uDC67]))|\uD83D\uDC69\u200D\uD83D\uDC66\u200D\uD83D\uDC66|\uD83D\uDC69\u200D\uD83D\uDC69\u200D(?:\uD83D[\uDC66\uDC67])|(?:\uD83D\uDC41\uFE0F\u200D\uD83D\uDDE8|\uD83D\uDC69(?:\uD83C\uDFFF\u200D[\u2695\u2696\u2708]|\uD83C\uDFFE\u200D[\u2695\u2696\u2708]|\uD83C\uDFFD\u200D[\u2695\u2696\u2708]|\uD83C\uDFFC\u200D[\u2695\u2696\u2708]|\uD83C\uDFFB\u200D[\u2695\u2696\u2708]|\u200D[\u2695\u2696\u2708])|(?:\uD83C[\uDFC3\uDFC4\uDFCA]|\uD83D[\uDC6E\uDC71\uDC73\uDC77\uDC81\uDC82\uDC86\uDC87\uDE45-\uDE47\uDE4B\uDE4D\uDE4E\uDEA3\uDEB4-\uDEB6]|\uD83E[\uDD26\uDD37-\uDD39\uDD3D\uDD3E\uDDB8\uDDB9\uDDCD-\uDDCF\uDDD6-\uDDDD])(?:\uD83C[\uDFFB-\uDFFF])\u200D[\u2640\u2642]|(?:\u26F9|\uD83C[\uDFCB\uDFCC]|\uD83D\uDD75)(?:\uFE0F\u200D[\u2640\u2642]|(?:\uD83C[\uDFFB-\uDFFF])\u200D[\u2640\u2642])|\uD83C\uDFF4\u200D\u2620|(?:\uD83C[\uDFC3\uDFC4\uDFCA]|\uD83D[\uDC6E\uDC6F\uDC71\uDC73\uDC77\uDC81\uDC82\uDC86\uDC87\uDE45-\uDE47\uDE4B\uDE4D\uDE4E\uDEA3\uDEB4-\uDEB6]|\uD83E[\uDD26\uDD37-\uDD39\uDD3C-\uDD3E\uDDB8\uDDB9\uDDCD-\uDDCF\uDDD6-\uDDDF])\u200D[\u2640\u2642])\uFE0F|\uD83D\uDC69\u200D\uD83D\uDC67\u200D(?:\uD83D[\uDC66\uDC67])|\uD83C\uDFF3\uFE0F\u200D\uD83C\uDF08|\uD83D\uDC69\u200D\uD83D\uDC67|\uD83D\uDC69\u200D\uD83D\uDC66|\uD83D\uDC15\u200D\uD83E\uDDBA|\uD83C\uDDFD\uD83C\uDDF0|\uD83C\uDDF6\uD83C\uDDE6|\uD83C\uDDF4\uD83C\uDDF2|\uD83E\uDDD1(?:\uD83C[\uDFFB-\uDFFF])|\uD83D\uDC69(?:\uD83C[\uDFFB-\uDFFF])|\uD83C\uDDFF(?:\uD83C[\uDDE6\uDDF2\uDDFC])|\uD83C\uDDFE(?:\uD83C[\uDDEA\uDDF9])|\uD83C\uDDFC(?:\uD83C[\uDDEB\uDDF8])|\uD83C\uDDFB(?:\uD83C[\uDDE6\uDDE8\uDDEA\uDDEC\uDDEE\uDDF3\uDDFA])|\uD83C\uDDFA(?:\uD83C[\uDDE6\uDDEC\uDDF2\uDDF3\uDDF8\uDDFE\uDDFF])|\uD83C\uDDF9(?:\uD83C[\uDDE6\uDDE8\uDDE9\uDDEB-\uDDED\uDDEF-\uDDF4\uDDF7\uDDF9\uDDFB\uDDFC\uDDFF])|\uD83C\uDDF8(?:\uD83C[\uDDE6-\uDDEA\uDDEC-\uDDF4\uDDF7-\uDDF9\uDDFB\uDDFD-\uDDFF])|\uD83C\uDDF7(?:\uD83C[\uDDEA\uDDF4\uDDF8\uDDFA\uDDFC])|\uD83C\uDDF5(?:\uD83C[\uDDE6\uDDEA-\uDDED\uDDF0-\uDDF3\uDDF7-\uDDF9\uDDFC\uDDFE])|\uD83C\uDDF3(?:\uD83C[\uDDE6\uDDE8\uDDEA-\uDDEC\uDDEE\uDDF1\uDDF4\uDDF5\uDDF7\uDDFA\uDDFF])|\uD83C\uDDF2(?:\uD83C[\uDDE6\uDDE8-\uDDED\uDDF0-\uDDFF])|\uD83C\uDDF1(?:\uD83C[\uDDE6-\uDDE8\uDDEE\uDDF0\uDDF7-\uDDFB\uDDFE])|\uD83C\uDDF0(?:\uD83C[\uDDEA\uDDEC-\uDDEE\uDDF2\uDDF3\uDDF5\uDDF7\uDDFC\uDDFE\uDDFF])|\uD83C\uDDEF(?:\uD83C[\uDDEA\uDDF2\uDDF4\uDDF5])|\uD83C\uDDEE(?:\uD83C[\uDDE8-\uDDEA\uDDF1-\uDDF4\uDDF6-\uDDF9])|\uD83C\uDDED(?:\uD83C[\uDDF0\uDDF2\uDDF3\uDDF7\uDDF9\uDDFA])|\uD83C\uDDEC(?:\uD83C[\uDDE6\uDDE7\uDDE9-\uDDEE\uDDF1-\uDDF3\uDDF5-\uDDFA\uDDFC\uDDFE])|\uD83C\uDDEB(?:\uD83C[\uDDEE-\uDDF0\uDDF2\uDDF4\uDDF7])|\uD83C\uDDEA(?:\uD83C[\uDDE6\uDDE8\uDDEA\uDDEC\uDDED\uDDF7-\uDDFA])|\uD83C\uDDE9(?:\uD83C[\uDDEA\uDDEC\uDDEF\uDDF0\uDDF2\uDDF4\uDDFF])|\uD83C\uDDE8(?:\uD83C[\uDDE6\uDDE8\uDDE9\uDDEB-\uDDEE\uDDF0-\uDDF5\uDDF7\uDDFA-\uDDFF])|\uD83C\uDDE7(?:\uD83C[\uDDE6\uDDE7\uDDE9-\uDDEF\uDDF1-\uDDF4\uDDF6-\uDDF9\uDDFB\uDDFC\uDDFE\uDDFF])|\uD83C\uDDE6(?:\uD83C[\uDDE8-\uDDEC\uDDEE\uDDF1\uDDF2\uDDF4\uDDF6-\uDDFA\uDDFC\uDDFD\uDDFF])|[#\*0-9]\uFE0F\u20E3|(?:\uD83C[\uDFC3\uDFC4\uDFCA]|\uD83D[\uDC6E\uDC71\uDC73\uDC77\uDC81\uDC82\uDC86\uDC87\uDE45-\uDE47\uDE4B\uDE4D\uDE4E\uDEA3\uDEB4-\uDEB6]|\uD83E[\uDD26\uDD37-\uDD39\uDD3D\uDD3E\uDDB8\uDDB9\uDDCD-\uDDCF\uDDD6-\uDDDD])(?:\uD83C[\uDFFB-\uDFFF])|(?:\u26F9|\uD83C[\uDFCB\uDFCC]|\uD83D\uDD75)(?:\uD83C[\uDFFB-\uDFFF])|(?:[\u261D\u270A-\u270D]|\uD83C[\uDF85\uDFC2\uDFC7]|\uD83D[\uDC42\uDC43\uDC46-\uDC50\uDC66\uDC67\uDC6B-\uDC6D\uDC70\uDC72\uDC74-\uDC76\uDC78\uDC7C\uDC83\uDC85\uDCAA\uDD74\uDD7A\uDD90\uDD95\uDD96\uDE4C\uDE4F\uDEC0\uDECC]|\uD83E[\uDD0F\uDD18-\uDD1C\uDD1E\uDD1F\uDD30-\uDD36\uDDB5\uDDB6\uDDBB\uDDD2-\uDDD5])(?:\uD83C[\uDFFB-\uDFFF])|(?:[\u231A\u231B\u23E9-\u23EC\u23F0\u23F3\u25FD\u25FE\u2614\u2615\u2648-\u2653\u267F\u2693\u26A1\u26AA\u26AB\u26BD\u26BE\u26C4\u26C5\u26CE\u26D4\u26EA\u26F2\u26F3\u26F5\u26FA\u26FD\u2705\u270A\u270B\u2728\u274C\u274E\u2753-\u2755\u2757\u2795-\u2797\u27B0\u27BF\u2B1B\u2B1C\u2B50\u2B55]|\uD83C[\uDC04\uDCCF\uDD8E\uDD91-\uDD9A\uDDE6-\uDDFF\uDE01\uDE1A\uDE2F\uDE32-\uDE36\uDE38-\uDE3A\uDE50\uDE51\uDF00-\uDF20\uDF2D-\uDF35\uDF37-\uDF7C\uDF7E-\uDF93\uDFA0-\uDFCA\uDFCF-\uDFD3\uDFE0-\uDFF0\uDFF4\uDFF8-\uDFFF]|\uD83D[\uDC00-\uDC3E\uDC40\uDC42-\uDCFC\uDCFF-\uDD3D\uDD4B-\uDD4E\uDD50-\uDD67\uDD7A\uDD95\uDD96\uDDA4\uDDFB-\uDE4F\uDE80-\uDEC5\uDECC\uDED0-\uDED2\uDED5\uDEEB\uDEEC\uDEF4-\uDEFA\uDFE0-\uDFEB]|\uD83E[\uDD0D-\uDD3A\uDD3C-\uDD45\uDD47-\uDD71\uDD73-\uDD76\uDD7A-\uDDA2\uDDA5-\uDDAA\uDDAE-\uDDCA\uDDCD-\uDDFF\uDE70-\uDE73\uDE78-\uDE7A\uDE80-\uDE82\uDE90-\uDE95])|(?:[#\*0-9\xA9\xAE\u203C\u2049\u2122\u2139\u2194-\u2199\u21A9\u21AA\u231A\u231B\u2328\u23CF\u23E9-\u23F3\u23F8-\u23FA\u24C2\u25AA\u25AB\u25B6\u25C0\u25FB-\u25FE\u2600-\u2604\u260E\u2611\u2614\u2615\u2618\u261D\u2620\u2622\u2623\u2626\u262A\u262E\u262F\u2638-\u263A\u2640\u2642\u2648-\u2653\u265F\u2660\u2663\u2665\u2666\u2668\u267B\u267E\u267F\u2692-\u2697\u2699\u269B\u269C\u26A0\u26A1\u26AA\u26AB\u26B0\u26B1\u26BD\u26BE\u26C4\u26C5\u26C8\u26CE\u26CF\u26D1\u26D3\u26D4\u26E9\u26EA\u26F0-\u26F5\u26F7-\u26FA\u26FD\u2702\u2705\u2708-\u270D\u270F\u2712\u2714\u2716\u271D\u2721\u2728\u2733\u2734\u2744\u2747\u274C\u274E\u2753-\u2755\u2757\u2763\u2764\u2795-\u2797\u27A1\u27B0\u27BF\u2934\u2935\u2B05-\u2B07\u2B1B\u2B1C\u2B50\u2B55\u3030\u303D\u3297\u3299]|\uD83C[\uDC04\uDCCF\uDD70\uDD71\uDD7E\uDD7F\uDD8E\uDD91-\uDD9A\uDDE6-\uDDFF\uDE01\uDE02\uDE1A\uDE2F\uDE32-\uDE3A\uDE50\uDE51\uDF00-\uDF21\uDF24-\uDF93\uDF96\uDF97\uDF99-\uDF9B\uDF9E-\uDFF0\uDFF3-\uDFF5\uDFF7-\uDFFF]|\uD83D[\uDC00-\uDCFD\uDCFF-\uDD3D\uDD49-\uDD4E\uDD50-\uDD67\uDD6F\uDD70\uDD73-\uDD7A\uDD87\uDD8A-\uDD8D\uDD90\uDD95\uDD96\uDDA4\uDDA5\uDDA8\uDDB1\uDDB2\uDDBC\uDDC2-\uDDC4\uDDD1-\uDDD3\uDDDC-\uDDDE\uDDE1\uDDE3\uDDE8\uDDEF\uDDF3\uDDFA-\uDE4F\uDE80-\uDEC5\uDECB-\uDED2\uDED5\uDEE0-\uDEE5\uDEE9\uDEEB\uDEEC\uDEF0\uDEF3-\uDEFA\uDFE0-\uDFEB]|\uD83E[\uDD0D-\uDD3A\uDD3C-\uDD45\uDD47-\uDD71\uDD73-\uDD76\uDD7A-\uDDA2\uDDA5-\uDDAA\uDDAE-\uDDCA\uDDCD-\uDDFF\uDE70-\uDE73\uDE78-\uDE7A\uDE80-\uDE82\uDE90-\uDE95])\uFE0F?|(?:[\u261D\u26F9\u270A-\u270D]|\uD83C[\uDF85\uDFC2-\uDFC4\uDFC7\uDFCA-\uDFCC]|\uD83D[\uDC42\uDC43\uDC46-\uDC50\uDC66-\uDC78\uDC7C\uDC81-\uDC83\uDC85-\uDC87\uDC8F\uDC91\uDCAA\uDD74\uDD75\uDD7A\uDD90\uDD95\uDD96\uDE45-\uDE47\uDE4B-\uDE4F\uDEA3\uDEB4-\uDEB6\uDEC0\uDECC]|\uD83E[\uDD0F\uDD18-\uDD1F\uDD26\uDD30-\uDD39\uDD3C-\uDD3E\uDDB5\uDDB6\uDDB8\uDDB9\uDDBB\uDDCD-\uDDCF\uDDD1-\uDDDD])){1,2}$/;
		},

		_plainToRich: function(message) {
			/**
			 * In Talk we only parse URLs with a protocol to avoid undesired
			 * clickables like composer.json. Therefor the method and regex were
			 * copied from OCP.Comments and adjusted accordingly.
			 */
			// var urlRegex = /(\s|^)(https?:\/\/)?((?:[-A-Z0-9+_]+\.)+[-A-Z]+(?:\/[-A-Z0-9+&@#%?=~_|!:,.;()]*)*)(\s|$)/ig;
			var urlRegex = /(\s|\(|^)(https?:\/\/)((?:[-A-Z0-9+_]+\.)+[-A-Z]+(?:\/[-A-Z0-9+&@#%?=~_|!:,.;()]*)*)(?=\s|\)|$)/ig;
			return message.replace(urlRegex, function (_, leadingSpace, protocol, url) {
				var trailingClosingBracket = '';
				if (url.substr(-1) === ')' && (url.indexOf('(') === -1 || leadingSpace === '(')) {
					url = url.substr(0, url.length - 1);
					trailingClosingBracket = ')';
				}
				var linkText = url;
				// if (!protocol) {
				// 	protocol = 'https://';
				// } else
				if (protocol === 'http://') {
					linkText = protocol + url;
				}

				return leadingSpace + '<a class="external" target="_blank" rel="noopener noreferrer" href="' + protocol + url + '">' + linkText + '</a>' + trailingClosingBracket;
			});
		},

		_onAddModelStart: function() {
			this._virtualList.appendElementStart();

			this._scrollToNew = this._virtualList.getLastElement() === this._virtualList.getLastVisibleElement();
		},

		_onAddModel: function(model) {
			var $el = $(this.commentTemplate(this._formatItem(model)));
			this._virtualList.appendElement($el);

			if (this._modelsHaveSameActor(this._lastAddedMessageModel, model) &&
				this._modelsAreTemporaryNear(this._lastAddedMessageModel, model, 3600) &&
				this.sameAuthorMessages < 20

			) {
				this.sameAuthorMessages++;
				if (this._modelsAreTemporaryNear(this._lastAddedMessageModel, model) &&
					this.temporaryNearMessages < 5) {
					$el.addClass('grouped');

					this.temporaryNearMessages++;
				} else {
					$el.addClass('same-author');
					this.temporaryNearMessages = 0;
				}
			} else {
				this.sameAuthorMessages = 0;
				this.temporaryNearMessages = 0;
			}

			// PHP timestamp is second-based; JavaScript timestamp is
			// millisecond based.
			model.set('date', new Date(model.get('timestamp') * 1000));

			if (!this._lastAddedMessageModel || !this._modelsHaveSameDate(this._lastAddedMessageModel, model)) {
				$el.attr('data-date', this._getDateSeparator(model.get('date')));
				$el.addClass('showDate');
			}

			// Keeping the model for the last added message is not only
			// practical, but needed, as the models for previous messages are
			// removed from the collection each time a new set of messages is
			// received.
			this._lastAddedMessageModel = model;

			this._postRenderItem(model, $el);
		},

		_onAddModelEnd: function() {
			this.$el.find('.emptycontent').toggleClass('hidden', true);

			this._virtualList.appendElementEnd();

			if (this._scrollToNew) {
				this._virtualList.scrollTo(this._virtualList.getLastElement());
			}
		},

		_getDateSeparator: function(timestamp) {
			var date = moment(timestamp, 'x'),
				today = moment(),
				dayOfYear = OC.Util.formatDate(date, 'YYYY-DDD'),
				dayOfYearToday = OC.Util.formatDate(today, 'YYYY-DDD');

			var relativePrefix = '';
			if (dayOfYear === dayOfYearToday) {
				relativePrefix = t('spreed', 'Today');
			} else {
				var yesterday = OC.Util.formatDate(today.subtract(1, 'd'), 'YYYY-DDD');

				if (dayOfYear === yesterday) {
					relativePrefix = t('spreed', 'Yesterday');
				} else {
					relativePrefix = date.fromNow();
				}
			}

			return t('spreed', '{relativeDate}, {absoluteDate}', {
				relativeDate: relativePrefix,
				// 'LL' formats a localized date including day of month, month
				// name and year
				absoluteDate: OC.Util.formatDate(timestamp, 'LL')
			}, undefined, {
				escape: false // French "Today" has a ' in it
			});
		},

		_modelsHaveSameActor: function(model1, model2) {
			if (!model1 || !model2) {
				return false;
			}

			return (model1.get('actorType') !== 'bots' || model1.get('actorId') === 'changelog') &&
				(model1.get('systemMessage').length === 0) === (model2.get('systemMessage').length === 0) &&
				model1.get('actorId') === model2.get('actorId') &&
				model1.get('actorType') === model2.get('actorType');
		},

		_modelsAreTemporaryNear: function(model1, model2, secondsThreshold) {
			if (!model1 || !model2) {
				return false;
			}

			if (_.isUndefined(secondsThreshold)) {
				secondsThreshold = 30;
			}

			return Math.abs(model1.get('timestamp') - model2.get('timestamp')) <= secondsThreshold;
		},

		_modelsHaveSameDate: function(model1, model2) {
			if (!model1 || !model2) {
				return false;
			}

			return model1.get('date').toDateString() === model2.get('date').toDateString();
		},

		/**
		 * If there is no model then it is being called on a message being
		 * composed.
		 */
		_postRenderItem: function(model, $el) {
			$el.find('.has-tooltip').tooltip({container: this._tooltipContainer});

			var setAvatar = function($element, size) {
				var userId = '' + $element.data('user-id');
				if (userId && userId.substr(0, 6) !== 'guest/') {
					if (userId === 'all') {
						$element.addClass('avatar icon icon-contacts');
					} else {
						$element.avatar(userId, size, undefined, false, undefined, $element.data('user-display-name'));
					}
				} else {
					var displayName = $element.data('user-display-name');
					var customName = displayName !== t('spreed', 'Guest') ? displayName : '';
					$element.imageplaceholder(customName ? customName.substr(0, 1) : '?', customName, size);
					$element.css('background-color', '#b9b9b9');
				}
			};
			$el.find('.authorRow .avatar').each(function() {
				var avatarSize = 32;
				if (model && model.get('actorType') === 'bots') {
					if (model.get('actorId') === 'changelog') {
						$(this).addClass('icon icon-changelog');
					} else {
						$(this).imageplaceholder('>_', $(this).data('displayname'), avatarSize);
						$(this).css('background-color', '#363636');
					}
				} else {
					setAvatar($(this), avatarSize);
				}
			});
			var inlineAvatars = $el.find('.message .avatar');
			if ($($el.context).hasClass('message')) {
				inlineAvatars = $el.find('.avatar');
			}
			var inlineAvatarSize = 16;
			inlineAvatars.each(function () {
				setAvatar($(this), inlineAvatarSize);
			});

			if (OC.getCurrentUser().uid &&
				model &&
				model.get('actorType') === 'users' &&
				model.get('actorId') !== OC.getCurrentUser().uid) {
				$el.find('.authorRow .avatar, .authorRow .author').contactsMenu(
					model.get('actorId'), 0, $el.find('.authorRow'));
			}

			var $message = $el.find('.message');
			this._postRenderMessage($message);
		},

		_postRenderMessage: function($el) {
			var self = this;

			$el.find('.filePreview').each(function() {
				self._renderFilePreview($(this));
			});

			// Contacts menu is not shown in public view.
			if (!OC.getCurrentUser().uid) {
				return;
			}

			$el.find('.mention-user').each(function() {
				var $this = $(this);
				var $avatar = $this.find('.avatar');

				var user = $avatar.data('user-id');
				if (user !== OC.getCurrentUser().uid) {
					$this.contactsMenu(user, 0, $this);
				}
			});
		},

		_renderFilePreview: function($filePreview) {
			var previewSize = Math.ceil(128 * window.devicePixelRatio);

			var defaultIconUrl = OC.imagePath('core', 'filetypes/file');
			var previewUrl = defaultIconUrl;
			if ($filePreview.data('preview-available') === 'yes') {
				previewUrl = OC.generateUrl(
					'/core/preview?fileId={fileId}&x={width}&y={height}',
					{
						fileId: $filePreview.data('file-id'),
						width: previewSize,
						height: previewSize
					});
			} else {
				previewUrl = OC.MimeType.getIconUrl($filePreview.data('mimetype'));
			}

			// If the default file icon can not be loaded either there is
			// nothing else that can be done, just remove the loading icon
			// and the image and leave only the message about a shared file.
			var handleDefaultIconLoadError = function() {
				$filePreview.removeClass('icon-loading');
				$filePreview.find('img').remove();
			};

			var img = new Image();

			var handlePreviewLoadError = function() {
				img.onerror = handleDefaultIconLoadError;

				img.src = defaultIconUrl;
			};

			img.onload = function() {
				$filePreview.removeClass('icon-loading');
			};

			$filePreview.addClass('icon-loading');

			img.width = previewSize;
			img.height = previewSize;

			if (OC.getCurrentUser().uid) {
				img.onerror = handlePreviewLoadError;
				img.src = previewUrl;
			} else {
				img.onerror = handleDefaultIconLoadError;
				img.src = defaultIconUrl;
			}

			$filePreview.prepend(img);
		},

		_onTypeComment: function(ev) {
			var $field = $(ev.target);
			var $submitButton = $field.data('submitButtonEl');
			if (!$submitButton) {
				$submitButton = $field.closest('form').find('.submit');
				$field.data('submitButtonEl', $submitButton);
			}

			var newMessageFieldOldHeight = this._newMessageFieldHeight;
			// Before the 3.0.0 release jQuery rounded the height to the nearest
			// integer, but Firefox has subpixel accuracy, so the height
			// returned by jQuery can not be used in the calculations.
			this._newMessageFieldHeight = $field.get(0).getBoundingClientRect().height;
			if (this._newMessageFieldHeight !== newMessageFieldOldHeight) {
				this.triggerMethod('newMessageFieldHeightChange', this._newMessageFieldHeight - newMessageFieldOldHeight);
			}

			// Submits form with Enter, but Shift+Enter is a new line. If the
			// autocomplete popover is being shown Enter does not submit the
			// form either; it will be handled by At.js which will add the
			// currently selected item to the message.
			if (ev.keyCode === 13 && !ev.shiftKey && !$field.atwho('isSelecting')) {
				$submitButton.click();
				ev.preventDefault();
			}

			// Pressing Arrow-up/down in an empty/unchanged input brings back the last sent messages
			if (this.lastComments.length !== 0 && !$field.atwho('isSelecting')) {

				if (ev.keyCode === 38 || ev.keyCode === 40) {
					this._loopThroughLastComments(ev, $field);
				} else {
					this.currentLastComment = -1;
				}
			}
		},

		_loopThroughLastComments: function(ev, $field) {
			if ($field.text().trim().length === 0 ||
				this.currentLastComment !== -1) {

				if (ev.keyCode === 38) {
					this.currentLastComment++;
				} else {
					if (this.currentLastComment === -1) {
						this.currentLastComment = this.lastComments.length - 1;
					} else {
						this.currentLastComment--;
					}
				}

				if (typeof this.lastComments[this.currentLastComment] !== 'undefined') {
					$field.html(this.lastComments[this.currentLastComment]);

					/**
					 * Jump to the end of the editable content:
					 * https://stackoverflow.com/a/3866442
					 */
					var range = document.createRange();//Create a range (a range is a like the selection but invisible)
					range.selectNodeContents(ev.target);//Select the entire contents of the element with the range
					range.collapse(false);//collapse the range to the end point. false means collapse to end rather than the start
					var selection = window.getSelection();//get the selection object (allows you to change selection)
					selection.removeAllRanges();//remove any selections already made
					selection.addRange(range);//make the range you have just created the visible selection
				} else {
					this.currentLastComment = -1;
					$field.text('');
				}

				ev.preventDefault();
			}

		},

		_commentBodyHTML2Plain: function($el) {
			var $comment = $el.clone();

			$comment.find('.mention-user').each(function () {
				var $this = $(this),
					$inserted = $this.parent(),
					userId = '' + $this.find('.avatar').data('user-id');
				if (userId.indexOf(' ') !== -1 || userId.indexOf('guest/') === 0) {
					$inserted.html('@"' + userId + '"');
				} else {
					$inserted.html('@' + userId);
				}
			});

			$comment.html($comment.html().replace(/<br>/g, "\n"));
			var message = $comment.text();

			// Little hack to replace the non-breaking space resulting from the editable div content with normal spaces
			return decodeURI(encodeURI(message).replace(/%C2%A0/g, '%20'));
		},

		_onSubmitComment: function(e) {
			var self = this;
			var $form = $(e.target);
			var $submit = $form.find('.submit');
			var $loading = $form.find('.submitLoading');
			var $commentField = $form.find('.message');
			var message = $commentField.text().trim();

			if (!message.length) {
				return false;
			}

			var htmlComment = $commentField.html();
			if (this.lastComments.length === 0 ||
				this.lastComments[0] !== htmlComment) {
				this.lastComments.unshift(htmlComment);
			}
			this.currentLastComment = -1;

			$commentField.prop('contenteditable', false);
			$submit.addClass('hidden');
			$loading.removeClass('hidden');

			message = this._commentBodyHTML2Plain($commentField);
			var data = {
				token: this.collection.token,
				message: message
			};

			if (!OC.getCurrentUser().uid) {
				var guestNick = OCA.SpreedMe.app._localStorageModel.get('nick');
				if (guestNick) {
					data.actorDisplayName = guestNick;
				}
			}

			var comment = new OCA.SpreedMe.Models.ChatMessage(data);
			comment.save({}, {
				success: function(model) {
					self._onSubmitSuccess(model, $form);
				},
				error: function(model, response) {
					if (response.status === 413) {
						self._onSubmitError($form, t('spreed', 'The message you are trying to send is too long'));
					} else {
						self._onSubmitError($form, t('spreed', 'Error occurred while sending message'));
					}
				}
			});

			return false;
		},

		_onSubmitSuccess: function(model, $form) {
			$form.find('.submit').removeClass('hidden');
			$form.find('.submitLoading').addClass('hidden');
			$form.find('.message').text('').prop('contenteditable', true);

			$form.find('.message').focus();

			// The new message does not need to be explicitly added to the list
			// of messages; it will be automatically fetched from the server
			// thanks to the auto-refresh of the list.
		},

		_onSubmitError: function($form, errorMsg) {
			$form.find('.submit').removeClass('hidden');
			$form.find('.submitLoading').addClass('hidden');
			$form.find('.message').prop('contenteditable', true);

			$form.find('.message').focus();

			OC.Notification.show(errorMsg, {type: 'error'});
		},

		_onAddShare: function() {
			var self = this;
			var $form = this.$el.find('.newCommentForm');
			var $shareButton = $form.find('.share');
			var $shareLoadingIcon = $form.find('.shareLoading');

			OC.dialogs.filepicker(t('spreed', 'File to share'), function(targetPath) {
				$shareButton.addClass('hidden');
				$shareLoadingIcon.removeClass('hidden');

				$.ajax({
					type: 'POST',
					url: OC.linkToOCS('apps/files_sharing/api/v1', 2) + 'shares',
					dataType: 'json',
					data: {
						shareType: OC.Share.SHARE_TYPE_ROOM,
						path: targetPath,
						shareWith: self.collection.token
					}
				}).always(function() {
					$shareLoadingIcon.addClass('hidden');
					$shareButton.removeClass('hidden');
				}).fail(function(xhr) {
					var message = t('spreed', 'Error while sharing');

					var result = xhr.responseJSON;
					if (result && result.ocs && result.ocs.meta) {
						if (result.ocs.meta.statuscode === 403) {
							return;
						}
						message = result.ocs.meta.message;
					}

					OC.Notification.showTemporary(message);
				});
			}, false, ['*', 'httpd/unix-directory'], true, OC.dialogs.FILEPICKER_TYPE_CHOOSE);
		},

	});

	OCA.SpreedMe.Views.ChatView = ChatView;

})(OCA, OC, OCP, Marionette, autosize, moment);

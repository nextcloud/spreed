/* global autosize, Handlebars, Marionette, moment, OC, OCA, OCP */

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

(function(OCA, OC, OCP, Marionette, Handlebars, autosize, moment) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var TEMPLATE =
		'<ul class="comments">' +
		'</ul>' +
		'<div class="emptycontent"><div class="icon-comment"></div>' +
		'<p>{{emptyResultLabel}}</p></div>' +
		'<div class="loading hidden" style="height: 50px"></div>';

	var ADD_COMMENT_TEMPLATE =
		'<div class="newCommentRow comment">' +
		'    <div class="authorRow currentUser">' +
		'        <div class="avatar" data-username="{{actorId}}"></div>' +
		'        {{#if actorId}}' +
		'            <div class="author">{{actorDisplayName}}</div>' +
		'        {{else}}' +
		'            <div class="guest-name"></div>' +
		'        {{/if}}' +
		'    </div>' +
		'    <form class="newCommentForm">' +
		'        <div contentEditable="true" class="message" data-placeholder="{{newMessagePlaceholder}}">{{message}}</div>' +
		'        <input class="submit icon-confirm" type="submit" value="" />' +
		'        <div class="submitLoading icon-loading-small hidden"></div>'+
		'        {{#if actorId}}' +
		'        <button class="share icon-add"></button>' +
		'        <div class="shareLoading icon-loading-small hidden"></div>'+
		'        {{/if}}' +
		'    </form>' +
		'</div>';

	var COMMENT_TEMPLATE =
		'<li class="comment{{#if isNotSystemMessage}}{{else}} systemMessage{{/if}}" data-id="{{id}}">' +
		'    <div class="authorRow{{#if isUserAuthor}} currentUser{{/if}}{{#if isGuest}} guestUser{{/if}}">' +
		'        {{#if isNotSystemMessage}}' +
		'        <div class="avatar" data-user-id="{{actorId}}" data-displayname="{{actorDisplayName}}"> </div>' +
		'        <div class="author">{{actorDisplayName}}</div>' +
		'        {{/if}}' +
		'        <div class="date has-tooltip{{#if relativeDate}} live-relative-timestamp{{/if}}" data-timestamp="{{timestamp}}" title="{{altDate}}">{{date}}</div>' +
		'    </div>' +
		'    <div class="message">{{{formattedMessage}}}</div>' +
		'</li>';

	var ChatView = Marionette.View.extend({

		groupedMessages: 0,

		className: 'chat',

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

		initialize: function() {
			this.listenTo(this.collection, 'reset', this.render);
			this.listenTo(this.collection, 'add', this._onAddModel);

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
						$li.find('.avatar').avatar(undefined, 32);
						return $li;
					},
					sorter: function (q, items) { return items; }
				},
				displayTpl: function (item) {
					return '<li>'
						+ '<span class="avatar-name-wrapper">'
						+ '<div class="avatar"'
						+ ' data-username="' + escapeHTML(item.id) + '"'	// for avatars
						+ ' data-user="' + escapeHTML(item.id) + '"'		// for contactsmenu
						+ ' data-user-display-name="' + escapeHTML(item.label) + '"></div>'
						+ ' <strong>' + escapeHTML(item.label) + '</strong>'
						+ '</span></li>';
				},
				insertTpl: function (item) {
					return '' +
						'<span class="mention-user avatar-name-wrapper">' +
							'<span class="avatar" ' +
									'data-username="' + escapeHTML(item.id) + '" ' + // for avatars
									'data-user="' + escapeHTML(item.id) + '" ' + // for contactsmenu
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
						'span[data-username="' + $el.find('[data-username]').data('username') + '"]'
					).parent()
				);
			});
		},

		_onAutoComplete: function(query, callback) {
			var self = this;

			if(_.isEmpty(query)) {
				return;
			}
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

		template: Handlebars.compile(TEMPLATE),
		templateContext: {
			emptyResultLabel: t('spreed', 'No messages yet, start the conversation!')
		},

		addCommentTemplate: function(params) {
			if (!this._addCommentTemplate) {
				this._addCommentTemplate = Handlebars.compile(ADD_COMMENT_TEMPLATE);
			}

			return this._addCommentTemplate(_.extend({
				actorId: OC.getCurrentUser().uid,
				actorDisplayName: OC.getCurrentUser().displayName,
				newMessagePlaceholder: t('spreed', 'New message …'),
				submitText: t('spreed', 'Send')
			}, params));
		},

		commentTemplate: function(params) {
			if (!this._commentTemplate) {
				this._commentTemplate = Handlebars.compile(COMMENT_TEMPLATE);
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

			if (OC.getCurrentUser().uid) {
				this.$el.find('.avatar').avatar(OC.getCurrentUser().uid, 32, undefined, false, undefined, OC.getCurrentUser().displayName);
			} else {
				this.$el.find('.avatar').imageplaceholder('?', this.getOption('guestNameModel').get('nick'), 128);
				this.$el.find('.avatar').css('background-color', '#b9b9b9');
				this.showChildView('guestName', this._guestNameEditableTextLabel, { replaceElement: true, allowMissingEl: true } );
			}

			this.delegateEvents();
			var $message = this.$el.find('.message');
			$message.blur().focus();
			$message.on('keydown input change', this._onTypeComment);

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
			this.$el.find('.has-tooltip').tooltip('destroy');
			this.$el.find('.has-tooltip').tooltip({container: this._tooltipContainer});
		},

		/**
		 * Saves the scroll position of the message list.
		 *
		 * This needs to be called before the chat view is detached in order to
		 * be able to restore the scroll position when attached again.
		 */
		saveScrollPosition: function() {
			var self = this;

			if (_.isUndefined(this.$container)) {
				return;
			}

			var containerHeight = this.$container.outerHeight();

			this._$lastVisibleComment = this.$container.children('.comment').filter(function() {
					return self._getCommentTopPosition($(this)) < containerHeight;
			}).last();
		},

		/**
		 * Restores the scroll position of the message list to the last saved
		 * position.
		 *
		 * When the scroll position is restored the size of the message list may
		 * have changed (for example, if the chat view was detached from the
		 * main view and attached to the sidebar); it is not possible to
		 * guarantee that exactly the same messages that were visible when the
		 * scroll position was saved will be visible when the scroll position is
		 * restored. Due to this, restoring the scroll position just ensures
		 * that the last message that was partially visible when it was saved
		 * will be fully visible when it is restored.
		 */
		restoreScrollPosition: function() {
			if (_.isUndefined(this.$container) || _.isUndefined(this._$lastVisibleComment)) {
				return;
			}

			var scrollBottom = 0;

			// When the last visible comment has a next sibling the scroll
			// position is based on the top position of that next sibling.
			// Basing it on the last visible comment top position and its height
			// could cause the next sibling to be shown due to a negative margin
			// "pulling it up" over the last visible comment bottom margin.
			var $nextSibling = this._$lastVisibleComment.next();
			if ($nextSibling.length > 0) {
				// Substract 1px to ensure that it does not scroll into the next
				// element (which would cause the next element to be fully shown
				// if saving and restoring the scroll position again) due to
				// rounding.
				scrollBottom = this._getCommentTopPosition($nextSibling) - 1;
			} else if (this._$lastVisibleComment.length > 0) {
				scrollBottom = this._getCommentTopPosition(this._$lastVisibleComment) + this._getCommentOuterHeight(this._$lastVisibleComment);
			}

			this.$container.scrollTop(scrollBottom - this.$container.outerHeight());
		},

		_formatItem: function(commentModel) {
			// PHP timestamp is second-based; JavaScript timestamp is
			// millisecond based.
			var timestamp = commentModel.get('timestamp') * 1000,
				relativeDate = moment(timestamp, 'x').diff(moment()) > -86400000;

			var actorDisplayName = commentModel.get('actorDisplayName');
			if (commentModel.get('actorType') === 'guests' &&
				actorDisplayName === '') {
				actorDisplayName = t('spreed', 'Guest');
			}
			if (actorDisplayName == null) {
				actorDisplayName = t('spreed', '[Unknown user name]');
			}

			var formattedMessage = escapeHTML(commentModel.get('message')).replace(/\n/g, '<br/>');
			formattedMessage = OCP.Comments.plainToRich(formattedMessage);
			formattedMessage = OCA.SpreedMe.RichObjectStringParser.parseMessage(
				formattedMessage, commentModel.get('messageParameters'));

			var data = _.extend({}, commentModel.attributes, {
				actorDisplayName: actorDisplayName,
				timestamp: timestamp,
				date: relativeDate ? OC.Util.relativeModifiedDate(timestamp) : OC.Util.formatDate(timestamp, 'LTS'),
				relativeDate: relativeDate,
				altDate: OC.Util.formatDate(timestamp),
				isNotSystemMessage: commentModel.get('systemMessage') === '',
				formattedMessage: formattedMessage
			});
			return data;
		},

		_onAddModel: function(model, collection, options) {
			this.$el.find('.emptycontent').toggleClass('hidden', true);

			var $newestComment = this.$container.children('.comment').last();
			var scrollToNew = $newestComment.length > 0 && this._getCommentTopPosition($newestComment) < this.$container.outerHeight();

			var $el = $(this.commentTemplate(this._formatItem(model)));
			if (!_.isUndefined(options.at) && collection.length > 1) {
				this.$container.find('li').eq(options.at).before($el);
			} else {
				this.$container.append($el);
			}

			if (this._modelsHaveSameActor(this._lastAddedMessageModel, model) &&
					this._modelsAreTemporaryNear(this._lastAddedMessageModel, model) &&
					this.groupedMessages < 10) {
				$el.addClass('grouped');

				this.groupedMessages++;
			} else {
				this.groupedMessages = 0;
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

			if (scrollToNew) {
				var newestCommentHiddenHeight = (this._getCommentTopPosition($newestComment) + this._getCommentOuterHeight($newestComment)) - this.$container.outerHeight();
				this.$container.scrollTop(this.$container.scrollTop() + newestCommentHiddenHeight + $el.outerHeight(true));
			}
		},

		_getCommentTopPosition: function($element) {
			// When the margin is positive, jQuery returns the proper top
			// position of the element (that is, including the top margin).
			// However, when it is negative, jQuery returns where the top
			// position of the element would be if there was no margin. Grouped
			// messages use a negative top margin to "pull them up" closer to
			// the previous message, so in those cases the top position returned
			// by jQuery is below the actual top position of the element.
			var marginTop = parseInt($element.css('margin-top'));
			if (marginTop >= 0) {
				return $element.position().top;
			}

			return $element.position().top + marginTop;
		},

		_getCommentOuterHeight: function($element) {
			// When the margin is positive, jQuery returns the proper outer
			// height of the element. However, when it is negative, it
			// substracts the negative margin from the overall height of the
			// element. Grouped messages use a negative top margin to "pull them
			// up" closer to the previous message, so in those cases the outer
			// height returned by jQuery is smaller than the actual height.
			var marginTop = parseInt($element.css('margin-top'));
			if (marginTop >= 0) {
				return $element.outerHeight(true);
			}

			return $element.outerHeight(true) - marginTop;
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

			return (model1.get('systemMessage').length === 0) === (model2.get('systemMessage').length === 0) &&
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
				if (!model || model.get('actorType') === 'users') {
					$element.avatar($element.data('user-id'), size, undefined, false, undefined, $element.data('displayname'));
				} else {
					$element.imageplaceholder('?', model.get('actorDisplayName'), size);
					$element.css('background-color', '#b9b9b9');
				}
			};
			$el.find('.authorRow .avatar').each(function() {
				setAvatar($(this), 32);
			});
			var inlineAvatars = $el.find('.message .avatar');
			if ($($el.context).hasClass('message')) {
				inlineAvatars = $el.find('.avatar');
			}
			inlineAvatars.each(function () {
				setAvatar($(this), 16);
			});

			var username = $el.find('.avatar').data('user-id');
			if (OC.getCurrentUser().uid &&
				model &&
				model.get('actorType') === 'users' &&
				username !== OC.getCurrentUser().uid) {
				$el.find('.authorRow .avatar, .authorRow .author').contactsMenu(
					username, 0, $el.find('.authorRow'));
			}

			var $message = $el.find('.message');
			this._postRenderMessage($message);
		},

		_postRenderMessage: function($el) {
			// Contacts menu is not shown in public view.
			if (!OC.getCurrentUser().uid) {
				return;
			}

			$el.find('.mention-user').each(function() {
				var $this = $(this);
				var $avatar = $this.find('.avatar');

				var user = $avatar.data('user');
				if (user !== OC.getCurrentUser().uid) {
					$this.contactsMenu(user, 0, $this);
				}
			});
		},

		_onTypeComment: function(ev) {
			var $field = $(ev.target);
			var $submitButton = $field.data('submitButtonEl');
			if (!$submitButton) {
				$submitButton = $field.closest('form').find('.submit');
				$field.data('submitButtonEl', $submitButton);
			}

			// Submits form with Enter, but Shift+Enter is a new line. If the
			// autocomplete popover is being shown Enter does not submit the
			// form either; it will be handled by At.js which will add the
			// currently selected item to the message.
			if (ev.keyCode === 13 && !ev.shiftKey && !$field.atwho('isSelecting')) {
				$submitButton.click();
				ev.preventDefault();
			}
		},

		_commentBodyHTML2Plain: function($el) {
			var $comment = $el.clone();

			$comment.find('.mention-user').each(function () {
				var $this = $(this);
				var $inserted = $this.parent();
				$inserted.html('@' + $this.find('.avatar').data('user'));
			});

			var oldHtml;
			var html = $comment.html();
			do {
				// replace works one by one
				oldHtml = html;
				html = oldHtml.replace("<br>", "\n");	// preserve line breaks
			} while(oldHtml !== html);
			$comment.html(html);

			return $comment.text();
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
				error: function() {
					self._onSubmitError($form);
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

		_onSubmitError: function($form) {
			$form.find('.submit').removeClass('hidden');
			$form.find('.submitLoading').addClass('hidden');
			$form.find('.message').prop('contenteditable', true);

			$form.find('.message').focus();

			OC.Notification.show(t('spreed', 'Error occurred while sending message'), {type: 'error'});
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
						message = result.ocs.meta.message;
					}

					OC.Notification.showTemporary(message);
				});
			}, false, ['*', 'httpd/unix-directory'], true, OC.dialogs.FILEPICKER_TYPE_CHOOSE);
		},

	});

	OCA.SpreedMe.Views.ChatView = ChatView;

})(OCA, OC, OCP, Marionette, Handlebars, autosize, moment);

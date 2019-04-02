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

		groupedMessages: 0,

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
						if ($avatar.data('user-id') === 'all') {
							$avatar.addClass('avatar icon icon-contacts');
						} else {
							$avatar.avatar($avatar.data('user-id'), 32);
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
			$message.on('keydown input change', _.bind(this._onTypeComment, this));

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
			if (actorDisplayName === null) {
				actorDisplayName = t('spreed', '[Unknown user name]');
			}

			var formattedMessage = escapeHTML(commentModel.get('message')).replace(/\n/g, '<br/>');
			formattedMessage = OCP.Comments.plainToRich(formattedMessage);
			formattedMessage = OCA.SpreedMe.Views.RichObjectStringParser.parseMessage(
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

		_onAddModelStart: function() {
			this._virtualList.appendElementStart();

			this._scrollToNew = this._virtualList.getLastElement() === this._virtualList.getLastVisibleElement();
		},

		_onAddModel: function(model) {
			var $el = $(this.commentTemplate(this._formatItem(model)));
			this._virtualList.appendElement($el);

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

			return model1.get('actorType') !== 'bots' &&
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
				if ($element.data('user-id')) {
					if ($element.data('user-id') === 'all') {
						$element.addClass('avatar icon icon-contacts');
					} else {
						$element.avatar($element.data('user-id'), size, undefined, false, undefined, $element.data('user-display-name'));
					}
				} else {
					$element.imageplaceholder('?', $element.data('displayname'), size);
					$element.css('background-color', '#b9b9b9');
				}
			};
			$el.find('.authorRow .avatar').each(function() {
				if (model && model.get('actorType') === 'bots') {
					if (model.get('actorId') === 'changelog') {
						$(this).addClass('icon icon-changelog');
					} else {
						$(this).imageplaceholder('>_', $(this).data('displayname'), 32);
						$(this).css('background-color', '#363636');
					}
				} else {
					setAvatar($(this), 32);
				}
			});
			var inlineAvatars = $el.find('.message .avatar');
			if ($($el.context).hasClass('message')) {
				inlineAvatars = $el.find('.avatar');
			}
			inlineAvatars.each(function () {
				setAvatar($(this), 16);
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
					userId = $this.find('.avatar').data('user-id');
				if (userId.indexOf(' ') !== -1) {
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

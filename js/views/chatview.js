/* global autosize, Backbone, Handlebars, OC, OCA */

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

(function(OCA, OC, Backbone, Handlebars, autosize) {
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
		'<div class="newCommentRow comment" data-id="{{id}}">' +
		'    <div class="authorRow">' +
		'        <div class="avatar" data-username="{{actorId}}"></div>' +
		'        <div class="author">{{actorDisplayName}}</div>' +
		'    </div>' +
		'    <form class="newCommentForm">' +
		'        <div contentEditable="true" class="message" data-placeholder="{{newMessagePlaceholder}}">{{message}}</div>' +
		'        <input class="submit icon-confirm" type="submit" value="" />' +
		'        <div class="submitLoading icon-loading-small hidden"></div>'+
		'    </form>' +
		'</div>';

	var COMMENT_TEMPLATE =
		'<li class="comment" data-id="{{id}}">' +
		'    <div class="authorRow">' +
		'        <div class="avatar" {{#if actorId}}data-username="{{actorId}}"{{/if}}> </div>' +
		'        <div class="author">{{actorDisplayName}}</div>' +
		'        <div class="date has-tooltip live-relative-timestamp" data-timestamp="{{timestamp}}" title="{{altDate}}">{{date}}</div>' +
		'    </div>' +
		'    <div class="message">{{{formattedMessage}}}</div>' +
		'</li>';

	var ChatView = Backbone.View.extend({

		events: {
			'submit .newCommentForm': '_onSubmitComment',
		},

		initialize: function() {
			this.listenTo(this.collection, 'reset', this.render);
			this.listenTo(this.collection, 'add', this._onAddModel);
		},

		template: function(params) {
			if (!this._template) {
				this._template = Handlebars.compile(TEMPLATE);
			}
			return this._template(params);
		},

		addCommentTemplate: function(params) {
			if (!this._addCommentTemplate) {
				this._addCommentTemplate = Handlebars.compile(ADD_COMMENT_TEMPLATE);
			}
			// FIXME handle guest users
			var currentUser = OC.getCurrentUser();
			return this._addCommentTemplate(_.extend({
				actorId: currentUser.uid,
				actorDisplayName: currentUser.displayName,
				newMessagePlaceholder: t('spreed', 'New message…'),
				submitText: t('spreed', 'Send')
			}, params));
		},

		commentTemplate: function(params) {
			if (!this._commentTemplate) {
				this._commentTemplate = Handlebars.compile(COMMENT_TEMPLATE);
			}
			return this._commentTemplate(params);
		},

		render: function() {
			delete this._lastAddedMessageModel;

			this.$el.html(this.template({
				emptyResultLabel: t('spreed', 'No messages yet, start the conversation!')
			}));
			this.$el.find('.comments').before(this.addCommentTemplate({}));
			this.$el.find('.has-tooltip').tooltip();
			this.$container = this.$el.find('ul.comments');
			// FIXME handle guest users
			this.$el.find('.avatar').avatar(OC.getCurrentUser().uid, 32);
			this.delegateEvents();
			this.$el.find('.message').on('keydown input change', this._onTypeComment);

			autosize(this.$el.find('.newCommentRow .message'));

			return this;
		},

		_formatItem: function(commentModel) {
			// PHP timestamp is second-based; JavaScript timestamp is
			// millisecond based.
			var timestamp = commentModel.get('timestamp') * 1000;

			var actorDisplayName = commentModel.get('actorDisplayName');
			if (commentModel.attributes.actorType === 'guests') {
				// FIXME get guest name from WebRTC or something like that
				actorDisplayName = 'Guest';
			}
			if (actorDisplayName == null) {
				actorDisplayName = t('spreed', '[Unknown user name]');
			}

			var data = _.extend({}, commentModel.attributes, {
				actorDisplayName: actorDisplayName,
				timestamp: timestamp,
				date: OC.Util.relativeModifiedDate(timestamp),
				altDate: OC.Util.formatDate(timestamp, 'LL LTS'),
				formattedMessage: escapeHTML(commentModel.get('message')).replace(/\n/g, '<br/>')
			});
			return data;
		},

		_onAddModel: function(model, collection, options) {
			this.$el.find('.emptycontent').toggleClass('hidden', true);

			var $el = $(this.commentTemplate(this._formatItem(model)));
			if (!_.isUndefined(options.at) && collection.length > 1) {
				this.$container.find('li').eq(options.at).before($el);
			} else {
				this.$container.prepend($el);
			}

			if (this._modelsHaveSameActor(this._lastAddedMessageModel, model) &&
					this._modelsAreTemporaryNear(this._lastAddedMessageModel, model)) {
				$el.next().addClass('grouped');
			}

			// PHP timestamp is second-based; JavaScript timestamp is
			// millisecond based.
			model.set('date', new Date(model.get('timestamp') * 1000));

			if (this._lastAddedMessageModel && !this._modelsHaveSameDate(this._lastAddedMessageModel, model)) {
				// 'LL' formats a localized date including day of month, month
				// name and year
				$el.next().attr('data-date', OC.Util.formatDate(this._lastAddedMessageModel.get('date'), 'LL'));
				$el.next().addClass('showDate');
			}

			// Keeping the model for the last added message is not only
			// practical, but needed, as the models for previous messages are
			// removed from the collection each time a new set of messages is
			// received.
			this._lastAddedMessageModel = model;

			this._postRenderItem($el);
		},

		_modelsHaveSameActor: function(model1, model2) {
			if (!model1 || !model2) {
				return false;
			}

			return model1.get('actorId') === model2.get('actorId') &&
				model1.get('actorType') === model2.get('actorType');
		},

		_modelsAreTemporaryNear: function(model1, model2, secondsThreshold) {
			if (!model1 || !model2) {
				return false;
			}

			if (_.isUndefined(secondsThreshold)) {
				secondsThreshold = 120;
			}

			return Math.abs(model1.get('timestamp') - model2.get('timestamp')) <= secondsThreshold;
		},

		_modelsHaveSameDate: function(model1, model2) {
			if (!model1 || !model2) {
				return false;
			}

			return model1.get('date').toDateString() === model2.get('date').toDateString();
		},

		_postRenderItem: function($el) {
			$el.find('.has-tooltip').tooltip();
			$el.find('.avatar').each(function() {
				var $this = $(this);
				$this.avatar($this.attr('data-username'), 32);
			});

			// FIXME do not show contacts menu for guest users
			var username = $el.find('.avatar').data('username');
			if (username !== oc_current_user) {
				$el.find('.authorRow .avatar, .authorRow .author').contactsMenu(
					username, 0, $el.find('.authorRow'));
			}

			var $message = $el.find('.message');
			this._postRenderMessage($message);
		},

		_postRenderMessage: function($el) {
			$el.find('.avatar').each(function() {
				var avatar = $(this);
				var strong = $(this).next();
				var appendTo = $(this).parent();

				$.merge(avatar, strong).contactsMenu(avatar.data('user'), 0, appendTo);
			});
		},

		_onTypeComment: function(ev) {
			var $field = $(ev.target);
			var $submitButton = $field.data('submitButtonEl');
			if (!$submitButton) {
				$submitButton = $field.closest('form').find('.submit');
				$field.data('submitButtonEl', $submitButton);
			}

			// Submits form with Enter, but Shift+Enter is a new line
			if (ev.keyCode === 13 && !ev.shiftKey) {
				$submitButton.click();
				ev.preventDefault();
			}
		},

		_commentBodyHTML2Plain: function($el) {
			var $comment = $el.clone();

			var oldHtml;
			var html = $comment.html();
			do {
				// replace works one by one
				oldHtml = html;
				html = oldHtml.replace("<br>", "\n");	// preserve line breaks
				console.warn(html);
			} while(oldHtml !== html);
			$comment.html(html);

			return $comment.text();
		},

		_onSubmitComment: function(e) {
			var self = this;
			var $form = $(e.target);
			var comment = null;
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

			comment = new OCA.SpreedMe.Models.ChatMessage({
				token: this.collection.token,
				message: message
			});
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

			// The new message does not need to be explicitly added to the list
			// of messages; it will be automatically fetched from the server
			// thanks to the auto-refresh of the list.
		},

		_onSubmitError: function($form) {
			$form.find('.submit').removeClass('hidden');
			$form.find('.submitLoading').addClass('hidden');
			$form.find('.message').prop('contenteditable', true);

			OC.Notification.show(t('spreed', 'Error occurred while sending message'), {type: 'error'});
		},

	});

	OCA.SpreedMe.Views.ChatView = ChatView;

})(OCA, OC, Backbone, Handlebars, autosize);

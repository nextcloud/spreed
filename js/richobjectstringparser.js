/* global OC, OCA, Handlebars */

/**
 * @copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

(function(OC, OCA, Handlebars) {

	OCA.SpreedMe.RichObjectStringParser = {

		_userLocalTemplate: '' +
				'<span class="atwho-inserted" contenteditable="false">' +
					'<span class="mention-user avatar-name-wrapper {{#if isCurrentUser}}currentUser{{/if}}">' +
						'<span class="avatar" ' +
								'data-username="{{id}}" ' +
								'data-user="{{id}}" ' +
								'data-user-display-name="{{name}}">' +
						'</span>' +
						'<strong>{{name}}</strong>' +
					'</span>' +
				'</span>',

		_unknownTemplate: '<strong>{{name}}</strong>',
		_unknownLinkTemplate: '<a href="{{link}}" class="external" target="_blank" rel="noopener noreferrer"><strong>{{name}}</strong></a>',

		/**
		 * @param {string} subject
		 * @param {Object} parameters
		 * @returns {string}
		 */
		parseMessage: function(subject, parameters) {
			var self = this,
				regex = /\{([a-z0-9-]+)\}/gi,
				matches = subject.match(regex);

			_.each(matches, function(parameter) {
				parameter = parameter.substring(1, parameter.length - 1);
				if (!parameters.hasOwnProperty(parameter) || !parameters[parameter]) {
					// Malformed translation?
					console.error('Potential malformed ROS string: parameter {' + parameter + '} was found in the string but is missing from the parameter list');
					return;
				}

				var parsed = self.parseParameter(parameters[parameter]);
				subject = subject.replace('{' + parameter + '}', parsed);
			});

			return subject;
		},

		/**
		 * @param {Object} parameter
		 * @param {string} parameter.type
		 * @param {string} parameter.id
		 * @param {string} parameter.name
		 * @param {string} parameter.link
		 */
		parseParameter: function(parameter) {
			switch (parameter.type) {
				case 'user':
					if (!this.userLocalTemplate) {
						this.userLocalTemplate = Handlebars.compile(this._userLocalTemplate);
					}
					if (!parameter.name) {
						parameter.name = parameter.id;
					}
					if (OC.getCurrentUser().uid === parameter.id) {
						parameter.isCurrentUser = true;
					}
					return this.userLocalTemplate(parameter);

				default:
					if (!_.isUndefined(parameter.link)) {
						if (!this.unknownLinkTemplate) {
							this.unknownLinkTemplate = Handlebars.compile(this._unknownLinkTemplate);
						}
						return this.unknownLinkTemplate(parameter);
					}

					if (!this.unknownTemplate) {
						this.unknownTemplate = Handlebars.compile(this._unknownTemplate);
					}
					return this.unknownTemplate(parameter);
			}
		}

	};

})(OC, OCA, Handlebars);

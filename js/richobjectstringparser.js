/* global OCA, Handlebars */

/**
 * @copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

(function(OCA, Handlebars) {

	OCA.SpreedMe.RichObjectStringParser = {

		_userLocalTemplate: '<span class="avatar-name-wrapper" data-user="{{id}}"><div class="avatar" data-user="{{id}}" data-user-display-name="{{name}}"></div><strong>{{name}}</strong></span>',

		_unknownTemplate: '<strong>{{name}}</strong>',
		_unknownLinkTemplate: '<a href="{{link}}">{{name}}</a>',

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

})(OCA, Handlebars);

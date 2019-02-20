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

(function(OCA, Marionette, Handlebars) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var TEMPLATE =
		'<div class="label-wrapper">' +
		'	<{{labelTagName}} class="label">{{text}}</{{labelTagName}}>' +
		'	{{#if editionEnabled}}' +
		'		<div class="edit-button"><span class="icon button icon-rename" {{#if buttonTitle}} title="{{buttonTitle}}" {{/if}}></span></div>' +
		'	{{/if}}' +
		'</div>' +
		'{{#if editionEnabled}}' +
		'	<div class="input-wrapper hidden-important">' +
		'		<input class="username" {{#if inputMaxLength}} maxlength="{{inputMaxLength}}" {{/if}} type="text" value="{{inputValue}}" {{#if inputPlaceholder}} placeholder="{{inputPlaceholder}}" {{/if}}>'+
		'		<input type="submit" value="" class="icon icon-confirm confirm-button"></div>'+
		'	</div>' +
		'{{/if}}';

	/**
	 * View for an editable text label.
	 *
	 * In its main state, an EditableTextLabel shows text in a label (an HTML
	 * element that can contain a line of text, like "<h1>" or "<p>"). The text
	 * comes from an attribute in a Backbone model and is automatically updated
	 * when the attribute changes.
	 *
	 * It also provides an edition state in which a text input field replaces
	 * the label, making possible to edit and save the attribute of the model.
	 * The EditableTextLabel can be make read-only by calling
	 * "disableEdition()", or read-write by calling "enableEdition()".
	 *
	 * The EditableTextLabel works on a single attribute of a model; they must
	 * be set in the constructor using the "model" and "modelAttribute" options
	 * (the first is the Backbone model to get the attribute from, the second is
	 * the name of the attribute). The "modelSaveOptions" option can be set if
	 * needed to control the options passed to "Model.save", and
	 * "extraClassNames", "labelTagName", "labelPlaceholder", "inputMaxLength",
	 * "inputPlaceholder" and "buttonTitle" can be used to customize some
	 * elements of the view.
	 *
	 * After initialization, and once the view has been rendered, the
	 * "modelAttribute" and "labelPlaceholder" options can be updated using the
	 * "setModelAttribute" and "setLabelPlaceholder" methods.
	 */
	var EditableTextLabel = Marionette.View.extend({

		className: function() {
			return 'editable-text-label' + (this.getOption('extraClassNames')? ' ' + this.getOption('extraClassNames') : '');
		},

		labelTagName: 'p',

		buttonTitle: t('spreed', 'Edit'),

		ui: {
			labelWrapper: '.label-wrapper',
			label: '.label',
			editButton: '.edit-button',
			inputWrapper: '.input-wrapper',
			input: 'input.username',
			confirmButton: '.confirm-button',
		},

		events: {
			'click @ui.editButton': 'showInput',
			'keyup @ui.input': 'handleInputKeyUp',
			'click @ui.confirmButton': 'confirmEdit',
		},

		modelEvents: function() {
			var modelEvents = {};
			modelEvents['change:' + this.modelAttribute] = 'updateText';

			return modelEvents;
		},

		template: Handlebars.compile(TEMPLATE),

		templateContext: function() {
			return {
				text: this._getText(),

				editionEnabled: this._editionEnabled,

				labelTagName: this.getOption('labelTagName'),
				inputMaxLength: this.getOption('inputMaxLength'),
				// The text of the label is not used as input value as it could
				// contain a placeholder text.
				inputValue: this.model.get(this.modelAttribute),
				inputPlaceholder: this.getOption('inputPlaceholder'),
				buttonTitle: this.getOption('buttonTitle')
			};
		},

		initialize: function(options) {
			this.mergeOptions(options, ['model', 'modelAttribute', 'modelSaveOptions', 'labelPlaceholder']);

			this._editionEnabled = true;
		},

		setModelAttribute: function(modelAttribute) {
			if (this.modelAttribute === modelAttribute) {
				return;
			}

			var modelEvents = _.result(this, 'modelEvents');
			this.unbindEvents(this.model, modelEvents);

			this.modelAttribute = modelAttribute;

			modelEvents = _.result(this, 'modelEvents');
			this.bindEvents(this.model, modelEvents);

			this.updateText();
			this.hideInput();
		},

		setLabelPlaceholder: function(labelPlaceholder) {
			if (this.labelPlaceholder === labelPlaceholder) {
				return;
			}

			this.labelPlaceholder = labelPlaceholder;

			this.updateText();
		},

		enableEdition: function() {
			if (this._editionEnabled) {
				return;
			}

			this._editionEnabled = true;

			this.render();
		},

		disableEdition: function() {
			if (!this._editionEnabled) {
				return;
			}

			this._editionEnabled = false;

			this.render();
		},

		_getText: function() {
			return this.model.get(this.modelAttribute) || this.labelPlaceholder || '';
		},

		updateText: function() {
			this.getUI('label').text(this._getText());
		},

		showInput: function() {
			this.getUI('input').val(this.model.get(this.modelAttribute));

			this.getUI('inputWrapper').removeClass('hidden-important');
			this.getUI('labelWrapper').addClass('hidden-important');

			this.getUI('input').focus();
		},

		hideInput: function() {
			this.getUI('labelWrapper').removeClass('hidden-important');
			this.getUI('inputWrapper').addClass('hidden-important');
		},

		handleInputKeyUp: function(event) {
			if (event.keyCode === 13) {
				// Enter
				this.confirmEdit();
			} else if (event.keyCode === 27) {
				// ESC
				this.hideInput();
			}
		},

		confirmEdit: function() {
			var newText = this.getUI('input').val().trim();

			if (newText === this.model.get(this.modelAttribute)) {
				this.hideInput();

				return;
			}

			var options = _.clone(this.modelSaveOptions || {});
			options.success = _.bind(function() {
				this.hideInput();

				if (this.modelSaveOptions && _.isFunction(this.modelSaveOptions.success)) {
					this.modelSaveOptions.success.apply(this, arguments);
				}
			}, this);
			options.error = _.bind(function() {
				this.hideInput();
			}, this);

			this.model.save(this.modelAttribute, newText, options);
		},

	});

	OCA.SpreedMe.Views.EditableTextLabel = EditableTextLabel;

})(OCA, Marionette, Handlebars);

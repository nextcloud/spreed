/* global Marionette */

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

(function(OCA, Marionette) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.Talk = OCA.Talk || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};
	OCA.Talk.Views = OCA.Talk.Views || {};

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
	 * It is recommended, although not strictly needed, to wait for the server
	 * response before setting the new attribute value in the model; otherwise,
	 * in case of failure the label will show the new value of the attribute
	 * even if it was not set in the server.
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
			editButton: '.edit-button button',
			inputWrapper: '.input-wrapper',
			input: 'input.username',
			confirmButton: '.confirm-button',
			loadingIcon: '.icon-loading-small',
		},

		events: {
			'keydown @ui.editButton': 'preventConfirmEditOnNextInputKeyUp',
			'click @ui.editButton': 'showInput',
			'keyup @ui.input': 'handleInputKeyUp',
			'click @ui.confirmButton': 'confirmEdit',
		},

		modelEvents: function() {
			var modelEvents = {};
			modelEvents['change:' + this.modelAttribute] = 'updateText';

			return modelEvents;
		},

		template: function(context) {
			// OCA.Talk.Views.Templates may not have been initialized when this
			// view is initialized, so the template can not be directly
			// assigned.
			return OCA.Talk.Views.Templates['editabletextlabel'](context);
		},

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

			// Needed to use "getUI" before the view is first rendered (even if
			// no elements would exist at that point).
			this.bindUIElements();
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

		/**
		 * Prevents the edition to be confirmed on the next key up event on the
		 * input.
		 *
		 * When Enter is pressed in the edit button the default behaviour is to
		 * trigger a click event which, in turn, shows and focus the input.
		 * However, as the enter key is still pressed as soon as it is released
		 * a key up event is triggered, now on the focused input, which would
		 * confirm the edit and hide again the input.
		 *
		 * Note that confirming the edition is only prevented for the first key
		 * up event. If the Enter key is kept pressed on an input the browser
		 * periodically generates new key down and key up events; surprisingly
		 * the "repeat" property of the event is "false", so it can not be
		 * distinguished if the key is being kept pressed. Due to this it is not
		 * possible to prevent confirming the edition until the Enter key is
		 * actually released for the first time after showing the input.
		 */
		preventConfirmEditOnNextInputKeyUp: function(event) {
			if (event.keyCode !== 13) {
				return;
			}

			this.getUI('input').one('keyup', function(event) {
				event.stopPropagation();
			}.bind(this));
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

			this.getUI('editButton').focus();
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

			this.ui.input.prop('disabled', true);
			this.ui.confirmButton.addClass('hidden');
			this.ui.loadingIcon.removeClass('hidden');

			var restoreState = function() {
				this.ui.input.prop('disabled', false);
				this.ui.confirmButton.removeClass('hidden');
				this.ui.loadingIcon.addClass('hidden');
			}.bind(this);

			// TODO This should show the error message instead of just hiding
			// the input without changes.
			var hideInputOnValidationError = function(/*model, error*/) {
				this.hideInput();
				restoreState();
			}.bind(this);
			this.model.listenToOnce(this.model, 'invalid', hideInputOnValidationError);

			var options = _.clone(this.modelSaveOptions || {});
			options.success = _.bind(function() {
				this.model.stopListening(this.model, 'invalid', hideInputOnValidationError);

				this.hideInput();
				restoreState();

				if (this.modelSaveOptions && _.isFunction(this.modelSaveOptions.success)) {
					this.modelSaveOptions.success.apply(this, arguments);
				}
			}, this);
			options.error = _.bind(function() {
				this.model.stopListening(this.model, 'invalid', hideInputOnValidationError);

				this.hideInput();
				restoreState();

				if (this.modelSaveOptions && _.isFunction(this.modelSaveOptions.error)) {
					this.modelSaveOptions.error.apply(this, arguments);
				}
			}, this);

			this.model.save(this.modelAttribute, newText, options);
		},

	});

	OCA.SpreedMe.Views.EditableTextLabel = EditableTextLabel;

})(OCA, Marionette);

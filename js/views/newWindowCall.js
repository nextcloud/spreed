/* global Marionette, Handlebars */

(function(OC, OCA, Marionette, Handlebars) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var ITEM_TEMPLATE = '<h3>Your videocall is in a separate window</h3>' +
		'<button class="close-separateWindow"> Back to main screen </button>';

	var SeparateWindowCall = Marionette.View.extend({
		tagName: '#separate-window-message',

		events: {
			'click .close-separateWindow': 'closeWindow'
		},
		ui: {

		},
		template: Handlebars.compile(ITEM_TEMPLATE),

		initialize: function() {
			var height = 335;
			var width = 405;
			var top = (window.screen.height - height) - 5 ;
			var left = (window.screen.width - width) - 5;
			var popStile = 'top='+top+', left='+left+', width='+width+', height='+height+', location=no, status=no, menubar=no, toolbar=no, titlebar=0, scrollbars=no, resizable=no';
			this.roomUrl = window.location.href;
			this.newWindow = window.open(this.roomUrl, '', popStile);
		},

		closeWindow: function() {
			this.newWindow.close();
			$('#add-content').removeClass('hidden');
			$('#separate-window-message').addClass('hidden');
		},

		feedbackScreen: function() {
			$('#app-content').addClass('hidden');
			$('#separate-window-message').removeClass('hidden');
		},

		populateNewWindow: function() {
			this.newWindow.document.body.append(this.localVideo);
			this.newWindow.document.body.setAttribute('class', 'separate-window');

			$(this.btnPop).addClass('hidden');
			$(this.btnClose).removeClass('hidden');
		},

		onRender: function() {
			var self = this;
			this.newWindow.focus();
			this.feedbackScreen();

			setTimeout( function(){
				if(self.newWindow.document.body.innerHTML !== ''){
					//self.appEl = self.newWindow.document.getElementById('app-content');
					self.localVideo = self.newWindow.document.getElementById('localVideo');
					self.btnPop = self.newWindow.document.getElementById('video-separateWindow');
					self.btnClose = self.newWindow.document.getElementById('btnCloseWindow');
					self.newWindow.document.body.innerHTML = '';
					self.populateNewWindow();
				}
			}, 3000);
		}
	});

	OCA.SpreedMe.Views.SeparateWindowCall = SeparateWindowCall;

})(OC, OCA, Marionette, Handlebars);

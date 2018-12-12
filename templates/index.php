<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */

vendor_script('select2/select2');
vendor_style('select2/select2');

style('spreed', 'style');
style('spreed', 'chatview');
style('spreed', 'autocomplete');
style('spreed', 'video');
script(
	'spreed',
	[
		'vendor/backbone/backbone-min',
		'vendor/backbone.radio/build/backbone.radio.min',
		'vendor/backbone.marionette/lib/backbone.marionette.min',
		'vendor/handlebars/handlebars.min',
		'vendor/jshashes/hashes.min',
		'vendor/Caret.js/dist/jquery.caret.min',
		'vendor/At.js/dist/js/jquery.atwho.min',
		'models/chatmessage',
		'models/chatmessagecollection',
		'models/room',
		'models/roomcollection',
		'models/participant',
		'models/participantcollection',
		'views/callbutton',
		'views/callinfoview',
		'views/chatview',
		'views/editabletextlabel',
		'views/emptycontentview',
		'views/mediacontrolsview',
		'views/participantlistview',
		'views/participantview',
		'views/richobjectstringparser',
		'views/roomlistview',
		'views/sidebarview',
		'views/tabview',
		'views/templates',
		'views/virtuallist',
		'simplewebrtc',
		'webrtc',
		'signaling',
		'connection',
		'app',
		'init',
	]
);
?>

<div id="app" class="nc-enable-screensharing-extension" data-token="<?php p($_['token']) ?>">
	<script type="text/json" id="signaling-settings">
	<?php echo json_encode($_['signaling-settings']) ?>
	</script>
</div>

<div id="app-navigation" class="icon-loading">
	<form id="oca-spreedme-add-room">
		<input id="select-participants" class="select2-offscreen" type="text" placeholder="<?php p($l->t('New conversation …')) ?>"/>
	</form>
	<ul id="spreedme-room-list" class="with-icon">
	</ul>
</div>

<div id="app-content" class="participants-1">

	<div id="app-content-wrapper">
		<button id="video-fullscreen" class="icon-fullscreen icon-white icon-shadow hidden" data-placement="bottom" data-toggle="tooltip" data-original-title="<?php p($l->t('Fullscreen (f)')) ?>"></button>

		<div id="video-speaking">

	</div>
	<div id="videos">
		<div class="videoView videoContainer hidden" id="localVideoContainer">
			<video id="localVideo"></video>
			<div class="avatar-container hidden">
				<div class="avatar"></div>
			</div>
			<div class="nameIndicator">
			</div>
		</div>
	</div>

	<div id="screens"></div>

		<div id="emptycontent">
			<div id="emptycontent-icon" class="icon-talk"></div>
			<h2><?php p($l->t('Join a conversation or start a new one')) ?></h2>
			<p class="emptycontent-additional"><?php p($l->t('Say hi to your friends and colleagues!')) ?></p>
			<div id="shareRoomContainer" class="" style="display: inline-flex">
				<input id="shareRoomInput" class="share-room-input hidden" readonly="readonly" type="text"/>
				<div id="shareRoomClipboardButton" class="shareRoomClipboard icon-clippy hidden" data-clipboard-target="#shareRoomInput"></div>
			</div>

		</div>

	</div>
</div>

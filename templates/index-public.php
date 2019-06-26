<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */

style('spreed', 'merged');
script('spreed', 'merged-guest');
?>

<div id="app" class="nc-enable-screensharing-extension" data-token="<?php p($_['token']) ?>">
	<script type="text/json" id="signaling-settings">
	<?php echo json_encode($_['signaling-settings']) ?>
	</script>
</div>

<div id="app-content" class="participants-1">

	<div id="app-content-wrapper">
		<button id="video-fullscreen" class="icon-fullscreen force-icon-white-in-call icon-shadow public" data-placement="bottom" data-toggle="tooltip" data-original-title="<?php p($l->t('Fullscreen (f)')) ?>"></button>

		<div id="videos"></div>

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

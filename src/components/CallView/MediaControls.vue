<template>
	<div class="controls">
		<button class="forced-white icon-audio" />
		<button class="forced-white icon-video" />
		<button class="forced-white icon-screen" />
	</div>
</template>

<script>
export default {
	name: 'MediaControls',
}
</script>

<style lang="scss" scoped>
.controls {
	width: 100%;
	margin: 0 auto;

	button {
		background-color: transparent !important;
		border: none;
		width: 32px;
		height: 32px;
		background-size: 22px;
	}
}
</style>

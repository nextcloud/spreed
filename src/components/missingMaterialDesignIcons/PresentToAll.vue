<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon present-to-all-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg :fill="fillColor"
			class="material-design-icon__svg"
			:width="size"
			:height="size"
			viewBox="0 0 24 24">
			<path d="M0 0h24v24H0V0z" fill="none" />
			<path d="M21 3H3c-1.11 0-2 .89-2 2v14c0 1.11.89 2 2 2h18c1.11 0 2-.89 2-2V5c0-1.11-.89-2-2-2zm0 16.02H3V4.98h18v14.04zM10 12H8l4-4 4 4h-2v4h-4v-4z" />
			<title v-if="title">{{ title }}</title>
		</svg>
	</span>
</template>

<script>
export default {
	name: 'PresentToAll',
	props: {
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>

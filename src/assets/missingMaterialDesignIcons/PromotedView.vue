<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon promoted-view-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg :fill="fillColor"
			class="material-design-icon__svg"
			:width="size"
			:height="size"
			viewBox="0 0 16 16">
			<rect x="1"
				y="1"
				width="13"
				height="9" />
			<rect x="1"
				y="12"
				width="3"
				height="3" />
			<rect x="6"
				y="12"
				width="3"
				height="3" />
			<rect x="11"
				y="12"
				width="3"
				height="3" />
			<title v-if="title">{{ title }}</title>
		</svg>
	</span>
</template>

<script>
export default {
	name: 'PromotedView',
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

	emits: ['click'],
}
</script>

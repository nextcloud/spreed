<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon grid-view-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg :fill="fillColor"
			class="material-design-icon__svg"
			:width="size"
			:height="size"
			viewBox="0 0 16 16">
			<g>
				<rect width="7" height="7" rx="2" />
				<rect y="9"
					width="7"
					height="7"
					rx="2" />
				<rect x="9"
					width="7"
					height="7"
					rx="2" />
				<rect x="9"
					y="9"
					width="7"
					height="7"
					rx="2" />
			</g>
			<title v-if="title">{{ title }}</title>
		</svg>
	</span>
</template>

<script>
export default {
	name: 'GridView',
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

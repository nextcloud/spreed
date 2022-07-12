<template functional>
	<span :aria-hidden="props.decorative"
		:aria-label="props.title"
		:class="[data.class, data.staticClass]"
		class="material-design-icon present-to-all-icon"
		role="img"
		v-bind="data.attrs"
		v-on="listeners">
		<svg :fill="props.fillColor"
			class="material-design-icon__svg"
			:width="props.size"
			:height="props.size"
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
		</svg>
	</span>
</template>

<script>
export default {
	name: 'GridView',
	props: {
		title: {
			type: String,
			default: 'Grid View icon',
		},
		decorative: {
			type: Boolean,
			default: false,
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

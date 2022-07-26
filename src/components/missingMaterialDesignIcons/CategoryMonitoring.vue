<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon category-monitoring-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg :width="size"
			:height="size"
			viewBox="0 0 16 16">
			<path d="m1 8h4l1.5-4.0935 3 8.1875 1.5-4.094h4"
				:stroke="fillColor"
				fill="none"
				stroke-miterlimit="4"
				stroke-width="2" />
		</svg>

	</span>
</template>

<script>
export default {
	name: 'CategoryMonitoring',
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

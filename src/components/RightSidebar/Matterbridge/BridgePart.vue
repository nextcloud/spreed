<!--
  - @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
  -
  - @author Julien Veyssier <eneiluj@posteo.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div class="part">
		<h3>
			<span>
				{{ type.name }}
			</span>
			<a v-if="type.infoTarget"
				class="icon icon-info"
				target="_blank"
				:href="type.infoTarget" />
			<button v-if="deletable"
				class="icon icon-delete"
				@click="$emit('deletePart')" />
		</h3>
		<div v-for="(field, key) in type.fields" :key="key">
			<div v-if="field.type === 'checkbox'" class="checkbox-container">
				<input
					:id="key + '-' + num"
					v-model="part[key]"
					:type="field.type"
					:class="classesOf(key)">
				<label :for="key + '-' + num">
					{{ field.labelText }}
				</label>
			</div>
			<div v-else>
				<label :for="key + '-' + num" class="hidden-visually">
					{{ field.placeholder }}
				</label>
				<input
					:id="key + '-' + num"
					v-model="part[key]"
					:type="field.type"
					:class="classesOf(key)"
					:placeholder="field.placeholder"
					:readonly="readonly"
					@focus="readonly = false">
			</div>
		</div>
	</div>
</template>

<script>

export default {
	name: 'BridgePart',
	components: {
	},

	mixins: [
	],

	props: {
		num: {
			type: Number,
			required: true,
		},
		part: {
			type: Object,
			required: true,
		},
		type: {
			type: Object,
			required: true,
		},
		deletable: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			readonly: true,
		}
	},

	computed: {
	},

	beforeMount() {
	},

	beforeDestroy() {
	},

	methods: {
		classesOf(name) {
			const classes = {
				icon: true,
			}
			classes[this.type.fields[name].icon] = true
			return classes
		},
	},
}
</script>

<style lang="scss" scoped>
.part {
	padding-top: 10px;
}

button {
	display: inline-block;
}

h3 {
	padding-left: 40px;
	display: flex;
	margin-bottom: 0;

	> span {
		flex-grow: 1;
		padding-top: 14px;
	}

	.icon {
		display: inline-block;
		width: 44px;
		height: 44px;
		border-radius: var(--border-radius-pill);
		opacity: .5;

		&.icon-delete {
			background-color: transparent;
			border: none;
			margin: 0;
		}

		&:hover,
		&:focus {
			opacity: 1;
			background-color: var(--color-background-hover);
		}
	}
}

input {
	background-size: 16px;
	background-position: 16px;
	padding-left: 40px;
	width: 100%;
	text-overflow: ellipsis;
	&[type=checkbox] {
		width: unset;
		margin-left: 15px;
		margin-right: 10px;
	}
}

.checkbox-container {
	display: flex;
	height: 40px;

	> label {
		flex-grow: 1;
		line-height: 40px;
	}

	&:hover {
		opacity: 1;
		background-color: var(--color-background-hover);
		border-radius: var(--border-radius-large);
	}
}
</style>
